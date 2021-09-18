<?php

/**
 * A specific kind of schedule that can be on, or off, or "opportunistically off"
 * and adds some convenience methods for logical operations
 *
 */

namespace Ensemble\Schedule;

class OnSchedule extends Schedule {

    public function __construct() {
        parent::__construct(array('ON', 'OFF', 'OPOFF'));
        $this->setPoint(0, 'OFF');
    }

    /**
     * Set "ON" at $onTime and "OFF" before ontime and after offtime
     * offTime must be after ontime. Strings are normalised
     */
    public static function onBetween($onTime, $offTime) {
        $s = new OnSchedule();

        if($this->normaliseTime($offTime) < $this->normaliseTime($onTime)) {
            throw new Exception("off time must be after ontime");
        }

        $s->setPeriod($onTime, $offTime, 'ON');

        return $s;
    }

    /**
     * Return the most restrictive schedule. Basically equivalent to AND'ing, except
     * that we handle the non-binary OPOFF in a sensible way
     */
    public function and(OnSchedule $s) {
        return self::reduce(array($this, $s), function($statuses){
            if(in_array('OFF', $statuses)) {
                return 'OFF';
            } elseif(in_array('OPOFF', $statuses)) {
                return 'OPOFF';
            } else {
                return 'ON';
            }
        });
    }

    /**
     * Return a schedule that's on when either this schedule or the supplied
     * schedule is ON
     */
    public function or(OnSchedule $s) {
        return self::reduce(array($this, $s), function($statuses){
            if(in_array('ON', $statuses)) {
                return 'ON';
            } elseif(in_array('OPOFF', $statuses)) {
                return 'OPOFF';
            } else {
                return 'OFF';
            }
        });
    }


}
