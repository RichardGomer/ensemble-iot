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
        $this->setTimezone(date_default_timezone_get());
    }

    /**
     * Set the schedule's timezone
     * The timezone controls how strings are interpreted and how prettyPrint
     * formats output
     * Internally, all times are UTC
     */
    private $timezone = 'UTC';
    public function setTimezone($tz) {
        if(count($this->periods) > 0 && $this->periods[0]['start'] !== 0) {
            //echo $this->prettyPrint();
            throw new \Exception("Timezone cannot be changed on a populated schedule");
        }
        $this->tzo = false; // Clear timezone object cache
        $this->timezone = $tz;
    }

    public function getTimezone() {
        return $this->timezone;
    }

    public static function fromJSON($json) {
        $c = get_called_class();
        $sched = new $c();

        if(!is_array($json)) {
            $arr = json_decode($json, true);
        } else {
            $arr = $json;
        }

        if(!is_array($arr)) {
            throw new \Exception("Bad schedule JSON:\n".$json."\n");
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

    public function prettyPrint($extended=false) {
        $out = $extended ? "    [Schedule tz = {$this->timezone}]\n    [ts             dur          start                  ]\n" : "";
        foreach($this->getAllPeriods() as $t) {
            $time = new \DateTime("now", $this->getTZO());
            $time->setTimestamp($t['start']); // There's no constructor for this!
            $ext = $extended ? (sprintf("%010d   ", $t['start'])."  ".($t['end'] == false ? " ∞        " : sprintf("%- 10d", $t['end'] - $t['start'])))."   " : "";
            $out .= "    [{$ext}".$time->format('Y-m-d H:i:s T')."] ".$t['status']."\n";
        }
        return $out;
    }

    /**
     * Set the status for a period of time
     */
    public function setPeriod($t_from, $t_to, $status, $tidy=true) {

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

        if($tidy)
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
     * A more usable form of periods, including end time
     */
    public function getAllPeriods() {
        $periods = array();
        $keys = array_keys($this->periods);
        foreach($keys as $i=>$k) {
            $p = $this->periods[$k];
            $next = $i < count($keys) - 1 ? $this->periods[$keys[$i+1]] : false;
            $periods[]  = array(
                'status'=>$p['status'],
                'start'=>$p['start'],
                'end'=> $next === false ? false : $next['start']
            );
        }

        return $periods;
    }

    /**
     * @mixed $t : The time to normalise as a UNIX timestamp (integer) or ISO8601 string
     * Returns a UNIX timestamp
     */
    protected function normaliseTime($t) {

        if(is_int($t))
        return $t;

        if(is_numeric($t))
        return (int) $t;

        $d = new \DateTime($t, $this->getTZO());
        return $d->getTimestamp();
    }

    protected $tzo = false;
    public function getTZO(){
        if($this->tzo === false) {
            $this->tzo = new \DateTimeZone($this->timezone);
        }
        return $this->tzo;
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

    /**
     * Get status now, optionally offset by a given number of seconds
     */
    public function getNow($offset = 0) {
        return $this->getAt(time() + $offset);
    }

    // Return the current period in the format array($lastChangeTime => $currentStatus, $nextChangeTime => $nextStatus)
    public function getCurrentPeriod($offset=0) {
        $now = time() + $offset;
        $lastTime = false;
        foreach($this->getChangePoints() as $time) {
            if($time > $now) {
                if($lastTime == false) {
                    return array($time=>$this->getAt($time));
                }
                return array($lastTime => $this->getAt($lastTime), $time=>$this->getAt($time));
            }
            $lastTime = $time;
        }
        return array($lastTime=>$this->getAt($time));
    }

    public function getNextChangeTime() {
        $now = time();
        foreach($this->getChangePoints() as $time) {
            if($time > $now) {
                return $time;
            }
        }

        return false;
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
     * Get a new instance of whatever class this object is
     */
    protected function factory() {
        $c = get_class($this);
        return new $c();
    }

    /**
     * Extract a period between the start and end times
     * Start is assumed to be today; end can be today or tomorrow
     * Times must be in format hh:mm
     * $then is the value that the end point should be set to, defaults false
     */
    public function between($start, $end, $then=false) {
        $tp = '/[0-9]{2}:[0-9]{2}/';
        if(!preg_match($tp, $start) || !preg_match($tp, $end)) {
            throw new \Exception("Times passed to TariffSchedule::between() must be in format hh:mm");
        }

        $tstart = strtotime($start);
        $tend = strtotime($end);

        if($tend <= $tstart) {
            $tend = strtotime("tomorrow {$end}");
        }

        echo date("Y-m-d H:i:s", $tstart)." - ".date('Y-m-d H:i:s', $tend)."\n";

        $out = $this->factory();

        $out->setPoint($tstart, $this->getAt($tstart));

        foreach($this->getChangePoints() as $cp) {
            if($cp > $tstart && $cp < $tend) {
                $out->setPoint($cp, $this->getAt($cp));
            }
        }

        $out->setPoint($tend, $then);

        return $out;
    }


    /**
     * Use the supplied translation function to create a new schedule from this one
     * The translator function takes a single argument, a status, and returns a status
     * for the new schedule
     */
    public function translate($translator, Schedule $out=null) {
        if(!$out instanceof Schedule)
        $out = new Schedule();

        foreach($this->getPeriods() as $p) {
            $out->setPoint($p['start'], $translator($p['status']));
        }

        return $out;
    }


    /**
     * Reduce the given schedules using the provided translation function
     * Like translate(), but all the schedules are combined into one, and
     * the translation function receives the value of all schedules at each
     * change point. A change point is any point at which one or more schedules
     * change state.
     */
    public static function reduce($schedules, $f, Schedule $out=null) {
        $out = $out == null ? $schedules[0]->factory() : $out;

        $changepoints = [];
        foreach($schedules as $s) {
            $changepoints = array_merge($s->getChangePoints(), $changepoints);
        }

        sort($changepoints);
        $changepoints = array_unique($changepoints);

        foreach($changepoints as $time) {

            $statuses = [];
            foreach($schedules as $s) {
                $statuses[] = $s->getAt($time);
            }

            $v = $f($statuses);
            //$tstr = date('[Y-m-d H:i:s]', $time);
            //echo "Reduce @ $tstr [".implode(",", $statuses)."] to ".$v."\n";

            $out->setPoint($time, $v);
        }

        return $out;
    }

}

class ScheduleException extends \Exception {}
