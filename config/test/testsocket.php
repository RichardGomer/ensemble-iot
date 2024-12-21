<?php

// Mostly for testing

namespace Ensemble;
use Ensemble\MQTT\Client as MQTTClient;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Create a context device to broker schedules
$conf['devices'][] = $ctx = new Device\ContextDevice('test.schedules');

/**
* Create some schedules
*/

// Daily offpeak
$doffpeak = new Schedule\Schedule();
$doffpeak->setPoint(0, 'ON');
$sd_doffpeak = new Schedule\DailyScheduler('test.scheduler', 'test.schedules', 'testschedule', $doffpeak);
$conf['devices'][] = $sd_doffpeak;


$client = new MQTTClient('10.0.0.8', 1883);
$conf['devices'][] = $bridge = new MQTT\Bridge("mqttbridge", new MQTT\Client('10.0.0.8', '1883'));

$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("testsocket", $bridge, new Device\ContextPointer('energy.schedules', 'allday'), "x");


class SocketTestDevice extends Device\BasicDevice {

    public function __construct(Device\Socket\Socket $socket) {
        $this->socket = $socket;
        $this->name = '*sockettest';
    }


    public function getPollInterval() {
        return 30;
    }

    public function poll(\Ensemble\CommandBroker $b) {

        $state = $this->socket->getStatus()->get('STATE.POWER');
        $current = $this->socket->getStatus()->get('SENSOR.ENERGY.POWER');

        echo "        State: $state
        Current: $current\n";
    }
}

$conf['devices'][] = new SocketTestDevice($socket);
