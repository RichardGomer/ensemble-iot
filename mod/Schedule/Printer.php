<?php

namespace Ensemble\Schedule;


use Mmarica\DisplayTable;

/**
 * Pretty print one or more schedules
 *
 */
class Printer {

    public function __construct() {

    }

    private $scheds = [];
    public function addSchedule($name, Schedule $s) {
        $this->scheds[$name] = $s;
    }

    private $tformat = 'Y-m-d H:i:s T';
    public function setTimeFormat($tf) {
        $this->tformat = $tf;
    }

    public function print() {

        if(count($this->scheds) < 1) {
            return "No schedules added\n";
        }

        $points = [];
        foreach($this->scheds as $s) {
            $points = array_merge($points, $s->getChangePoints());
        }

        sort($points);
        $points = array_unique($points);

        $cols = array_merge(array(''), array_keys($this->scheds));

        $rows = [];
        foreach($points as $p) {
            $dt = new \DateTime("now", $this->scheds[array_keys($this->scheds)[0]]->getTZO());
            $dt->setTimestamp($p);
            $row = [$dt->format($this->tformat)];
            foreach($this->scheds as $s) {
                $row[] = $s->getAt($p);
            }
            $rows[] = $row;
        }

        return DisplayTable::create()
        ->headerRow($cols)
        ->dataRows($rows)
        ->toText()
        ->compactBorder()
        ->generate();
    }


}
