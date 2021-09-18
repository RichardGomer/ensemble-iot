<?php

/**
 * IR controls for a frankly bizarre "Netta" branded heater
 *
 */
namespace Ensemble\Device\IR;
use Ensemble\Async as Async;

class NettaHeater extends IRDevice {

    public function __construct($name, \Ensemble\MQTT\Client $client, $deviceName, $context_device, $context_field) {
        $this->context_field = $context_field;
        $this->context_device = $context_device;

        parent::__construct($name, $client, $deviceName);
    }

    const CMD_UP = '{"Protocol":"SYMPHONY","Bits":12,"Data":"0xD84","DataLSB":"0xB021","Repeat":0}';
    const CMD_DOWN = '{"Protocol":"SYMPHONY","Bits":12,"Data":"0xDA0","DataLSB":"0xB005","Repeat":0}';

    public function isReady() {
        return $this->lastTemp !== false;
    }

    public function getTemperature() {
        return $this->lastTemp;
    }

    private $lastTemp = false;
    public function setTemperature($temp) {
        if($this->lastTemp === false) {
            $this->log("Temperature can't be set, because last temperature setting is unknown");
            return;
        }

        $diff = $temp - $this->lastTemp;
        $sign = $diff < 0 ? "" : "+";

        $this->log("Set temperature to $temp, {$sign}{$diff}");

        if($diff == 0) {
            return;
        }
        else if ($diff > 0) {
            for( ; $this->lastTemp < $temp; $this->lastTemp++) {
                $this->sendCommand(self::CMD_UP);
            }
        }
        else if ($diff < 0) {
            for( ; $this->lastTemp > $temp; $this->lastTemp--) {
                $this->sendCommand(self::CMD_DOWN);
            }
        }

        $cmd = \Ensemble\Command::create($this, $this->context_device, 'updateContext');
        $cmd->setArg('field', $this->context_field);
        $cmd->setArg('time', time());
        $cmd->setArg('value', $temp);
        $this->getBroker()->send($cmd);
    }

    /**
     * Heater status is stored in context; this context should be persistent so that
     * state isn't lost across restarts! There's NO way to force the Netta heater into
     * a known state - the temperature setting wraps around, and it even remembers
     * the last state over power cycles. :-/
     */
    private $state_field;
    private $state_device;
    protected function refreshState(){
        return new \Ensemble\Device\FetchContextRoutine($this, $this->context_device, $this->context_field);
    }

    public function getRoutine() {
        $heater = $this;
        return new Async\Lambda(function() use ($heater) {

            if($this->lastTemp === false) { // Only fetch current setting once
                // 1: Get the schedule from the configured context device
                try {
                    $this->lastTemp = yield $heater->refreshState();
                    if(is_object($this->lastTemp)) {
                        throw new Exception("Received an object instead of an integer!");
                    }
                    $this->log("Obtained heater state from context, ".$this->lastTemp);
                } catch(\Exception $e) {
                    $this->log("Couldn't fetch state: ".$e->getMessage());
                    $schedule = false;
                }
            }

        });
    }

}
