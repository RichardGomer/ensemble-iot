<?php

/**
* Drive other devices based on context values
*/

namespace Ensemble\Device;
use Ensemble\Async as Async;
use Ensemble\Device\FetchContextRoutine as FetchRoutine;

class ContextDriver extends Async\Device {
    public function __construct(\Ensemble\Module $target, $setFunc, $context_device, $context_field) {
        $this->target = $target;
        $this->setFunc = $setFunc;

        $this->context_device = $context_device;
        $this->context_field = $context_field;

        $this->name = $this->target->getDeviceName().'-context_driver-'.random_int(100000,999999);
    }

    public $refreshTime = 30;
    public function setRefreshTime($secs) {
        $this->refreshTime = $secs;
    }

    public function getPollInterval() {
        return $this->refreshTime;
    }

    public function getRoutine() {
        return new Async\Lambda(function(){

            // First, fetch the schedule
            try {
                $this->log("Trying to fetch context field {$this->context_field} from {$this->context_device}");
                $schedule = yield new Async\TimeoutController(new FetchRoutine($this, $this->context_device, $this->context_field), 10);
            } catch(\Exception $e) {
                $this->log("Couldn't fetch context field: ".$e->getMessage());
                $schedule = false;
            }

            if(!$schedule) {
                return;
            }

            // Then use it to drive the device until it's time to refresh the schedule again
            $expire = time() + $this->refreshTime;
            while(time() < $expire) {

                    $current = $schedule;
                    $this->log("Current status is $current");

                // If necessary, yield anything that the set function needs to do asynchronously
                $res = ($this->setFunc)($this->target, $current);
                if($res instanceof \Traversable)
                yield from $res;

                yield; // Then yield to allow control to return to main loop
            }
        });
    }
}
