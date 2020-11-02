<?php

// Mostly for testing

namespace Ensemble;
use Ensemble\MQTT\Client as MQTTClient;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Create a context device to broker schedules
$conf['devices'][] = $ctx = new Device\ContextDevice('global.schedules');

/**
* Create some schedules
*/

// Daily offpeak
$doffpeak = new Schedule\Schedule();
$doffpeak->setPoint('00:00:00', 'OFF');
$doffpeak->setPoint('07:00:00', 'ON');
$doffpeak->setPoint('16:00:00', 'OFF');
$doffpeak->setPoint('19:00:00', 'ON');
$doffpeak->setPoint('22:00:00', 'OFF');
$sd_doffpeak = new Schedule\DailyScheduler('daytime.scheduler', 'global.schedules', 'daytimeoffpeak', $doffpeak);
$conf['devices'][] = $sd_doffpeak;


$client = new MQTTClient('10.0.0.8', 1883);

$conf['devices'][] = $socket = new Device\Socket\ScheduledSocket("socket-vent-office", $client, "socket5", 'global.schedules', 'daytimeoffpeak');


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
