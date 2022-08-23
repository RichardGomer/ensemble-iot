<?php

namespace Ensemble\Schedule;

class DailyScheduler extends SchedulerDevice {

    /**
     * The base schedule sets the daily schedule, as if it was a template
     *
     * This scheduler is timezone aware; it uses the timezone that's set on the
     * base schedule for the generated schedules.
     *
     * CLOCK TIME is maintained in the generated schedule, so events will happen
     * at the same LOCAL time each day, even if the timezone changes due to
     * daylight savings time.
     *
     * To avoid compensating for DST, pass in a base schedule that uses a
     * non-geographic timezone; e.g. "UTC" instead of "Europe/London"
     */
    public function __construct($name, $device, $field, $baseschedule) {
        parent::__construct($name, $device, $field);

        $this->base = $baseschedule;
    }

    /**
     * $date is the date that we want to generate the schedule for, in ISO8601
     */
    public function reschedule($date=false) {

        $date = new \DateTime($date === false ? "now" : $date, $tz = new \DateTimeZone($this->base->getTimezone()));

        $ns = new Schedule();
        $ns->setTimezone($this->base->getTimezone());
        $ns->setPoint(0, 'OFF');

        for($i = 0; $i <= 1; $i++) {
            foreach($this->base->getAllPeriods() as $p) {
                // Get the clock time from the base schedule
                $time = new \DateTime("now", $tz); $time->setTimestamp($p['start']); // There's no constructor for this!
                $clocktime_h = $time->format('H');
                $clocktime_m = $time->format('i');
                $clocktime_s = $time->format('s');

                // Set the clock time on the new schedule to the same
                $date->setTime($clocktime_h, $clocktime_m, $clocktime_s);

                // Now set the absolute time on the output schedule
                $ns->setPoint($date->format('Y-m-d H:i:s'), $p['status']);
            }

            // Move to next day
            $date->setTime(0,0);
            $date = $date->add(new \DateInterval("P1D"));
        }


        $this->log("Generated Schedule:\n".$ns->prettyPrint(true));

        return $ns;
    }


}
