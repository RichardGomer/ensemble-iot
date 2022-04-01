<?php

namespace Ensemble;
use \Ensemble\MQTT\Client as MQTTClient;
use \Ensemble\Schedule as Schedule;
use \Ensemble\Device\IR as IR;


$conf['devices'][] = $ctx = new Device\ContextDevice('test.context');
$ctx->update('heater1-temp-setting', 10);

// Create a socket to be controlled and bind it to the schedule in the broker
$client = new MQTTClient('10.0.0.8', 1883);
$conf['devices'][] = $h = new IR\NettaHeater("ir1-heater", $client, "ir1", 'test.context', 'heater1-temp-setting');

class TestHeater extends Device\BasicDevice {
    public function __construct(IR\NettaHeater $heater) {
        $this->heater = $heater;
        $this->name = "*testHeater";
    }

    public function getPollInterval() {
        return 10;
    }

    public function poll(CommandBroker $b) {
        $heater = $this->heater;

        if(!$this->heater->isReady()) {
            echo "Heater isn't ready";
            return false;
        }

        $diff = rand(0,7);
        $diff = rand(0,1) ? $diff : $diff * -1;
        $heater->setTemperature($heater->getTemperature() + $diff);
    }
}

$conf['devices'][] = new TestHeater($h);
