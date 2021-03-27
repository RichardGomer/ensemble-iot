<?php

/**
 * Work with energy tariff data
 * Periods either have price data, or are set false
 * Combining Tariff Schedules allows complex schedules to be built
 * e.g./ find the cheapest two hours between midnight and 6am
 *
 *   (new TariffSchedule($priceData))->trim('00:00', '06:00')->cheapest(120);
 *
 */
namespace Ensemble\Schedule;

class TariffSchedule extends Schedule {

    public function __construct(Schedule $tariff = null) {
        parent::__construct(false);
        $this->setPoint(0, false);

        // Import price data
        if($tariff instanceof Schedule) {
            foreach($tariff->getAllPeriods() as $p) {
                $this->setPoint($p['start'], $p['status']);
            }
        }
    }

    private function newTS() {
        $c = __CLASS__;
        return new $c();
    }

    // Extract a period between the start and end times
    // Start is assumed to be today; end can be today or tomorrow
    // Times must be in format hh:mm
    public function between($start, $end) {
        $tp = '/[0-9]{2}:[0-9]{2}/';
        if(!preg_match($tp, $start) || !preg_match($tp, $end)) {
            throw new \Exception("Times passed to TariffSchedule::between() must be in format hh:mm");
        }

        $tstart = strtotime($start);
        $tend = strtotime($end);

        if($tend <= $tstart) {
            $tend = strtotime("tomorrow {$end}");
        }

        echo date("Y-m-d H:i:s", $tstart)." - ".date('Y-m-d H:i:s', $tend)."\n";

        $out = $this->newTS();

        $out->setPoint($tstart, $this->getAt($tstart));

        foreach($this->getChangePoints() as $cp) {
            if($cp > $tstart && $cp < $tend) {
                $out->setPoint($cp, $this->getAt($cp));
            }
        }

        $out->setPoint($tend, $this->getAt($tend));

        return $out;
    }

    // Get periods where the price is less or equal to than $max
    public function lessThan($max) {
        $out = $this->newTS();
        return $this->translate(function($value) use ($max) {
            return $value > $max ? false : $value;
        }, $out);
    }

    // Get the cheapest $mins minutes in the schedule
    public function cheapest($mins) {
        $values = array_values(array_unique($this->toArray()));
        sort($values);

        $out = $this->newTS();

        $totalmins = 0;
        foreach($values as $v) { // Iterate through values, cheapest first
            foreach($this->getAllPeriods() as $p) {
                if($totalmins >= $mins) return $out;

                if($p['status'] === false) {
                    continue; // Skip excluded periods
                }

                if($p['status'] == $v) {
                    $out->setPeriod($p['start'], $p['end'], $p['status']);
                    $totalmins += ($p['end'] - $p['start']) / 60;
                    //echo "setPeriod {$p['start']} - {$p['end']} = {$p['status']} => $totalmins minutes\n";
                }
            }
        }

        return $out;
    }

    // Convert the schedule into an on/off schedule
    public function getOnSchedule() {
        $os = new OnSchedule();
        $this->translate(function($v) {
            return $v === false ? 'OFF' : 'ON';
        }, $os);
        return $os;
    }


}
