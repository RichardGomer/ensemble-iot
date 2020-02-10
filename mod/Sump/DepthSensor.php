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
    public function __construct($name, $triggerPhys, $echoPhys, $maxDepth)
    {
        $this->name = $name;
        $this->pinT = $triggerPhys;
        $this->pinE = $echoPhys;
        $this->maxDepth = $maxDepth;
    }

    public function measure()
    {
        $lines = $this->runMeasurements($this->pinT, $this->pinE, 10);

        $m = array();
        foreach($lines as $l){
            if(preg_match('/^\.*Distance: ([0-9]+\.[0-9]+) cm/i', $l, $matches)) {
                $m[] = $matches[1];
            }
        }

        if(count($m) < 1) {
            return false;
        }

        $dist = $this->median($m);

        // The depth of the water is the maxdepth minus the distance we measured
        return $this->maxDepth - $dist;
    }

    protected function runMeasurements($pinT, $pinE, $n)
    {
        $pinT = (int) $pinT;
        $pinE = (int) $pinE;
        $n = (int) $n;
        $proc = new \Ensemble\System\Thread("python ./mods-bin/distance.py $pinT $pinE $n");
        $proc->waitForExit();
        return $proc->read();
    }

    protected function median($array)
    {
        sort($array);

        $mid = floor(count($array) / 2);
        $keys = array_keys($array);

        if(count($array) == 1) {
            return $array[$keys[0]];
        }
        elseif(count($array) % 2 == 0) {
            return 0.5 * ($array[$keys[$mid]] + $array[$keys[$mid+1]]);
        }
        else {
            return $array[$keys[$mid]];
        }
    }
}
