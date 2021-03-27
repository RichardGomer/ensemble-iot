<?php

/**
 * Quick Scheduler Device; waits for a scheduler to be set from outside and then
 * pushes it to the configured context.
 * Useful when a schedule is generated based on some other event.
 */
namespace Ensemble\Schedule;

class QuickSchedulerDevice extends SchedulerDevice {

        private $schedule = false;

        // This just waits for the schedule to be set using setSchedule()
        public function reschedule() {
            $device = $this;
            return new Async\Lambda(function() use ($device) {
                if($device->schedule instanceof Schedule) {
                    $s = $device->schedule;
                    $device->schedule = false;
                    return $s;
                }

                yield;
            });
        }

        public function setSchedule(Schedule $s) {
            $this->schedule = $s;
        }
}
