<?php

namespace Ensemble\Device\Sump;

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
