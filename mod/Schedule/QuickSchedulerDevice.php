<?php

/**
 * Quick Scheduler Device; waits for a scheduler to be set from outside and then
 * pushes it to the configured context.
 * Useful when a schedule is generated based on some other event.
 */
namespace Ensemble\Schedule;

class QuickSchedulerDevice extends SchedulerDevice {

        public $schedule = false;
        private $done = false;

        // This just waits for the schedule to be set using setSchedule()
        public function reschedule() {
            $device = $this;
            return new \Ensemble\Async\Lambda(function() use ($device) {
                while(true) {
                    if($device->schedule instanceof Schedule) {
                        $s = $device->schedule;
                        $device->schedule = false;
                        $device->done = true;
                        return $s;
                    }

                    yield;
                }
            });
        }

        public function setSchedule(Schedule $s) {
            $this->schedule = $s;
        }

        public function getPollInterval() {
            return $this->done ? 120 : 15;
        }
}
