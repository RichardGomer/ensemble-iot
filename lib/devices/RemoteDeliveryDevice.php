<?php

/**
 * A device that delivers messages from the remote queue.
 * It's only really a device for convenience, so it can use the polling
 * mechanism to run delivery regularly
 */

namespace Ensemble\Device;

use Ensemble\Queue;
use Ensemble\Remote\DeviceMap;

class RemoteDeliveryDevice implements \Ensemble\Module {

    private $maxDelivery = 5; // Maximum number of messages to deliver in a single attempt
    private $pollInterval = 0.1; // Request to be polled every this many seconds; this can be very frequent
    private $defaultEndpoint = false;

    private DeviceMap $map;
    private Queue $queue;

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

    public function getChildDevices() {
        return false;
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
            // discarded to prevent backlogs caused by connectivity problems overwhelming 
            // the remote node
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
                echo "     -> ".$e->getMessage()."\n";
            }

        }
    }


    public function action(\Ensemble\Command $c, \Ensemble\CommandBroker $b) {
        echo "RemoteDelivery Device received a command!\n";

        // Device registrations can arrive as commands; we should handle them
        if($c->getAction() == "registerDevices") {
            $devices = $c->getArg('devices');
            $ep = $c->getArg('endpoint');
            foreach($devices as $d) {
                echo "Register some devices at $ep\n";
                $this->map->register($d, $ep);
            }
        }

        return false;
    }

    public function isBusy() {
        return false;
    }


}
