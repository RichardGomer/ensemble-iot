<?php

namespace Ensemble\Device\Temperature;


// A single sensor
class OregonSensor extends \Ensemble\Device\SensorDevice
{
    public function __construct($name, OregonSensorSet $source, $channel) {
        $this->name = $name;
        $this->source = $source;
        $this->channel = $channel;
    }

    public function getPollInterval() {
        return 60;
    }

    private $maxAge = 120; // Readings can't be older than this
    public function measure() {
        $readings = $this->source->getReadings();

        if(!array_key_exists($this->channel, $readings)) {
            return false;
        }

        $last = max(array_keys($readings[$this->channel]));
        if(time() - $last > $this->maxAge) {
            return false;
        }

        return array('time'=>$last, 'value'=>$readings[$this->channel][$last]);
    }
}

// A set of sensors
class OregonSensorSet extends \Ensemble\Device\BasicDevice
{
    private $proc;
    public function __construct($name)
    {
        $this->name = $name;
        $this->proc = new \Ensemble\System\Thread('sudo ./mods-bin/oregonrcv');
    }

    public function getPollInterval() {
        return 30;
    }

    private $readings = array();
    private $maxreadings = 30; // Maximum number of readings to store
    public function poll(\Ensemble\CommandBroker $b)
    {
        $lines = $this->proc->read();
        foreach($lines as $l)
        {
            $l = trim($l);

            if(preg_match('/([0-9]+): ([0-9]{1,2}\.[0-9]+)/', $l, $matches))
            {
                $channel = $matches[1];
                $temp = $matches[2];
                if(!array_key_exists($channel, $readings)){
                    $this->readings[$channel] = array();
                }

                while(count($readings[$channel]) > $this->maxreadings)
                {
                    unset($readings[$channel][array_keys($readings[$channel])[0]]);
                }

                $time = floor(time() / $this->resolution) * $this->resolution;
                $this->readings[$channel][$time] = $temp;
            }
        }
    }

    public function getReadings() {
        return $this->readings;
    }

    // Get a single-channel sensor
    public function getChannelSensor($name, $channel) {
        return new OregonSensor($name, $this, $channel);
    }
}
