<?php

namespace Ensemble\GPIO;

/**
 * Control something via a relay
 */
class Relay {

    protected array $pins = []; // Irrigation SoftStart extends and accesses this directly - so not private
    private int $offState;

    public function __construct($pins, $offState = 1) {

        if($pins instanceof OutputPin)
            $pins = array($pins);

        foreach($pins as $p) {
            if(!$p instanceof OutputPin) {
                throw new \Exception("Can only pass OutputPin objects to Relay");
            }
        }

        $this->pins = $pins;
        $this->offState = $offState;

        $this->off(); // Start in a known state
    }

    public function on() {
        usleep(100000);
        foreach($this->pins as $pin)
		$pin->setValue(!$this->offState ? 1 : 0);
        usleep(100000);
    }

    public function off() {
        $value = $this->offState;
        usleep(100000);
        foreach($this->pins as $pin)
		$pin->setValue($this->offState ? 1 : 0);
        usleep(100000);
    }

    public function isOn() {
        $s = $this->pins[0]->getValue() == 1;
    }
}
