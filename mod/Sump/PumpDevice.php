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

        // TODO: Allow pumping requests when water is required; as long as level
        // is above minimum? OR allow pumping to be inhibited for a time?
        //$this->registerAction('pump', $this, 'action_pump');
    }

    /**
     * Polling interval is increased when pumping so that we can detect when to
     * stop
     */
    public getPollInterval() {
        if($this->pumping()) {
            return 3;
        } else {
            return 60;
        }
    }

    protected function pumping() {
        return $this->pump->isOn();
    }

    private $lastpump = 0;
    public function poll() {
        $depth = $this->depth->measure();

        // If we're pumping, just decide when to stop
        if($this->pump->isOn()) {
            if($depth <= $this->min) {
                $this->pump->off();
            }
            return;
        }

        // Otherwise, decide whether to start
        if($depth > $this->mandatory) {
            $this->pump->on();
            $this->lastpump = time();
            return;
        }

        if($depth > $this->advisory && (time() - $this->lastpump) > $this->advisoryInterval) {
            $this->pump->on();
            $this->lastpump = time();
            return;
        }
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
