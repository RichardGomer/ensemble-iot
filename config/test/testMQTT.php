<?php

/**
 * Test the MQTT bridge
 *
 */

namespace Ensemble;

use Ensemble\Module;
use Ensemble\Device\BasicDevice;
use Ensemble\Command;
use Ensemble\CommandBroker;

use Ensemble\MQTT;

class testDevice extends BasicDevice {
    public function __construct() {
        $this->name = "MQTT_Test";

        $this->registerAction("printMessage", $this, "printMessage");
    }

    public function printMessage(Command $c, CommandBroker $b) {
        echo "Received ".$c->getArg('mqtt_topic')." ".$c->getArg('mqtt_payload')."\n";
    }
}


$conf['devices'][] = new testDevice();

$conf['devices'][] = $bridge = new MQTT\Bridge("mqttbridge", new MQTT\Client('10.0.0.8', '1883'));

$bridge->subscribeBasic('#', 'MQTT_Test', 'printMessage');
