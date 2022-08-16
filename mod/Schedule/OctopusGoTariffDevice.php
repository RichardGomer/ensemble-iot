<?php

namespace Ensemble\Schedule;
use Ensemble\Async as Async;

/**
 * A device for creating a schedule of Octopus Go rates
 * Low cost period is 00:30 - 04:30
 * Schedule is generated for today and tomorrow
 */
class OctopusGoTariffDevice extends SchedulerDevice {
    public function __construct($name, $device, $field) {
        parent::__construct($name);
        $this->setRescheduleInterval(1800);
        $this->setContext($device, $field, self::MODE_SERIES);
    }

    public function reschedule() {
        $s = new TariffSchedule();

        $midnight = strtotime('today midnight');

        // Handle 2022 tariff change.
        // 39.84p! What a useless criminal bunch of cunts we have in charge of the country
        // Fuck the useless greedy lying tories
        if($midnight >= strtotime('2022-08-19 00:00:00')) {
            $drate = 39.84;
            $nrate = 7.5;
        } else {
            $drate = 15.59;
            $nrate = 5.00;
        }

        // Generate for today and tomorrow
        for($n = 0; $n < 96; $n++) {
          $cost = $n % 48 >= 1 && $n % 48 <= 8 ? $nrate : $drate;
          $time = $midnight + $n * 1800;
          $s->setPeriod($time, $time+1800, $cost, false);
        }

        echo $s->prettyPrint();

        return $s;
    }

    private $callback = false;
    // Set a callback to run when the tariff is retrieved
    public function setCallback($cb) {
        $this->callback = $cb;
    }
}
