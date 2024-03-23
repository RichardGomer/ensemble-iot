<?php

/**
 * Maintain a map of registered device names to remote endpoints
 */

namespace Ensemble\Remote;

use Ensemble\Storage\JsonStore;

/**
 * Instantiate using a JsonStore so that the map can be shared
 */
class DeviceMap {

    private JsonStore $map;

    public function __construct(\Ensemble\Storage\JsonStore $store) {
        $this->map = $store;
    }

    /**
     * Register a device; expires can be set false to disable expiry
     */
    public function register($devicename, $endpoint, $expires=300) {
        $this->map->$devicename = array('url' => $endpoint, 'expires' => $expires ? time() + $expires : Infinity, 'backoff' => 0);
    }

    public function contains($devicename) {
        // Catch unknown devices
        if($this->map->$devicename === false){
            return false;
        }

        // Ignore expired devices
        if($this->map->$devicename['expires'] < time()){
            return false;
        }

        return true;
    }

    public function backoff($devicename, $time=30) {
        $d = $this->map->$devicename;
        if($d == false)
            return false;
        $d['backoff'] = time() + $time;
        $this->map->$devicename = $d;
    }

    public function isBackedOff($devicename) {
        $d = $this->map->$devicename;

        if($d == false)
            return false;

        return $d['backoff'] > time();
    }

    function getEndpoint($devicename) {
        $d = $this->map->$devicename;

        if($d == false)
            return false;

        return $d['url'];
    }

    function getMap() {
        return $this->map->getData();
    }

    function getEndpoints() {
        $devices = $this->map->getData();
        $eps = array();
        foreach($devices as $d) {
            $eps[] = $d['url'];
        }
        return array_values(array_unique($eps));
    }
}
