<?php

// Mostly for testing

namespace Ensemble;
use Ensemble\MQTT\Client as MQTTClient;


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
        $socket->off();
    }


    public function getPollInterval() {
        return 30;
    }

    public function poll(\Ensemble\CommandBroker $b) {

        //var_dump($this->socket->getStatus()->getAll());

        $state = $this->socket->getStatus()->get('STATE.POWER');
        $current = $this->socket->getStatus()->get('SENSOR.ENERGY.POWER');

        echo "
        State: $state
        Current: $current
        ";

        if(true || rand(1,10) < 5) {
            if($this->socket->isOn()) {
                $this->socket->off();
            } else {
                $this->socket->on();
            }
        }
    }
}

$conf['devices'][] = new SocketTestDevice($socket);
