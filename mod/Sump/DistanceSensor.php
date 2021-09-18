<?php

abstract class DistanceSensor extends \Ensemble\Device\SensorDevice
{

  public function measure($send=false)
  {
      $m = $this->runMeasurements();

      if(count($m) < 1) {
          return false;
      }

      $dist = $this->median($m);
      $res = array('time'=>time(), 'value'=> $dist);

      if($send) {
          $this->pushToDestinations($res);
      }

      return $res;
  }


}

class HCSSR04 extends DistanceSensor {

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

class TFLuna extends DistanceSensor {

  /**
   * dev: A device name, e.g. /dev/ttyS0
   */
  public function __construct($name, $dev)
  {
      $this->name = $name;
      $this->device = $dev;
  }

  protected function runMeasurements()
  {
      $dev = $this->device;
      $bin = __DIR__.'/TFLuna/distance.py';
      $cmd = "python3 $bin $dev 5";
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
