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
class LightSwitch extends \Ensemble\Device\MQTTDevice {

    public function __construct($name, $client, $deviceName) {
        parent::__construct($name, $client, $deviceName);
        $this->setTeleInterval(5);
    }

}
