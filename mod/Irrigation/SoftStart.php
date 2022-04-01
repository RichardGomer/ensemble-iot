<?php

namespace Ensemble\Device\Irrigation;

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

    private $running = false;
    public function on() {
        if($this->running)
            return;

        $this->running = true;
        usleep(100000);

        $script = dirname(__FILE__).'/softstart/softstart.py';
        $this->thread = new System\Thread("python3 $script {$this->pins[0]->getPhys()} {$this->intensity}");
    }

    public function off() {
        if(!$this->running)
            return;

        $this->thread->close(2); // Signal 2 = SIGINT, should trigger a KeyboardInterrupt in the python process

        $this->running = false;
    }

    public function isOn() {
        return $this->running;
    }
}
