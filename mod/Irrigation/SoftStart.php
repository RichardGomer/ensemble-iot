<?php

namespace Ensemble\Irrigation;

use Ensemble\GPIO as GPIO;
use Ensemble\System as System;

/**
 * Control a soft-starter for a motor
 * Can be swapped in for a Relay
 */
class SoftStart extends GPIO\Relay {

    public function __construct(GPIO\OutputPin $pin, $intensity=100) {
        parent::__construct($pin);
        $this->intensity = $intensity;
    }

    public function on() {
        if($this->running)
            return;

        $this->running = true;
        usleep(100000);

        $script = dirname(__FILE__).'/softstart/softstart.py';
        $this->thread = new System\Thread("python3", array($script, $this->pin->getPhys(), $this->intensity));
    }

    public function off() {
        if(!$this->running)
            return;

        $this->thread->close();

        $this->running = false;
    }

    public function isOn() {
        return $this->running;
    }
}
