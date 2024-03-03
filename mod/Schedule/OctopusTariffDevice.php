<?php

namespace Ensemble\Schedule;
use Ensemble\Async as Async;

/**
 * A device for creating a schedule of Octopus Agile rates
 */
class OctopusTariffDevice extends SchedulerDevice {

    private Octopus $client;

    public function __construct($name, $device, $field, Octopus $client) {
        parent::__construct($name);
        $this->client = $client;
        $this->setRescheduleInterval(1800);
        $this->setContext($device, $field, self::MODE_SERIES);
    }

    public function reschedule() {
        $s = $this->client->getTariffSchedule();

        if($this->callback !== false) {
            echo "Tariff received, passing to callback\n";
            ($this->callback)(clone $s);
        } else {
            echo "Tariff received, no callback defined\n";
        }

        //echo "Tariff data\n".$s->prettyPrint();

        return $s;
    }

    private $callback = false;
    // Set a callback to run when the tariff is retrieved
    public function setCallback($cb) {
        $this->callback = $cb;
    }
}
