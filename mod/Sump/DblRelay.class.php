<?php

namespace Ensemble\Device\Sump;

/**
 * Control TWO relays as one; used to provide double-isolation of the pump
 */
class DblRelay
{
    public function __construct(\GPIO\OutputPin $a, \GPIO\OutputPin $b, $reverse=true)
    {
            $this->reverse = $reverse;
            $this->a = $a;
            $this->b = $b;
    }

    public function on()
    {
            $this->set(true);
    }

    public function off()
    {
            $this->set(false);
    }

    public function set($on)
    {
            if($this->reverse)
                    $on = !$on;

            $this->a->setValue($on);
            $this->b->setValue($on);
    }

    public function isOn() {
        return $this->a->getValue() == !$reverse;
    }

}
