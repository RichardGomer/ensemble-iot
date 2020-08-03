<?php

/**
 * Represent a schedule
 * A schedule is a series of contiguous time periods with associated statuses
 */
namespace Ensemble\Schedule;

class Schedule {

    /**
     * @mixed $statuses; If provided, $statuses must be an array containing the
     * allowed statuses for the schedule; for instance, "ON", "OFF"
     */
    public function __construct($statuses = false) {
        $this->periods = array();
        $this->statuses = $statuses;
    }

    public static function fromJSON($json) {
        $sched = new Schedule();

        if(!is_array($json)) {
            $arr = json_decode($json);
        } else {
            $arr = $json;
        }

        foreach($arr as $t=>$s) {
            if(!is_numeric($t)) {
                throw new ScheduleException("'$t' is not a valid timestamp");
            }
            $sched->setPoint((int) $t, $s);
        }

        return $sched;
    }

    public function toJSON() {
        foreach($this->getPeriods() as $p) {
            $out[$p['start']] = $p['status'];
        }

        return json_encode($out);
    }

    /**
     * Set the status for a period of time
     */
    public function setPeriod($t_from, $t_to, $status) {

        $t_from = $this->normaliseTime($t_from);
        $t_to = $this->normaliseTime($t_to);

        // Find what the status needs to return to after this period
        $return = $this->getAt($t_to);

        // Delete interim periods
        $periods = $this->getPeriods();
        foreach($periods as $i=>$p) {
            if($p['start'] > $t_from && $p['start'] < $t_to) {
                unset($periods[$i]);
            }
        }

        // Ser the start
        $this->setPoint($t_from, $status);
        $this->setPoint($t_to, $return);
    }

    /**
     * Set status from time $t, until the next time that's set, or indefinitely
     */
    public function setPoint($t, $status) {
        $this->checkStatus($status);
        $t = $this->normaliseTime($t);
        $this->periods[] = array('start' => $t, 'status' => $status);
        usort($this->periods, function($a, $b) {
            return $a['start'] - $b['start'];
        });
    }

    /**
     * Get all defined periods, in order
     */
    protected function &getPeriods() {
        return $this->periods;
    }

    /**
     * @mixed $t : The time to normalise as a UNIX timestamp (integer), DateTime
     * object or
     * Returns a UNIX timestamp
     */
    protected function normaliseTime($t) {

        if(is_int($t))
            return $t;

        return strtotime($t);
    }

    protected function checkStatus($s) {

        if($this->statuses === false) {
            return;
        }

        if(in_array($s, $this->statuses)) {
            return true;
        }

        $v = implode(" ", $this->statuses);
        throw new ScheduleException("Status $s is not valid for Schedule; must be one of $v");
    }

    /**
     * Get status at the given time
     */
    public function getAt($time) {
        $t = $this->normaliseTime($time);

        $last = false;
        foreach($this->periods as $p) {
            if($p['start'] > $time) {
                break;
            }
            $last = $p;
        }

        if($last === false) {
            return false;
        }

        //echo "Get at $t\n";
        //var_dump($last);
        return $last['status'];
    }

    public function getNow() {
        return $this->getAt(time());
    }

    /**
     * Get change points - times where the status of the schedule changes
     */
    public function getChangePoints() {
        $times = array();
        foreach($this->periods as $s) {
            $times[] = $s['start'];
        }

        sort($times);
        return array_unique($times);
    }

    /**
     * Combine multiple schedules using a custom resolver function
     * Schedules are matched up; for the whole period that exists in ALL the schedules,
     * the resolver is used to compute a status for a combined Schedule, which is
     * returned.
     */
    public static function combine(callable $resolver, ...$scheds) {

    }

}

class ScheduleException extends \Exception {}
