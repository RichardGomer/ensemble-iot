<?php

namespace Ensemble\Device\W1;

class Sensor extends \Ensemble\Device\SensorDevice {

    /**
     * Enable 1wire on the given GPIO pin
     */
    public static function enable($gpio) {
        $gpio = (int) $gpio;
        exec("dtoverlay w1-gpio gpiopin=$gpio pullup=0");
    }

    public function getPollInterval() {
        return 60;
    }

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

        $reading = file_get_contents($path);

        return array('time'=>time(), 'reading'=>$reading);
    }
}

class TemperatureSensor extends Sensor {

    public function __construct($name, $id) {
        parent::__construct($name, $id, "temperature");
    }

    public function measure() {
        $raw = parent::measure();

        foreach($raw as $k=>$v) {
            $raw[$k] = $v / 1000;
        }

        return $raw;
    }
}

class SensorNotFoundException extends \Exception {}
