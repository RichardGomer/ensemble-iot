<?php

/**
 * Tasmota RGBWCT light support
 */

namespace Ensemble\Device\Light;
use Ensemble\MQTT;
use Ensemble\Async;
use Ensemble\Schedule;
use Ensemble\Command;
use Ensemble\KeyValue\SubscriptionStore;

/**
 * Control a Tasmota light switch
 */
class LightSwitch extends MQTT\Tasmota {

    private $powerNum;

    public function __construct($name, MQTT\Bridge $bridge, $deviceName, $powerNum="") {
        parent::__construct($name, $bridge, $deviceName);

        $this->powerNum = $powerNum; // Multi-channel controllers have e.g. POWER1, POWER2 ...

        // Send mock commands when power state changes; other devices can use subAction to
        // receive a copy of that command - as if it were an event :)
        $status = $this->getStatus();
        $device = $this;
        $lastState = false;
        $status->sub('STATE.POWER'.$this->powerNum, function($key, $value, $type) use ($device, &$lastState) {

            if($lastState !== $value) {
                $typeStr = $type == SubscriptionStore::UPTYPE_SOFT ? "SOFT" : "HARD";
                echo "{$device->getDeviceName()} {$key} {$typeStr} CHANGED TO $value\n";
                if($type == SubscriptionStore::UPTYPE_SOFT) return; // Skip soft power changes - these usually come from telemetry and can interfere with deliberate switching
                if($device->getBroker() !== false) {
                    $this->pubAction(Command::create($device, $device->getDeviceName(), 'POWER'.($value == 'ON' ? "ON" : "OFF")), $device->getBroker());
                    $lastState = $value;
                }
            }
        });
    }

    public function getPollInterval() {
        return 0;
    }

    public function on() {
        $this->send($this->topic_command.'POWER'.$this->powerNum, 'ON');
    }

    public function off() {
        $this->send($this->topic_command.'POWER'.$this->powerNum, 'OFF');
    }

    public function isOn() {
        $state = $this->getStatus()->get("STATE.POWER".$this->powerNum);
        $on = $state === 'ON';
        return $on;
    }

}
