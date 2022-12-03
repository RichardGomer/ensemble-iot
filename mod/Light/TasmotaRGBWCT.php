<?php

/**
 * Tasmota RGBWCT light support
 */

namespace Ensemble\Device\Light;
use Ensemble\MQTT;
use Ensemble\Async;
use Ensemble\Schedule;

/**
 * Controls an RGBWCT Tasmota device using a schedule. Schedule must be stored
 * in a context device and specified using the $context_device $context_field
 * parameters.
 *
 * Check the docs below for information about the schedule
 */
class TasmotaRGBWCT extends MQTT\Tasmota implements RGBWCT {

    private $t_interval = 5; // Telemetry interval
    private $sched_polltime = 120; // Poll for schedule every two minutes
    protected $schedule = false;

    public function __construct($name, MQTT\Bridge $bridge, $deviceName) {

        parent::__construct($name, $bridge, $deviceName, false);

        $this->send($this->topic_command.'SetOption20', '1'); // Allow colours etc to be changed without turning the light on
        $this->send($this->topic_command.'Fade', '1');
        $this->send($this->topic_command.'Speed', '3');

        $this->setTeleInterval(30);
    }

    public function getPollInterval() {
        return 0;
    }

    public function on() {
        $this->send($this->topic_command.'POWER', 'ON');
    }

    public function off() {
        $this->send($this->topic_command.'POWER', 'OFF');
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


}
