<?php

/**
 * The SUMP module is for measuring water depth in a draniage sump, and controlling a pump
 */

namespace Ensemble\Device\Sump;

/**
 * Measure depth using something like an HC-SR04 sensor
 * triggerPhys / echoPhys are the physical pin numbers of the trigger and echo pins
 * $maxDepth is the distance between the sensor and the bottom of the hole
 */
class DepthSensor extends \Ensemble\Device\SensorDevice
{
    public function __construct($name, DistanceSensor $sensor, $maxDepth)
    {
        $this->name = $name;
        $this->sensor = $sensor;
        $this->maxDepth = $maxDepth;
    }

    public function measure($send=false)
    {
        $d = $this->sensor->measure();

        if($d === false) {
            return false;
        }

        $res = array('time'=>time(), 'value'=> $this->maxDepth - $d['value']);

        if($send) {
            $this->pushToDestinations($res);
        }

        // The depth of the water is the maxdepth minus the distance we measured
        return $res;
    }
}
