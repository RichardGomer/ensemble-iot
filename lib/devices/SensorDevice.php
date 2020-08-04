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
        $this->pushToDestinations($m, $broker);
    }

    final public function getAndPush(\Ensemble\CommandBroker $broker) {
        $m = $this->measure();
        $this->pushToDestinations($m, $broker);
        return $m;
    }

    protected function pushToDestinations($m, \Ensemble\CommandBroker $broker) {
        if(is_null($m) || $m === false) {
            return;
        }

        if(!is_array($m) || !array_key_exists('time', $m) || !array_key_exists('value', $m)) {
            $m = array('time'=>time(), 'value'=>$m);
        }

        foreach($this->destinations as $d) {
            $cmd = \Ensemble\Command::create($this, $d['device'], 'updateContext');
            $cmd->setArg('field', $d['field']);
            $cmd->setArg('time', $m['time']);
            $cmd->setArg('value', $m['value']);
            $broker->send($cmd);
        }
    }

    public function addDestination($device, $field) {
        $this->destinations[] = array('device'=>$device, 'field'=>$field);
    }

}

class BadValueException extends \Exception {}
