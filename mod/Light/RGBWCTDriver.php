<?php

/**
 * Drive an RGBWCT light using a schedule
 *
 * Extends the basic schedule driver, but parses the strings into light settings
 * and adds interpolation between values
 * Schedule should contain values in the format:
 * R,G,B [dim%=100] or        (for RGB value plus brightness)
 * CT [dim%=100] or           (for colour temperature and brightness)
 * auto [dim%=100]            (for auto temperature based on sunset, manual brightness)
 * Intermediate values are scaled linearly
 */

namespace Ensemble\Device\Light;
use Ensemble\Schedule;
use Ensemble\Device;

class RGBWCTDriver extends Schedule\Driver {

    public function __construct(RGBWCT $target, Device\ContextPointer $ctxptr) {
        parent::__construct($target, function($light, $currentStatus, $currentTime, $nextStatus, $nextTime) {
            $this->applySchedule($light, $currentStatus, $currentTime, $nextStatus, $nextTime);
        }, $ctxptr);
    }

    public function applySchedule($light, $currentStatus, $currentTime, $nextStatus, $nextTime) {
        $current = $this->parseStatus($currentStatus);
        $next = $this->parseStatus($nextStatus);


        if($next !== false && $current['mode'] == $next['mode']) { // If modes match, interpolate values
            //$this->log("Interpolate from $currentStatus @ $currentTime to $nextStatus @ $nextTime\n");

            $bpc = $this->scale($current['%'], $next['%'], $currentTime, $nextTime);
            //$this->log(" => ".$bpc."%");

            if($current['mode'] == 'rgb') { // RGB; interpolate each channel plus brightness
                $light->setRGB(
                    $this->scale($current['r'], $next['r'], $currentTime, $nextTime),
                    $this->scale($current['g'], $next['g'], $currentTime, $nextTime),
                    $this->scale($current['b'], $next['b'], $currentTime, $nextTime)
                );
                $light->setBrightness($bpc);
            } elseif($current['mode'] == 'ct') { // Manual colour temperature; scale temp and brightness
                $light->setCT($this->getAutoCT());
                $light->setBrightness($bpc);
            } elseif($current['mode'] == 'auto') { // Auto CT mode, only scale brightness
                $light->setCT($this->getAutoCT());
                $light->setBrightness($bpc);
            } else {
                $light->setRGB(255,50,50); // Error!
                $light->setBrightness(50);
            }
        } else { // If modes don't match, just apply the current one, because we can't interpolate
            if($current['mode'] == 'rgb') {
                $light->setRGB($current['r'], $current['g'], $current['b']);
                $light->setBrightness($current['%']);
            } elseif($current['mode'] == 'ct') {
                $light->setCT($current['ct']);
                $light->setBrightness($current['%']);
            } elseif($current['mode'] == 'auto') {
                $light->setCT($this->getAutoCT());
                $light->setBrightness($current['%']);
            } else {
                $light->setRGB(255,50,50); // Error!
                $light->setBrightness(50);
            }
        }
    }


    protected function parseStatus($s) {

        if(preg_match('/^([0-9]{1,3}),([0-9]{1,3}),([0-9]{1,3})( [0-9]{1,3})?/', $s, $matches)) {
            return array(
                'mode'=>'rgb',
                'r'=>$matches[1],
                'g'=>$matches[2],
                'b'=>$matches[3],
                '%'=>array_key_exists(4, $matches) ? (int) $matches[4] : 100
            );
        } elseif (preg_match('/^([0-9]{1,3})( [0-9]{1,3})?/', $s, $matches)) {
            return array(
                'mode'=>'ct',
                'ct'=>$matches[1],
                '%'=>array_key_exists(2, $matches) ? (int) $matches[2] : 100
            );
        } elseif (preg_match('/^auto( [0-9]{1,3})?/i', $s, $matches)) {
            return array(
                'mode'=>'auto',
                '%'=>array_key_exists(1, $matches) ? (int) $matches[1] : 100
            );
        }

        return false;
    }

    /**
     * Get a colour temperature based on time of day
     */
    protected function getAutoCT() {
        $maxCT = 500; // Max (Warmest) CT
        $minCT = 153; // Min (coldest) CT

        $lat = 50.928677;
        $lng = -1.336661;

        $now = time();

        $sunset_start = date_sunset($now, SUNFUNCS_RET_TIMESTAMP, $lat, $lng, 86);
        $sunset_end = date_sunset($now, SUNFUNCS_RET_TIMESTAMP, $lat, $lng, 108);

        $sunrise_start = date_sunrise($now, SUNFUNCS_RET_TIMESTAMP, $lat, $lng, 96);
        $sunrise_end = date_sunrise($now, SUNFUNCS_RET_TIMESTAMP, $lat, $lng, 89);

        if($now >= $sunset_start) { // Sunset period (and afterwards)
            $ct = $this->scale($minCT, $maxCT, $sunset_start, $sunset_end, time());
        } elseif ($now >= $sunrise_start) { // Sunrise period (and afterwards)
            $ct = $this->scale($maxCT, $minCT, $sunrise_start, $sunrise_end, time());
        } else { // Pre-sunrise i.e. night time
            $ct = $maxCT;
        }

        return $ct;
    }

    protected function scale($from, $to, $start, $stop, $now=false) {
        if($now === false) {
            $now = time();
        }

        if($from == $to) {
            return $to;
        }

        if($now <= $start) {
            return $from;
        }

        if($now >= $stop) {
            return $to;
        }

        $period = $stop - $start;
        $fraction = ($now - $start) / $period;
        $range = $to - $from;

        return $range * $fraction + $from;
    }
}
