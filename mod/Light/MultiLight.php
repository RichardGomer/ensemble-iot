<?php

namespace Ensemble\Device\Light;
use Ensemble\MQTT;
use Ensemble\Async;
use Ensemble\Schedule;

/**
 * Controls multiple lights
 */
class MultiLight extends Async\Device  implements RGBWCT  {

    public function __construct($name) {
        $this->name = $name;
    }

    /**
     * Switches are used to control power to the bulbs
     * All switches are operated when the MultiLight is set on/off
     *
     * $phys indicates if the switch is a physical switch - i.e. controls current
     * to the lights. Currently not used for anything.
     */
    private $switches = array();
    public function addSwitch(LightSwitch $switch, $phys=false) {
        $this->switches[] = array('switch' => $switch, 'phys'=>$phys);

        /**
         * Listen for switch status changes
         */
        $switch->subAction('POWERON', $this);
        $switch->subAction('POWEROFF', $this);
    }

    private $lights = array();
    public function addLight(RGBWCT $device, $xPos=0, $yPos=0) {
        $this->lights[] = array('x' => $xPos, 'y' => $yPos, 'device' => $device);
    }


    public function getPollInterval() {
        return 0;
    }

    /**
     * Main loop
     */
    public function getRoutine() {
        $device = $this;
        return new Async\Lambda(function() use ($device) {
            $cmd = yield new Async\WaitForCommand($device, array('POWERON', 'POWEROFF'));

            echo "Received {$cmd->getAction()} from {$cmd->getSource()}\n";

            if($cmd->getAction() == 'POWERON') {
                if(!$this->isOn())
                    $device->on();
            } else {
                if($this->isOn())
                    $device->off();
            }
        });
    }


    private $on = false;

    public function isOn() {
        return $this->on;
    }

    private $last = 0;

    /**
     * Turn lights on
     */
    public function on() {

        // Debounce changes
        if(time() < $this->last + 3) {
            echo "DEBOUNCE ON\n";
            return;
        }

        $this->last = time();

        $this->on = true;

        // 1: Set the control switches
        foreach($this->switches as $s) {
            $s['switch']->on();
        }

        // 2: Set the lights themselves
        $this->eachLight(function ($l) {
            $l['device']->on();
        });
    }

    /**
     * Turn lights off
     */
    public function off() {

        // Debounce changes
        if(time() < $this->last + 3) {
            echo "DEBOUNCE OFF\n";
            return;
        }

        $this->last = time();

        $this->on = false;

        // 1: Set the control switches
        foreach($this->switches as $s) {
            if($s['phys'] == false)
                $s['switch']->off();
            else
                $s['switch']->on();
        }

        // 2: Set the lights themselves
        $this->eachLight(function ($l) {
            $l['device']->off();
        });
    }

    protected function eachLight($fn) {
        foreach($this->lights as $l) {
            $fn($l);
        }
    }

    public function setRGB($r, $g, $b) {
        $this->eachLight(function ($l) use ($r, $g, $b) {
            $l['device']->setRGB($r, $g, $b);
        });
    }

    public function setCT($ct) {
        $this->eachLight(function ($l) use ($ct) {
            $l['device']->setCT($ct);
        });
    }

    public function setBrightness($percent) {
        $this->eachLight(function ($l) use ($percent) {
            $l['device']->setBrightness($percent);
        });
    }

}
