<?php

namespace Ensemble\Device\Sump;

/**
 * A pump device is bound to a local depth sensor in order to shut off when the
 * water level is low.
 */
class PumpDevice extends \Ensemble\Device\BasicDevice {

    public function __construct($name, DblRelay $pump, DepthSensor $depth) {
        $this->name = $name;
        $this->pump = $pump;
        $this->depth = $depth;

        $this->registerAction('pump', $this, 'a_pump');
    }

    /**
     * Polling interval is increased when pumping so that we can detect when to
     * stop
     */
    public function getPollInterval() {
        if($this->pumping()) {
            return 3;
        } else {
            return 60;
        }
    }

    protected function pumping() {
        return $this->pump->isOn();
    }

    private $request = false; // Hold the current pumping request, if any

    private $lastpump = 0;
    public function poll(\Ensemble\CommandBroker $b) {
        $m = $this->depth->getAndPush($b);
        $depth = $m['value'];

        echo "Depth: {$depth}cm\n";

        // If we're pumping, just decide when to stop
        if($this->pump->isOn()) {

		echo "Pumping";

            // Off depth can be overriden
            $min = $this->requestMin !== false ? $this->requestMin : $this->min;

            if($depth <= $min) {
                $diff = $this->startDepth - $depth;
                $diffMl = $diff * $this->length * $this->width;
                $this->log("Stop pumping. Level {$depth}cm is below {$min}cm. Pumped {$diff}cm / {$diffMl}ml", $b);
                $this->pump->off();
            }
            return;
        }

        $this->startDepth = $depth; // Make a note of starting depth so we can calculate total change at the end

        if($this->request !== false) {
            $volume = $this->request->getArgOrValue('ml', 10000);
            $cm = $volume / ($this->length * $this->width); // Convert volume to cm of water using hole dimensions

            $this->requestMin = max($depth - $cm, $this->min);

            $this->log("Begin pumping. Request for {$volume}ml, reduce by {$cm}cm to depth $this->requestMin", $b);

            $this->request = false;
            $this->pump->on();
            $this->lastpump = time();
        }

        // Otherwise, decide whether to start
        if($depth > $this->mandatory) {
            $this->log("Begin pumping. Level ($depth) exceeds mandatory pumping threshold ($this->mandatory)", $b);
            $this->pump->on();
            $this->lastpump = time();
            return;
        }

        if($depth > $this->advisory && (time() - $this->lastpump) > $this->advisoryInterval) {
            $this->log("Begin pumping. Level ($depth) exceeds advisory pumping threshold ($this->advisory)", $b);
            $this->pump->on();
            $this->lastpump = time();
            return;
        }
    }

    // Block actions while a request is in progress
    public function isBusy() {
        return $this->request !== false;
    }

    public function a_pump(\Ensemble\Command $c, \Ensemble\CommandBroker $b) {
        $this->request = $c;
    }

    /**
     * Set sump dimensions
     */
    public function setDimensions($width, $length) {
        $this->width = $width;
        $this->length = $length;
    }

    /**
     * Set the minimum depth; pumping always stops below this level
     */
    private $min = 10;
    public function setMinimumDepth($min) {
        $this->min = $min;
    }

    /**
     * Advisory pumping takes place when the water depth is above $depth cm
     * but only a maximum of once per $interval seconds. Pumping continues until
     * the depth is below the minimum depth
     */
    private $advisory = 50;
    private $advisoryInterval = 3600;
    public function setAdvisoryPumping($depth, $interval) {
        $this->advisory = $depth;
        $this->advisoryInterval = $interval;
    }

    /**
     * Mandatory pumping takes place when the water depth is above a certain level
     * Typically this stops the device getting submerged!
     */
    private $mandatory = INF;
    public function setMandatoryPumping($depth) {
        $this->mandatory = $depth;
    }
}
