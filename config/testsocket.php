<?php

// Mostly for testing

namespace Ensemble;
use Ensemble\MQTT\Client as MQTTClient;


$client = new MQTTClient('10.0.0.8', 1883);

$conf['devices'][] = $socket = new Device\Socket\ShowerSocket("socket", $client, "socket4");



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

//$conf['devices'][] = new SocketTestDevice($socket);
