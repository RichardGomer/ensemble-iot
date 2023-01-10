<?php

/**
 * This Module is for interfacing with Tasmota smart sockets using MQTT.
 * Tasmota can be used on hardware like Sonoff and Tuya WiFi switches.
 *
 * State and telemetry data are stored in a SubscriptionStore so changes can be
 * detected.
 *
 * This is implemented on top of the AsyncModule, so override getRoutine() to
 * implement custom control logic.
 *
 * MQTT messages are received via an MQTT bridge, so one of those needs to be
 * set up!
 */

namespace Ensemble\MQTT;
use Ensemble\Async as Async;

use Ensemble\Device\Subscription as Subscription;

abstract class Tasmota extends Async\Device {

    use Subscription; // Allow command subscription by other devices

    private $t_interval = 15; // Telemetry interval

    protected $topic, $topic_command, $mqtt, $status;

    const MQTT_COMMAND = 'do_rcv_mqtt';

    private $deviceName;
    public function __construct($name, Bridge $bridge, $deviceName, $listen=true) {

        $this->name = $name;

        $this->deviceName = $deviceName;

        $this->topic_command = "cmnd/$deviceName/";
        $this->topic = "+/$deviceName/+";

        $this->status = new \Ensemble\KeyValue\SubscriptionStore();

        $this->mqtt = $bridge;

        if($listen)
            $this->mqtt->subscribeBasic($this->topic, $this->name, self::MQTT_COMMAND);

        // Send some tasmota configuration (as retained messages, in case devices are not currently subscribed)
        $this->send($this->topic_command."SetOption56", "On", true); // Select strongest AP on start
        $this->send($this->topic_command."SetOption57", "Off", true); // Disable select strongest AP regularly; do this manually if required
        $this->send($this->topic_command."teleperiod", $this->t_interval, true);
    }

    public function setTeleInterval($t) {
        $this->t_interval = max((int) $t, 5);
        $this->send($this->topic_command."teleperiod", $this->t_interval);
    }

    public function getRoutine() {
        return new Async\NullRoutine();
    }

    /**
     * Receive MQTT messages, otherwise store commands like a normal Async device does
     */
    public function action(\Ensemble\Command $c, \Ensemble\CommandBroker $b) {
        if($c->getAction() == self::MQTT_COMMAND) { // Handle MQTT updates immediately
            $this->processMQTT($c->getArg('mqtt_topic'), $c->getArg('mqtt_payload'));
            $this->poll($b); // Resume the routine
        } else { // Else pop them into the heap for the async routine to pick up and trigger subscriptions
            parent::action($c, $b);
            $this->pubAction($c, $b);
        }
    }

    /**
     * Receive and process MQTT messages
     */
    protected function processMQTT($topic, $message) {

        // Split topic into components
        if(!preg_match('@([a-z]{4})/(.+)/(.+)@i', $topic, $matches)) {
            return; // Skip messages that don't have a correctly formatted topic
        }

        $type = $matches[1];
        $device = $matches[2];
        $field = $matches[3];

        if($device !== $this->deviceName) {
            echo "Message is not for us. '{$device}' != '{$this->deviceName}' ";
            return;
        }

        switch($type) {
            case 'stat': // stat contains single-field state updates
                $this->status->set("STATE.$field", $message);
                break;
            case 'tele': // Telemetry is a JSON message
                $json = json_decode($message, true);

                if(!$json) {
                    $this->status->set($field, $message);
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
        try {
            $state = $this->getStatus()->get("STATE.POWER");
            $on = $state === 'ON';
            return $on;
        }
        catch(\Exception $e) {
            return false;
        }
    }

    protected function send($topic, $message) {
        $this->mqtt->getClient()->publish($topic, $message, 0);
    }
}
