<?php

/**
 * A simple device for doing regular tasks
 */
namespace Ensemble\Async;

class Regularly extends Device {

    /**
     * $f is a callable that will be run every $interval seconds
     * $f must accept a single argument, the Regularly device itself (which it
     * may use for inter-device comms etc.)
     */
    public function __construct($interval, callable $f) {
        $this->name = 'regularly-'.uniqid();
        $this->f = $f;
        $this->interval = $interval;
    }

    public function getRoutine() {
        $device = $this;
        return new Lambda(function() use ($device) {
            $start = time();
            echo "EXECUTE\n";
            $f = $device->f;
            yield from $f($device);
            yield new WaitUntil($start + $this->interval);
        });
    }

}
