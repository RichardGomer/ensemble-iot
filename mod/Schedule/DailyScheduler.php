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

        if($date === false) {
            $date = time();
        } else {
            $date = strtotime($date);
        }

        $p = new DailyProjector($this->base);
        $ns = $p->project($date, $date);


        $this->log("Generated Schedule:\n".$ns->prettyPrint(true));

        return $ns;
    }


}
