<?php

/**
 * This device announces local devices to remote endpoints on a regular basis
 * Typically this would be the
 */

namespace Ensemble\Device;

class AnnouncerDevice implements \Ensemble\Module {

    private $pollInterval = 120; // Interval, in seconds, between polls i.e. announcements

    /**
     * Construct with our own endpoint URL (this is what will get registered remotely)
     * and, optionally, a list of remote endpoints to notify
     *
     * Our own devicemap can also be passed in, and we'll notify devices reciprocally
     */
    public function __construct($endpointURL, $remotes=array(), \Ensemble\Remote\DeviceMap $map = null) {
        $this->endpointURL = $endpointURL;
        $this->remotes = $remotes;
        $this->map = $map;
    }

    public function announce() {
        return false;
    }

    public function addRemote($endpoint) {
        $this->remotes[] = $endpoint;
    }

    // Device name is hardcoded because it doesn't matter and shouldn't ever be
    // used for anything!
    public function getDeviceName() {
        return "_Announcer";
    }

    public function getChildDevices() {
        return false;
    }

    public function getPollInterval() {
        return $this->pollInterval;
    }

    public function poll(\Ensemble\CommandBroker $broker) {
        // We notify the remote of all local devices
        $devices = $broker->getLocalDevices();

        // Combine manually added remotes with those from the device map
        $remotes = $this->remotes;
        if($this->map instanceof \Ensemble\Remote\DeviceMap) {
            $remotes = array_unique(array_merge($this->map->getEndpoints(), $remotes));
        }

        foreach($remotes as $r) {
            try {
                $client = \Ensemble\Remote\ClientFactory::factory($r);
                $client->registerDevices($devices, $this->endpointURL);
            } catch (\Exception $e) {
                echo "Couldn't register with $r: {$e->getMessage()}\n";
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
