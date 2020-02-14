<?php

namespace Ensemble\GPIO;

/**
 * Control something via a relay
 */
class Relay {
    public function __construct(OutputPin $pin, $offState = 1) {
        $this->pin = $pin;
        $this->offState = $offState;
    }

    public function on() {
        usleep(100000);
        $this->pin->setValue(!$this->offState ? 1 : 0);
        usleep(100000);
    }

    public function off() {
        $value = $this->offState;
        usleep(100000);
        $this->pin->setValue($this->offState ? 1 : 0);
        usleep(100000);
    }
}
