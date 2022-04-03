<?php

/**
 * The Socket Module is for interfacing with Tasmota smart sockets using MQTT.
 * Tasmota can be used on hardware like Sonoff and Tuya WiFi switches.
 *
 * State and telemetry data are stored in a SubscriptionStore so changes can be
 * detected.
 *
 * This is implemented on top of the AsyncModule, so override getRoutine() to
 * implement custom control logic.
 */

namespace Ensemble\Device\Socket;
use Ensemble\MQTT;
use Ensemble\Async;

class Socket extends MQTT\Tasmota {

    private $t_interval = 5; // Telemetry interval

    public function __construct($name, MQTT\Bridge $bridge, $deviceName, $powerNum="") {

        parent::__construct($name, $bridge, $deviceName);

        $this->powerNum = $powerNum; // Multi-channel controllers have e.g. POWER1, POWER2 ...

        $this->setTeleInterval(5);

        // Set up a subscription to sync internal power state with that received from the device
        $dev = $this;
        $this->status->sub("STATE.POWER{$powerNum}", function($k, $v) use ($dev) {
            $dev->on = $v == 'ON';
        });
    }

    private $on = null; // avoid sending unnecessary state changes

    public function on() {
        if($this->on !== true)
            $this->send($this->topic_command.'POWER'.$this->powerNum, 'ON');

        $this->on = true;
    }

    public function off() {
        if($this->on !== false)
            $this->send($this->topic_command.'POWER'.$this->powerNum, 'OFF');

        $this->on = false;
    }

    /**
     * Get a Current sensor for the socket
     */
    private $meter = false;
    public function getPowerMeter() {

        if(!$this->meter) {
            $this->meter = new PowerMeter($this->name.'_POWER', $this, 'SENSOR.ENERGY.POWER');
        }

        return $this->meter;
    }
}

// A sensor device that reads current information from an MQTT socket
class PowerMeter extends \Ensemble\Device\SensorDevice {
    public function __construct($name, Socket $socket, $key) {
        $this->name = $name;
        $this->socket = $socket;
        $this->key = $key;
    }

    public function getPollInterval() {
        return 30;
    }

    public function measure() {
        try {
            $power = $this->socket->getStatus()->get($this->key);
        }
        catch(\Ensemble\KeyValue\KeyNotSetException $e) { // This is a legit case for an offline device, so don't log it as an error
            $power = 0;
        }
        catch(\Exception $e) {
            trigger_error($e->getMessage(), E_USER_NOTICE);
            $power = 0;
        }

        return $power;
    }
}
