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

        // Generate for today and tomorrow
        for($n = 0; $n < 96; $n++) {
          $cost = $n % 48 >= 1 && $n % 48 <= 8 ? 5.00 : 15.59;
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
