<?php

/**
 * Tasmota RGBWCT light support
 */

namespace Ensemble\Device\Light;
use Ensemble\MQTT\Client as MQTTClient;
use Ensemble\Async as Async;
use Ensemble\Schedule as Schedule;

/**
 * Controls an RGBWCT Tasmota device using a schedule. Schedule must be stored
 * in a context device and specified using the $context_device $context_field
 * parameters.
 *
 * Check the docs below for information about the schedule
 */
class RGBWCT extends \Ensemble\Device\MQTTDevice {

    private $t_interval = 5; // Telemetry interval
    private $sched_polltime = 120; // Poll for schedule every two minutes
    protected $schedule = false;

    public function __construct($name, MQTTClient $client, $deviceName, $context_device, $context_field) {

        parent::__construct($name, $client, $deviceName);

        $this->setTeleInterval(30);

        $this->context_device = $context_device;
        $this->context_field = $context_field;
    }

    public function getPollInterval() {
        return 10;
    }

    /**
     * The async routine checks for schedule updates and handles current state
     */
    public function getRoutine() {
        $light = $this;
        return new Async\Lambda(function() use ($light) {

            $start = time();

            $light->log("Begin routine");

            // 1: Get the schedule from the configured context device
            yield $light->getRefreshScheduleRoutine();

            if(!$this->schedule) {
                $light->log("Schedule is not set");
                return;
            }

            // 2: Do the schedule
            while(time() < $start + $this->sched_polltime) {

                $period = $light->schedule->getCurrentPeriod();

                $currentTime = array_keys($period)[0];
                $currentStatus = $period[$currentTime];

                $nextTime = array_keys($period)[1];
                $nextStatus = $period[$nextTime];

                $current = $this->parseStatus($currentStatus);
                $next = $this->parseStatus($nextStatus);

                //var_dump($current);
                //var_dump($next);

                if($next !== false && $current['mode'] == $next['mode']) { // If modes match, interpolate values
                    //$this->log("Interpolate from $currentStatus @ $currentTime to $nextStatus @ $nextTime\n");

                    $bpc = $light->scale($current['%'], $next['%'], $currentTime, $nextTime);
                    //$this->log(" => ".$bpc."%");

                    if($current['mode'] == 'rgb') { // RGB; interpolate each channel plus brightness
                        $this->setRGB(
                            $light->scale($current['r'], $next['r'], $currentTime, $nextTime),
                            $light->scale($current['g'], $next['g'], $currentTime, $nextTime),
                            $light->scale($current['b'], $next['b'], $currentTime, $nextTime)
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

                yield;
            }
        });
    }


    public function on() {
        $this->send($this->topic_command.'POWER', 'ON');
    }

    public function off() {
        $this->send($this->topic_command.'POWER', 'OFF');
    }

    /**
     * Get a colour temperature based on time of day
     */
    public function getAutoCT() {
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

    public function setCT($ct) {
        $this->send($this->topic_command.'CT', $ct);
    }

    public function setBrightness($pc) {
        $this->send($this->topic_command.'Dimmer', $pc);
    }

    public function setRGB($r, $g, $b) {
        $this->send($this->topic_command.'Fade', "1");
        $this->send($this->topic_command.'Speed', "20");
        $this->send($this->topic_command.'Color2', "$r,$g,$b");
        //$this->send($this->topic_command.'Fade', "0");
    }

    /**
     * A schedule can be set to control the light. Schedule should contain values in the format:
     * R,G,B [dim%=100] or        (for RGB value plus brightness)
     * CT [dim%=100] or           (for colour temperature and brightness)
     * auto [dim%=100]                (for auto temperature based on sunset, manual brightness)
     * Intermediate values are scaled linearly
     */
    public function setSchedule(Schedule\Schedule $s) {
        $this->schedule = $s;
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

    protected function getRefreshScheduleRoutine() {
        $light = $this;
        return new Async\TimeoutController(new Async\Lambda(function() use ($light) {
            $c = \Ensemble\Command::create($light, $light->context_device, 'getContext', array('field' => $light->context_field));
            $light->getBroker()->send($c);
            $rep = yield new Async\WaitForReply($light, $c);

            if($rep->isException()) {
                $light->log("Couldn't fetch schedule: ".$rep->getArg('message'), );
                return;
            }

            $json = $rep->getArg('values')[0]['value'];
            $light->setSchedule(Schedule\Schedule::fromJSON($json));
        }), 60);
    }
}
