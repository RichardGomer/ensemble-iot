<?php

/**
 * Drive another device based on a schedule
 *
 */
namespace Ensemble\Schedule;
use Ensemble\Async as Async;
use Ensemble\Device as Device;

class Driver extends Async\Device {
    public function __construct(\Ensemble\Module $target, $setFunc, Device\ContextPointer $ctxptr, $translator=false) {
        $this->target = $target;
        $this->setFunc = $setFunc;

        $this->ctx = $ctxptr;

        $this->translator = $translator;

        $this->name = $this->target->getDeviceName().'-schedule_driver-'.random_int(100000,999999);

        $this->override = new Schedule();
        $this->override->setPoint(0, false);
    }

    public function setTranslator($f) {
        $this->translator = $f;
    }

    public $refreshTime = 600;
    public function getRoutine() {
        return new Async\Lambda(function(){

            // First, fetch the schedule
            try {
                $this->log("Trying to fetch schedule {$this->ctx->toString()}");
                $schedule = yield new Async\TimeoutController($this->ctx->getFetchRoutine($this), 60);
            } catch(\Exception $e) {
                $this->log("Couldn't fetch schedule: ".$e->getMessage());
                $schedule = false;
            }

            if(!$schedule) {
                return;
            }

            $schedule = Schedule::fromJSON($schedule);

            if(is_callable($this->translator)) {
                $schedule = $schedule->translate($this->translator);
                $this->log("Translated schedule for local driver\n".$schedule->prettyPrint());
            }

            // Then use it to drive the device until it's time to refresh the schedule again
            $expire = time() + $this->refreshTime;
            while(time() < $expire) {

                $over = $this->override->getNow();
                if($over !== false) { // Apply the override, if set
                    $current = $over;
                    $this->log("Current status is $current (OVERRIDDEN)");
                }
                else {
                    $current = $schedule->getNow();
                    $this->log("Current status is $current");
                }

                // If necessary, yield anything that the set function needs to do asynchronously
                $res = ($this->setFunc)($this->target, $current);
                if($res instanceof \Traversable)
                    yield from $res;

                yield; // Then yield to allow control to return to main loop
            }
        });
    }

    /**
     * Get the override schedule
     */
    public function getOverride() {
        return $this->override;
    }

    /**
     * Convenience methods to set or clear override
     */
    public function setOverride($value, $time) {
        $this->getOverride()->setPeriod(time(), time() + $time, $value);
    }

    public function clearOverride($time) {
        $this->getOverride()->setPeriod(0, time() + $time, false);
    }
}
