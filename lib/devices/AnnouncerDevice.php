<?php

/**
 * This device announces local devices to remote endpoints on a regular basis
 */

namespace Ensemble\Device;

class AnnouncerDevice implements \Ensemble\Module {

    private $announceInterval = 120; // Interval between announcements to each endpoint
    private $epsPerPoll = 3; // Number of remotes to announce to on each poll

    private $pollInterval = 10; // This is re-calculated on the first poll

    private $remotes = [];
    private string $endpointURL;
    private $map = [];


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

        // Recalculate poll interval to announce at the right frequency
        $this->pollInterval = $this->announceInterval / max(1, ceil((count($this->remotes) / $this->epsPerPoll))); // max avoids a div by zero problem

        // We notify the remote of all local devices
        $devices = $broker->getLocalDevices();

        // Remove devices that should not be announced
        foreach($devices as $k=>$d) {
            if($broker->getDevice($d)->announce() === false) {
                unset($devices[$k]);
            }
        }

        if(count($devices) < 1) {
            echo "No devices to announce\n";
            return;
        }

        // Combine manually added remotes with those from the device map
        $remotes = $this->remotes;
        if($this->map instanceof \Ensemble\Remote\DeviceMap) {
            $remotes = array_unique(array_merge($this->map->getEndpoints(), $remotes));
        }

        /**
         * Threading adds some complexities into announcements.
         * We should:
         * 1) Pick only one of our own EPs to announce to each remote; so that e.g. threads use json, others use HTTP
         * 2) Not announce json to remote EPs, which can only use HTTP
         * What are the rules? Only announce "compatible" endpoints?
         */

        foreach($remotes as $r) {
            try {
                $cd = count($devices);
                echo "Announce $cd devices to $r\n";
                $client = \Ensemble\Remote\ClientFactory::factory($r);
                $client->registerDevices($devices, $this->endpointURL);
            } catch (\Exception $e) {
                echo "Couldn't register with $r: {$e->getMessage()}\n";
                debug_print_backtrace();
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
