<?php

/**
 * A device that delivers messages from the remote queue.
 * It's only really a device for convenience, so it can use the polling
 * mechanism to run delivery regularly
 */

namespace Ensemble\Device;

class RemoteDeliveryDevice implements \Ensemble\Module {

    private $maxDelivery = 5; // Maximum number of messages to deliver in a single attempt
    private $pollInterval = 5; // Request to be polled every this many seconds
    private $timeout = 5; // HTTP request timeout in seconds
    private $tries = 3; // Number of tries before backing off of an endpoint
    private $backoff = 60; // Number of seconds to back off of an endpoint on failure
    private $defaultEndpoint = false;

    public function __construct(\Ensemble\Queue $queue, \Ensemble\Remote\DeviceMap $map) {
        $this->map = $map;
        $this->queue = $queue;
    }

    public function setDefaultEndpoint($ep) {
        $this->defaultEndpoint = $ep;
    }

    // Device name is hardcoded because it doesn't matter and shouldn't ever be
    // used for anything!
    public function getDeviceName() {
        return "_RemoteDelivery";
    }

    public function announce() {
        return false;
    }

    public function getPollInterval() {
        return $this->pollInterval;
    }

    public function poll(\Ensemble\CommandBroker $b) {
        $count = min($this->queue->count(), $this->maxDelivery);
        for($i = 0; $i < $count; $i++) {

            $cmd = $this->queue->shift();

            if(!$cmd instanceof \Ensemble\Command)
                continue;

            // Commands may expiry while in the remote queue, they need to be
            // discarded to prevent connectivity problems overwhelming the node
            if($cmd->isExpired()) {
                echo "TX ".$cmd." [DISCARDED, EXPIRED]\n";
                $b->send($cmd->reply(new \Ensemble\ExpiredException("Command expired before action")));
                continue;
            }

            $device = $cmd->getTarget();
            if(!$this->map->contains($device)) {
                $endpoint = $this->defaultEndpoint;
                if($endpoint == false) {
                    echo "TX ".$cmd." [DISCARDED, NO ROUTE]\n";
                    continue;
                }
            } else {
                $endpoint = $this->map->getEndpoint($device);
            }

            // Check that the device isn't backed off
            if($this->map->isBackedoff($device)) {
                $i--;
                continue;
            }

            // Attempt delivery; if it fails, put the command at the end of the queue
            $client = \Ensemble\Remote\ClientFactory::factory($endpoint);
            try {
                $client->sendCommand($cmd);
                echo "TX ".$cmd." [SENT] -> $endpoint\n";
            } catch(\Exception $e) {
                $this->map->backoff($device);
                $this->queue->push($cmd, time() + 10);
                echo "TX ".$cmd." [DELAYED BY ERROR]\n";
            }

        }
    }


    public function action(\Ensemble\Command $c, \Ensemble\CommandBroker $b) {
        return false;
    }

    public function isBusy() {
        return false;
    }


}
