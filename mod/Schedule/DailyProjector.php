<?php

/**
 * Project a daily schedule into the future
 * CLOCK TIME is maintained, so 0400GMT maps to 0400BST when the timezone
 * changes
 */

namespace Ensemble\Schedule;

class DailyProjector  {

    /**
     * Construct with the base schedule
     */
    public function __construct(Schedule $base) {
        $this->base = $base;
    }

    /**
     * ...then project it to other days!
     * $start and $end are timestamps that indicate the start and end days for the projection
     */
    public function project($start, $end = false, Schedule $to = null) {

        if($end === false) {
            $end = $start;
        }

        if(!$to instanceof Schedule) {
            $c = get_class($this->base);
            $to = new $c();
        }

        $to->setTimezone($this->base->getTimezone());

        $startDate = new \DateTime("now", $this->base->getTZO()); $startDate->setTimestamp($start);
        $endDate = new \DateTime("now", $this->base->getTZO()); $endDate->setTimestamp($end);

        $to->setTimezone($this->base->getTimezone());
        $to->setPoint(0, $this->base->getAt(0));

        $date = new \DateTime($startDate->format('Y-m-d 00:00:00'), $tz = $this->base->getTZO());

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
                $to->setPoint($date->format('Y-m-d H:i:s'), $p['status']);
            }

            // Move to next day
            $date->setTime(0,0);
            $date = $date->add(new \DateInterval("P1D"));
        }

        return $to;

    }

}
