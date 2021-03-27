<?php

namespace Ensemble\Schedule;
use Ensemble\Async as Async;

/**
 * A device for creating a schedule of Octopus Agile rates
 */
class OctopusGasUsageDevice extends SchedulerDevice {
    public function __construct($name, $device, $field, Octopus $client) {
        parent::__construct($name);
        $this->client = $client;
        $this->setRescheduleInterval(3600);
        $this->setContext($device, $field, self::MODE_SERIES);
    }

    public function reschedule() {
        $s = $this->client->getGasUsage();
        //var_dump($s);
        return $s;
    }
}

class OctopusElecUsageDevice extends OctopusGasUsageDevice {
    public function reschedule() {
        $s = $this->client->getElecUsage();
        return $s;
    }
}
