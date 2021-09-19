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
