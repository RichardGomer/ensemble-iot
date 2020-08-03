<?php

/**
 * Generate schedules using energy price data
 * Various strategies for different types of device
 */

namespace Ensemble\Schedule;

class EnergyScheduler {

    /**
     * Construct using a schedule that contains price data. The status of each
     * period should be the price per keh during that period
     */
    public function __construct(Schedule $prices) {

    }

    /**
     * Calculate the average energy price
     * $res is the resolution, in minutes; defaults to 30
     */
    public function averagePrice($res=30) {

    }

    /**
     * Get a schedule for appliances. Appliances are turned off during peak hours,
     * and go into an "opportunistic off" period for $prePeriod hours before that
     */
    public function getApplianceSchedule($prePeriod=2) {

    }

    /**
     * Get a schedule that indicates what times are peak
     * peak = more than 2x average cost
     */
    public function getPeakOffpeakSchedule() {

    }

}
