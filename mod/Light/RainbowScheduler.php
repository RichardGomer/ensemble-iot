<?php

namespace Ensemble\Device\Light;
use Ensemble\Schedule as Schedule;

/**
 * Generate rainbow schedules for lights!
 *
 * Schedules are generated for 2 hours in advance
 */
class RainbowScheduler extends Schedule\SchedulerDevice {

    public function __construct($name, $device, $field, $duration) {
        parent::__construct($name, $device, $field);

        $this->start = time();
        $this->duration = $duration;
    }

    public function reschedule() {

        $start = $this->start;

        $colours = array(
            '255,0,0 70%',
            '255,255,0 70%',
            '0,255,0 70%',
            '0,255,255 70%',
            '0,0,255 70%',
            '255,0,255 70%'
        );

        $schedstart = floor(time() / $this->duration) * $this->duration;

        //echo "Start at ".date('Y-m-d H:i:s', $schedstart)."\n";

        $step = round($this->duration / count($colours));

        $ns = new Schedule\Schedule();

        $now = time();

        $i = 0;
        $time = $schedstart;
        while($time <= $now + 7200) {
            $c = $colours[$i % count($colours)];
            $ns->setPoint($time, $c);
            $i++;
            $time += $step;
        }

        //echo "Generated rainbow schedule\n".$ns->prettyPrint();

        return $ns;
    }
}
