<?php

/**
 * Extend Schedule for easily working with energy
 * Periods must start and end at hh:00 or hh:30 and must be 30 minutes long
 * Numbers represent ENERGY (ie KWh) and not power (ie KW)
 */

namespace Ensemble\Device\EnergyPlan;

use Ensemble\Schedule\Schedule;

class EnergySchedule extends Schedule {

    public function add(EnergySchedule $s) {
        return self::reduce([$this, $s], function ($values) {
            return $values[0] + $values[1];
        }, new EnergySchedule());
    }

    public function subtract(EnergySchedule $s) {
        return self::reduce([$this, $s], function ($values) {
            return $values[0] - $values[1];
        }, new EnergySchedule());
    }


    // Disable tidying, which can change the length of periods
    protected function tidy() {
        
    }


    public function setPeriod($from, $to, $value) {

        $t_from = $this->normaliseTime($from);
        $t_to = $this->normaliseTime($to);

        if($t_from % 1800 !== 0) {
            throw new \Exception("In an EnergySchedule, periods must align with 30-minute intervals");
        }

        if(($t_to - $t_from) !== 1800) {
            $d = $t_to - $t_from;
            throw new \Exception("In an EnergySchedule, periods must be 30 minutes long; $t_from -> $t_to = $d");
        }

        parent::setPeriod($from, $to, $value);
    }

    /**
     * Set multiple periods at once, between $from and $to
     */
    public function setPeriods($t_from, $t_to, $value) {
        $t_from = $this->normaliseTime($t_from);
        $t_to = $this->normaliseTime($t_to);

        $t = $t_from;
        while($t < $t_to) {
            $this->setPeriod($t, $t + 1800, $value);
            $t += 1800;
        }
    }
}
