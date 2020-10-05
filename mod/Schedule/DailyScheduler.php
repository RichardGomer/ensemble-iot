<?php

namespace Ensemble\Schedule;

class DailyScheduler extends SchedulerDevice {

    /**
     * The base schedule sets the daily schedule
     *
     * Times are made modulo 24 hours and then bumped
     *
     */
    public function __construct($name, $device, $field, $baseschedule) {
        parent::__construct($name, $device, $field);

        $this->basesched = $this->normaliseSchedule($baseschedule);
    }

    public function reschedule() {

        // Get the timestamp at the start of today
        $todaystart = strtotime('today midnight');
        $tomorrowstart = strtotime('tomorrow midnight');

        $ns = new Schedule();
        $ns->setPoint(0, 'OFF');

        // Copy each point of the base schedule into the new schedule
        $this->copyIntoWithOffset($this->basesched, $ns, $todaystart);
        $this->copyIntoWithOffset($this->basesched, $ns, $tomorrowstart);

        $this->log("Generated Schedule:\n".$ns->prettyPrint());

        return $ns;
    }

    /**
     * Convert a schedule so that all times are relative to midnight
     * (i.e. date information is discarded, all timestamps become times on
     *  1970-01-01)
     */
    protected function normaliseSchedule(Schedule $in) {
        $periods = $in->getChangePoints();

        $out = new Schedule();

        foreach($periods as $time) {
            $midnight = strtotime(date('Y-m-d 00:00:00', $time));
            $timepastmidnight = $time - $midnight;
            $out->setPoint($timepastmidnight, $in->getAt($time));
        }

        return $out;
    }

    protected function copyIntoWithOffset(Schedule $base, Schedule $new, $offset) {
        $periods = $base->getChangePoints();

        foreach($periods as $time) {
            $status = $base->getAt($time);
            $new->setPoint($time + $offset, $status);
        }
    }

}
