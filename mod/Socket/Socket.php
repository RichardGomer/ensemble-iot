<?php

/**
 * The Socket Module is for interfacing with Tasmota smart sockets using MQTT
 * Tasmota can be used on hardware like Sonoff and Tuya WiFi switches
 *
 * State and telemetry data are stored in a SubscriptionStore so changes can be
 * detected
 */

namespace Ensemble\Device\Socket;
use Ensemble\MQTT\Client as MQTTClient;


class Socket extends \Ensemble\Device\BasicDevice {

    private $t_interval = 10; // Telemetry interval

    public function __construct($name, MQTTClient $client, $deviceName) {

        $this->name = $name;

        $this->deviceName = $deviceName;

        $this->topic_command = "cmnd/$deviceName/";
        $this->topic = "+/$deviceName/+";
        $this->mqttsub = $client->subscribe($this->topic);

        $this->status = new \Ensemble\KeyValue\SubscriptionStore();

        $this->mqtt = $client;

        $this->send($this->topic_command."teleperiod", $this->t_interval);
    }

    public function getPollInterval() {
        return $this->t_interval;
    }

    public function on() {
        $this->send($this->topic_command.'POWER', 'ON');
    }

    public function off() {
        $this->send($this->topic_command.'POWER', 'OFF');
    }

    protected function send($topic, $message) {
        $this->mqtt->publish($topic, $message, 0);
        usleep(500000); // Wait 1/2 second
        $this->pollMQTT(); // Poll for outstanding messages
    }

    public function poll(\Ensemble\CommandBroker $b) {
        $this->pollMQTT();
    }

    /**
     * Receive and process MQTT messages
     */
    protected function pollMQTT() {

        foreach($this->mqttsub->getMessages() as $m) {
            // Split topic into components
            preg_match('@([a-z]{4})/(.+)/(.+)@i', $m['topic'], $matches);

            $type = $matches[1];
            $device = $matches[2];
            $field = $matches[3];

            if($device !== $this->deviceName) {
                echo "Message is not for us. '{$device}' != '{$this->deviceName}' ";
                continue;
            }

            switch($type) {
                case 'stat': // stat contains single-field state updates
                    $this->status->set("STATE.$field", $m['message']);
                    break;
                case 'tele': // Telemetry is a JSON message
                    $json = json_decode($m['message'], true);

                    if(!$json) {
                        $this->status->set($field, $m['message']);
                    } else {
                        $this->status->setArray($json, array($field));
                    }

                    break;
            }
        }
    }

    public function getStatus() {
        return $this->status;
    }

    public function isOn() {
        $state = $this->getStatus()->get("STATE.POWER");
        $on = $state === 'ON';
        return $on;
    }

    /**
     * Get a Current sensor for the socket
     */
    public function getPowerMeter() {
        return new PowerMeter($this->name.'_POWER', $this, 'SENSOR.ENERGY.POWER');
    }

}

// A sensor device that reads current information from an MQTT socket
class PowerMeter extends \Ensemble\Device\SensorDevice {
    public function __construct($name, Socket $socket, $key) {
        $this->name = $name;
        $this->socket = $socket;
        $this->key = $key;
        $socket->getStatus()->sub($key, array($this, 'change'));
    }

    public function getPollInterval() {
        return 30;
    }

    public function change($key, $value) {
        echo "CURRENT $value\n";
    }

    public function measure() {
        try {
            $power = $this->socket->getStatus()->get($this->key);
        } catch(\Exception $e) {
            $power = 0;
        }

        return $power;
    }
}
