<?php

/**
 * The MQTT Bridge generates ensemble commands from MQTT events
 *
 * Rather than having MQTT devices check for events when they're polled,
 * this device creates ensemble commands that go into the global queue.
 * The latency of responding to MQTT events is thus reduced.
 */

namespace Ensemble\MQTT;

use Ensemble\Module;
use Ensemble\Device\BasicDevice;
use Ensemble\Command;
use Ensemble\CommandBroker;

class Bridge extends BasicDevice {

    private $client;

    public function __construct($name, Client $client) {
        $this->name = $name;

        $this->client = $client;
    }

    public function getClient() {
        return $this->client;
    }

    public function getPollInterval() {
        return 0.1;
    }

    private $subs = array();

    public function poll(CommandBroker $broker) {
        foreach($this->subs as $s) {
            $client = $s['client'];
            $trans = $s['translator'];

            $messages = $client->getMessages();
            foreach($messages as $m) {
                $cmd = $trans->convert($this, $m['topic'], $m['message']);
                $broker->send($cmd);
            }
        }
    }

    /**
     * Subscribe to a topic. Each received message is passed to the given translator
     * for conversion into a command; and then sent
     */
    public function subscribe($topic, Translator $trans) {
        $this->subs[] = array('client' => $this->client->subscribe($topic), 'translator'=>$trans);
    }

    /**
     *
     */
    public function subscribeBasic($topic, $destination, $commandName, $payloadField="mqtt_payload") {
        $this->subscribe($topic, new BasicTranslator($destination, $commandName, $payloadField));
    }

}

interface Translator {

    /**
     * Convert the given string
     */
    public function convert(Bridge $bridge, $topic, $payload);
}


class BasicTranslator implements Translator {

    public function __construct($destination, $commandName, $payloadField) {
        $this->destination = $destination;
        $this->commandName = $commandName;
        $this->payloadField = $payloadField;
    }

    public function convert(Bridge $bridge, $topic, $payload) {
        $args = array($this->payloadField => $payload, 'mqtt_topic' => $topic);
        $cmd = Command::create($bridge, $this->destination, $this->commandName, $args);
        return $cmd;
    }
}
