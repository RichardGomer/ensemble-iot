<?php

namespace Ensemble\Schedule;
use Ensemble\Async as Async;

/**
 * A scheduler device maintains a schedule somehow.
 *
 * This base class provdes support to recalculate the schedule regularly and
 * to push it to a context
 */
abstract class SchedulerDevice extends Async\Device {

    public function __construct($name, $device, $field) {
        $this->name = $name;
        $this->setContext($device, $field);
    }

    /**
     * Set the context broker device and field that the schedule should be
     * sent to
     */
    public function setContext($device, $field) {
        $this->contextdevice = $device;
        $this->contextfield = $field;
    }

    /**
     * Generate and return a schedule
     *
     * Reschedule can return a Routine, in which case the Routine should return
     * the Schedule once it is complete, or just a return a Schedule immediately
     */
    abstract public function reschedule();

    /**
     * Set the rescheduling interval that reschedule() is called at
     * defaults to hourly
     */
    private $reschedint = 3600;

    public function setRescheduleInterval($int) {
        $this->reschedint = (int) $int;
    }

    public function getPollInterval() {
        return 60;
    }

    public function getRoutine() {
        $device = $this;
        return new Async\Lambda(function() use ($device) {
            $start = time();

            // Yield to the rescheduler
            echo "Reschedule...\n";
            $d = $this;
            // We wrap it like this so that the value returned by reschedule() can be
            // either a literal, or a Routine
            $sched = $d->reschedule();

            if(!$sched instanceof Schedule) {
                throw new SchedulerDeviceException("Scheduler did not return a Schedule object");
            }

            $this->schedule = $sched;

            echo "Received schedule. Push to context.\n";

            // Send the schedule to the broker
            $cmd = \Ensemble\Command::create($this, $this->contextdevice, 'updateContext');
            $cmd->setArg('field', $this->contextfield);
            $cmd->setArg('time', time());
            $cmd->setArg('value', $this->schedule->toJSON());
            $this->getBroker()->send($cmd);

            // Wait until the next reschedule is due
            yield new Async\waitUntil($start + $this->reschedint);
        });
    }

}

class SchedulerDeviceException extends \Exception {}
