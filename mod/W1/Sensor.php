<?php

namespace Ensemble\Device\W1;

class Sensor extends \Ensemble\Device\SensorDevice {

    /**
     * Enable 1wire on the given GPIO pin
     */
    public static function enable($gpio) {
        $gpio = (int) $gpio;
        exec("sudo dtoverlay w1-gpio gpiopin=$gpio pullup=0");
    }

    public function getPollInterval() {
        return 60;
    }

    protected $id, $field;
    public function __construct($name, $id, $field) {
        $this->id = $id;
        $this->field = $field;
        $this->name = $name;

        $this->measure();
    }

    public function measure() {
        $spath = "/sys/bus/w1/devices/{$this->id}/";
        if(!file_exists($spath)) {
            throw new SensorNotFoundException("Sensor {$this->id} was not found");
        }

        $fpath = $spath.$this->field;
        if(!file_exists($fpath)) {
            throw new SensorNotFoundException("Field {$this->field} not found on sensor {$this->id}");
        }

        $reading = trim(file_get_contents($fpath));

        return array('time'=>time(), 'value'=>$reading);
    }
}

class SensorNotFoundException extends \Exception {}
