<?php

namespace Ensemble\Schedule;
use Ensemble\Async as Async;

/**
 * A device for creating a schedule of Octopus Agile rates
 */
class OctopusTariffDevice extends SchedulerDevice {
    public function __construct($name, $device, $field, Octopus $client) {
        parent::__construct($name);
        $this->client = $client;
        $this->setRescheduleInterval(1800);
        $this->setContext($device, $field, self::MODE_SERIES);
    }

    public function reschedule() {
        return $this->client->getTariffSchedule();
    }
}
