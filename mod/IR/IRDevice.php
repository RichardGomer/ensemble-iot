<?php

namespace Ensemble\Device\IR;

abstract class IRDevice extends \Ensemble\Device\MQTTDevice {

    public function __construct($name, \Ensemble\MQTT\Client $client, $deviceName) {

        parent::__construct($name, $client, $deviceName);
    }

    // Send an IR command. $ircmd should be a tasmota IR command string  like:
    // {"Protocol":"SYMPHONY","Bits":12,"Data":"0xDA0","DataLSB":"0xB005","Repeat":0}
    public function sendCommand($ircmd) {
        $this->send("cmnd/{$this->deviceName}/IRSend", $ircmd);
        usleep(100000);
    }

    // TODO: Could implement receiving commands; but don't need it yet!
    // Extend (and maybe refactor...) MQTTDevice::pollMQTT
}
