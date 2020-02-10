<?php

/**
 * A base class for sensors, adds methods for registereing places to push data
 */
namespace Ensemble\Device;

abstract class SensorDevice extends BasicDevice  {

    // Must return an array in the format (time=> measurement_timestamp, value=>measured_value)
    abstract public function measure();

    final public function poll(\Ensemble\CommandBroker $broker) {
        $m = $this->measure();

        if(is_null($m) || $m === false) {
            return;
        }

        if(!is_array($m) || !array_key_exists('time', $m) || !array_key_exists('value', $m)) {
            throw new BadValueException("Sensors must return a time/value array - check ".get_class($this)."->measure()");
        }

        foreach($this->destinations as $d) {
            $cmd = \Ensemble\Command::create($this, $d, 'updateContext');
            $cmd->setArg('time', $m['time']);
            $cmd->setArg('value', $m['value']);
            $broker->send($cmd);
        }
    }

    public function addDestination($name) {
        $this->destinations[] = $name;
    }

}

class BadValueException extends \Exception {}
