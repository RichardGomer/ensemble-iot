<?php

/**
 * Project a daily schedule into the future
 * CLOCK TIME is maintained, so 0400GMT maps to 0400BST when the timezone
 * changes
 * 
 * Some meta-values are supported. 
 * eg: 1800 => @dusk value
 * 
 * becomes 1800 or dusk (whichever is LATER) => value
 */

namespace Ensemble\Schedule;

use DateTime;

class DailyProjector  {

    private $lat, $lng, $base;

    /**
     * Construct with the base schedule
     */
    public function __construct(Schedule $base, $lat, $lng) {
        $this->base = $base;
        $this->lat = $lat;
        $this->lng = $lng;
    }

    /**
     * ...then project it to other days!
     * $start and $end are timestamps that indicate the start and end days for the projection
     */
    public function project($start, $end = false, Schedule $to = null) {

        if($end === false) {
            $end = $start;
        }

        $temp = new Schedule();

        if(!$to instanceof Schedule) {
            $c = get_class($this->base);
            $to = new $c();
        }

        $startDate = new \DateTime("now", $this->base->getTZO()); $startDate->setTimestamp($start);
        $endDate = new \DateTime("now", $this->base->getTZO()); $endDate->setTimestamp($end);

        $to->setTimezone($this->base->getTimezone());
        $to->setPoint(0, $this->base->getAt(0));

        $temp->setTimezone($this->base->getTimezone());
        $temp->setPoint(0, $this->base->getAt(0));

        $date = new \DateTime($startDate->format('Y-m-d'), $tz = $this->base->getTZO());

        // Iterate through each (whole) day that falls (at least partially) within the period, projecting
        // the schedule into it
        while($date <= $endDate) {
            foreach($this->base->getAllPeriods() as $i=>$p) {
                // Skip the first period, because it will be the value at epoch
                if($i==0) continue;
                
                // Get the clock time from the base schedule
                $time = new \DateTime("now", $tz); $time->setTimestamp($p['start']); // There's no constructor for this!
                $clocktime_h = $time->format('H');
                $clocktime_m = $time->format('i');
                $clocktime_s = $time->format('s');

                // Set the clock time on the new schedule to the same
                $date->setTime($clocktime_h, $clocktime_m, $clocktime_s);

                // Now set the absolute time on the output schedule
                $temp->setPoint($date->format('Y-m-d H:i:s'), $p['status']);
            }

            // Move to next day
            $date->setTime(0,0);
            $date = $date->add(new \DateInterval("P1D"));
        }

        // Now process meta values
        foreach($temp->getAllPeriods() as &$period) {
            // Process meta-values
            $period = $this->processMeta($period);
            $to->setPoint($period['start'], $period['status']);
        }

        return $to;

    }

    /**
     * Supported meta times are:
     * 
     *     @(EXPR) value
     *     EXPR: ANCHOR | ANCHOR '+' SECS
     *     ANCHOR: 'dawn' | 'dusk' | 'sunrise' | 'sunset'
     *     SECS: [0-9]+
     */
    protected function processMeta($period) {

        $minTime = $period['start'];
        $maxTime = $period['end'] ?? INF;
        $status = $period['status'];

        $ft = function($ts) {
            $dt = new \DateTime();
            $dt->setTimestamp((int) $ts);
            return $dt->format('Y-m-d H:i:s T');
        };

        if(preg_match('/^@(dawn|dusk|sunset|sunrise)((\+|\-)([0-9]+))? (.*)$/', $status, $matches)) {

            //echo "Meta-value: $status\n";

            $sunTimes = date_sun_info($period['start'], $this->lat, $this->lng);
            //$fts = $ft($ts);
            //echo "$status ::: Sun times $ts $fts:\n";
            //var_dump($sunTimes);

            switch($matches[1]) {
                case 'dawn':
                    $stime = $sunTimes['civil_twilight_begin'];
                    break;
                case 'sunrise':
                    $stime = $sunTimes['sunrise'];
                    break;
                case 'sunset':
                    $stime = $sunTimes['sunset'];
                    break;
                case 'dusk':
                    $stime = $sunTimes['civil_twilight_end'];
                    break;
            }

            $offset = $matches[4] == "" ? 0 : $matches[4];
            $offset = $matches[3] == "+" ? $offset : $offset * -1;

            $stoff = $stime + $offset;

            $fmin = $ft($minTime);
            $fanc = $matches[1];
            $fst = $ft($stime);
            $fstoff = $ft($stoff);

            //echo "  minTime: $fmin   anchor: $fanc  stime: $fst  offset: $offset    st+off: $fstoff \n";

            $final = max($minTime, $stoff);

            //echo "  =>  $final   ".$ft($final)."\n";

            $period['start'] = $final;
            $period['status'] = $matches[5];
        }

        return $period;

    }

}
