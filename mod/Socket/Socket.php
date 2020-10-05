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
use Ensemble\MQTT\Client as MQTTClient;
use Ensemble\Async as Async;

class Socket extends \Ensemble\Device\MQTTDevice {

    private $t_interval = 5; // Telemetry interval

    public function __construct($name, MQTTClient $client, $deviceName) {

        parent::__construct($name, $client, $deviceName);

        $this->setTeleInterval(5);
    }

    public function on() {
        $this->send($this->topic_command.'POWER', 'ON');
    }

    public function off() {
        $this->send($this->topic_command.'POWER', 'OFF');
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
            $this->socket->pollMQTT();
            $power = $this->socket->getStatus()->get($this->key);
        } catch(\Exception $e) {
            trigger_error($e->getMessage(), E_USER_NOTICE);
            $power = 0;
        }

        return $power;
    }
}
