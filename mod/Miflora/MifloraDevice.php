<?php

/**
* Device support for Xiaomi MiFlora devices
*
* The device sends multiple fields (temperature, moisture, light, conductivity)
* which can each be detected by a MifloraSensor, reading from a master MifloraDevice
* that starts a background polling process
*/

namespace Ensemble\Device\Miflora;

class MifloraSensor extends \Ensemble\Device\SensorDevice
{
    public function __construct($name, MifloraDevice $source, $field) {
        $this->name = $name;
        $this->source = $source;
        $this->field = $field;
    }

    public function getPollInterval() {
        return $this->source->getPollInterval();
    }

    private $maxAge = 60; // Readings can't be older than this
    public function measure() {
        $readings = $this->source->getReadings();

        if(!array_key_exists($this->field, $readings)) {
            return false;
        }

        $last = max(array_keys($readings[$this->field]));
        if(time() - $last > $this->maxAge) {
            return false;
        }

        return array('time'=>$last, 'value'=>$readings[$this->field][$last]);
    }
}

/**
* Polls data from a miflora device
*
*/
class MifloraDevice extends \Ensemble\Device\BasicDevice
{
    private $proc;
    public function __construct($name, $mac)
    {
        $this->name = $name;
        $this->mac = $mac;
        $this->startProc();
    }

    public function getPollInterval() {
        return 60;
    }

    protected function startProc() {
        $bin = "python3 -u ".__DIR__."/run.py {$this->mac}"; // Based on https://github.com/basnijholt/miflora
        $this->proc = new \Ensemble\System\Thread($bin);
    }

    private $readings = array();
    private $maxreadings = 30; // Maximum number of readings to store
    public function poll(\Ensemble\CommandBroker $b)
    {
    	if(!$this->proc->isRunning()) {
            echo "miflora process died, will restart\n";
            var_dump($this->proc->read());
    		var_dump($this->proc->error());
            $this->startProc();
    		//throw new \Exception("miflora process died");
    	}

        $lines = $this->proc->read();
        foreach($lines as $l)
        {
            $l = trim($l);
            echo "Line: {$l}\n";
            if(preg_match('/([0-9]+) ([a-z]+) ([0-9]{1,2}(\.[0-9]+)?)/i', $l, $matches))
            {
                $time = $matches[1];
                $field = $matches[2];
                $value = $matches[3];
                if(!array_key_exists($field, $this->readings)){
                    $this->readings[$field] = array();
                }

                while(count($this->readings[$field]) > $this->maxreadings)
                {
                    unset($this->readings[$field][array_keys($this->readings[$field])[0]]);
                }

                $this->readings[$field][time()] = $value;
            }
        }
    }

    public function getReadings() {
        return $this->readings;
    }

    // Get a single-channel sensor
    public function getSensor($name, $field) {
        return new MifloraSensor($name, $this, $field);
    }
}
