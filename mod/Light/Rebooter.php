<?php

namespace Ensemble\Device\Light;


class Rebooter extends \Ensemble\Device\BasicDevice {

    public function __construct(\Ensemble\Device\Light\LightSwitch $switch) {
        $this->switches = array($switch);

        $this->name = $switch->getDeviceName()."-rebooter-".rand(1000000,9999999);
    }

    public function announce() {
        return false;
    }

    public function getPollInterval() {
        return 300; // 5 minutes
    }

    public function poll(\Ensemble\CommandBroker $b) {

        // Reboot once, during the window 03:00 to (03:00 + poll interval)
        if(date('G') == 3 && (date('i') * 60) < $this->getPollInterval()) {
                foreach($this->switches as $s) {
                    $s->off();
                }

                sleep(2);

                foreach($this->switches as $s) {
                    $s->on();
                }
        }

    }

}
