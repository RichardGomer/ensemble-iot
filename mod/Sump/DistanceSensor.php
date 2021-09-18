<?php

namespace Ensemble\Device\Sump;

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
