<?php

/**
 * The MQTTDevice Module is for interfacing with Tasmota smart sockets using MQTT.
 * Tasmota can be used on hardware like Sonoff and Tuya WiFi switches.
 *
 * State and telemetry data are stored in a SubscriptionStore so changes can be
 * detected.
 *
 * This is implemented on top of the AsyncModule, so override getRoutine() to
 * implement custom control logic.
 */

namespace Ensemble\MQTT;
use Ensemble\Async as Async;

abstract class Tasmota extends Async\Device {

    private $t_interval = 15; // Telemetry interval

    protected $topic, $topic_command, $mqtt;

    const MQTT_COMMAND = 'do_rcv_mqtt';

    public function __construct($name, Bridge $bridge, $deviceName) {

        $this->name = $name;

        $this->deviceName = $deviceName;

        $this->topic_command = "cmnd/$deviceName/";
        $this->topic = "+/$deviceName/+";

        $this->status = new \Ensemble\KeyValue\SubscriptionStore();

        $this->mqtt = $bridge;
        $this->mqtt->subscribeBasic($this->topic, $this->name, self::MQTT_COMMAND);

        $this->send($this->topic_command."teleperiod", $this->t_interval);
    }

    public function setTeleInterval($t) {
        $this->t_interval = max((int) $t, 5);
        $this->send($this->topic_command."teleperiod", $this->t_interval);
    }

    public function getRoutine() {
        return new Async\NullRoutine();
    }

    public function action(\Ensemble\Command $c, \Ensemble\CommandBroker $b) {
        if($c->getAction() == self::MQTT_COMMAND) { // Handle MQTT updates immediately
            $this->processMQTT($c->getArg('mqtt_topic'), $c->getArg('mqtt_payload'));
        } else { // Else pop them into the heap for the async routine to pick up
            parent::action($c, $b);
        }
    }

    /**
     * Receive and process MQTT messages
     */
    protected function processMQTT($topic, $message) {

        // Split topic into components
        preg_match('@([a-z]{4})/(.+)/(.+)@i', $topic, $matches);

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

    public function getStatus() {
        return $this->status;
    }

    public function isOn() {
        $state = $this->getStatus()->get("STATE.POWER");
        $on = $state === 'ON';
        return $on;
    }

    protected function send($topic, $message) {
        $this->mqtt->getClient()->publish($topic, $message, 0);
    }
}
