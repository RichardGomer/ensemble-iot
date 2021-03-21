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

    public function __construct($name, $device=false, $field=false) {
        $this->name = $name;

        if($device !== false && $field !== false)
            $this->setContext($device, $field);
    }

    /**
     * There are two modes for pushing context; JSON or SERIES
     * JSON pushes a json serialisation of the schedule, suitable for hydrating
     * into a schedule object
     * Series pushes a series, such that the context device will treat it as a
     * series of timestamped values
     */
    const MODE_JSON = 1;
    const MODE_SERIES = 2;

    /**
     * Set the context broker device and field that the schedule should be
     * sent to
     */
    private $contexts = [];
    public function setContext($device, $field, $mode=self::MODE_JSON) {
        $this->contexts[] = array('device'=>$device, 'field'=>$field, 'mode'=>$mode);
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

            $sched = $device->reschedule();

            if(!$sched instanceof Schedule) {
                throw new SchedulerDeviceException("Scheduler did not return a Schedule object");
            }

            $device->schedule = $sched;

            foreach($device->contexts as $ctx) {
                $ctxdevice = $ctx['device'];
                $ctxfield = $ctx['field'];
                $mode = $ctx['mode'];

                $this->pushToContexts($ctxdevice, $ctxfield, $mode, $sched);
            }

            // Wait until the next reschedule is due
            yield new Async\waitUntil($start + $device->reschedint);
        });
    }

    protected function pushToContexts($device, $field, $mode, Schedule $schedule) {
        $cmd = \Ensemble\Command::create($this, $device, 'updateContext');
        $cmd->setArg('field', $field);

        if($mode == self::MODE_JSON) {
            $cmd->setArg('time', time());
            $cmd->setArg('value', $schedule->toJSON());
        }
        else {
            $cmd->setArg('series', $schedule->toArray());
        }

        $this->getBroker()->send($cmd);
    }

}

class SchedulerDeviceException extends \Exception {}
