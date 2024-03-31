<?php

namespace Ensemble\Schedule;

class DailyScheduler extends SchedulerDevice {

    private $lat, $lng, $base;

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
     * 
     * Meta-values can be used to create schedules that respond to sunrise/sunset etc.
     * See docs on DailyProjector for more. lat/lng must be set!
     */
    public function __construct($name, $device, $field, $baseschedule, $lat=0, $lng=0) {
        parent::__construct($name, $device, $field);

        $this->base = $baseschedule;
        $this->lat = $lat;
        $this->lng = $lng;
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

        $p = new DailyProjector($this->base, $this->lat, $this->lng);
        $ns = $p->project($date - 26 * 3600, $date + 26 * 3600);


        $this->log("Generated Schedule:\n".$ns->prettyPrint(true));

        return $ns;
    }


}
