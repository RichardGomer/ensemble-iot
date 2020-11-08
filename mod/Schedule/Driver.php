<?php

/**
 * Drive another device based on a schedule
 *
 */
namespace Ensemble\Schedule;
use Ensemble\Async as Async;

class Driver extends Async\Device {
    public function __construct(\Ensemble\Module $target, $setFunc, $context_device, $context_field, $translator=false) {
        $this->target = $target;
        $this->setFunc = $setFunc;

        $this->context_device = $context_device;
        $this->context_field = $context_field;

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
                $this->log("Trying to fetch schedule {$this->context_field} from {$this->context_device}");
                $schedule = yield new Async\TimeoutController(new FetchRoutine($this, $this->context_device, $this->context_field), 60);
            } catch(\Exception $e) {
                $this->log("Couldn't fetch schedule: ".$e->getMessage());
                $schedule = false;
            }

            if(!$schedule) {
                return;
            }

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
}
