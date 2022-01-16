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
class LightSwitch extends \Ensemble\MQTT\Tasmota {

    public function __construct($name, $bridge, $deviceName) {
        parent::__construct($name, $bridge, $deviceName);
        $this->setTeleInterval(5);
    }

}
