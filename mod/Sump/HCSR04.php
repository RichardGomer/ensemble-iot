<?php

namespace Ensemble\Device\Sump;

class HCSR04 extends DistanceSensor {

    public function __construct($name, $triggerPhys, $echoPhys)
    {
        $this->name = $name;
        $this->pinT = $triggerPhys;
        $this->pinE = $echoPhys;
    }

    protected function runMeasurements()
    {
        $pinT = (int) $this->pinT;
        $pinE = (int) $this->pinE;
        $bin = __DIR__.'/HCSR04/distance.py';
        $cmd = "python $bin {$this->pinT} {$this->pinE} 15";
        $proc = new \Ensemble\System\Thread($cmd);
        $proc->waitForExit();
        $lines = $proc->read();

        $m = array();
        foreach($lines as $l){
            if(preg_match('/^\.*Distance: ([0-9]+\.[0-9]+) cm/i', $l, $matches)) {
                $m[] = (double) $matches[1];
            }
        }

        return $m;
    }

}
