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
        return json_encode($this->toArray());
    }

    /**
     * This array is compatible with contextDevice series updates
     */
    public function toArray() {
        $out = [];
        foreach($this->getPeriods() as $p) {
            $out[$p['start']] = $p['status'];
        }

        return $out;
    }

    public function prettyPrint() {
        $out = '';
        foreach($this->getChangePoints() as $t) {
            $out .= "    ".date('[Y-m-d H:i:s]', $t)." ".$this->getAt($t)."\n";
        }
        return $out;
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
        $periods = &$this->getPeriods();
        foreach($periods as $i=>$p) {
            if($p['start'] >= $t_from && $p['start'] <= $t_to) {
                unset($periods[$i]);
            }
        }

        // Set the start
        $this->setPoint($t_from, $status);
        $this->setPoint($t_to, $return);
        $this->tidy();
    }

    /**
     * Set status from time $t, until the next time that's set, or indefinitely
     */
    public function setPoint($t, $status) {
        $this->checkStatus($status);
        $tn = $this->normaliseTime($t);
        //echo "Normalise $t to $tn\n";
        $this->periods[] = array('start' => $tn, 'status' => $status);
        usort($this->periods, function($a, $b) {
            return $a['start'] - $b['start'];
        });
    }

    /**
     * Remove redundant points
     */
    protected function tidy() {
        $last = false;
        foreach($this->periods as $i=>$p) {
            if($last !== false) {
                if($last['status'] == $p['status']) {
                    unset($this->periods[$i]);
                }
            }

            $last = $p;
        }
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

        if(is_numeric($t))
            return (int) $t;

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

    // Return the current period in the format array($lastChangeTime => $currentStatus, $nextChangeTime => $nextStatus)
    public function getCurrentPeriod() {
        $now = time();
        foreach($this->getChangePoints() as $time) {
            if($time > $now) {
                if($lastTime == false) {
                    return array($time=>$this->getAt($time));
                }
                return array($lastTime => $this->getAt($lastTime), $time=>$this->getAt($time));
            }
            $lastTime = $time;
        }
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
     * Use the supplied translation function to create a new schedule from this one
     * The translator function takes a single argument, a status, and returns a status
     * for the new schedule
     */
    public function translate($translator) {
            $out = new Schedule();

            foreach($this->getPeriods() as $p) {
                $out->setPoint($p['start'], $translator($p['status']));
            }

            return $out;
    }
}

class ScheduleException extends \Exception {}
