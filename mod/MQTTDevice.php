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

namespace Ensemble\Device;
use Ensemble\MQTT\Client as MQTTClient;
use Ensemble\Async as Async;

abstract class MQTTDevice extends Async\Device {

    private $t_interval = 15; // Telemetry interval

    protected $topic, $topic_command, $mqttsub, $mqtt;

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

    public function setTeleInterval($t) {
        $this->t_interval = max((int) $t, 5);
        $this->send($this->topic_command."teleperiod", $this->t_interval);
    }

    public function poll(\Ensemble\CommandBroker $b) {
        $this->pollMQTT(); // We want to process outstanding MQTT data on each poll
        parent::poll($b);
    }

    // Override this method to add custom logic
    public function getRoutine() {
        return new Async\NullRoutine();
    }

    protected function send($topic, $message) {
        $this->mqtt->publish($topic, $message, 0);
        usleep(500000); // Wait 1/2 second
        $this->pollMQTT(); // Poll for outstanding messages
    }

    /**
     * Receive and process MQTT messages
     */
    public function pollMQTT() {

        foreach($this->mqttsub->getMessages() as $m) {

            //$this->log($m['topic']." ".$m['message']);

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
}
