<?php

namespace Ensemble\Device\W1;

class TemperatureSensor extends Sensor {

    public function __construct($name, $id) {
        parent::__construct($name, $id, "temperature");
    }

    public function measure() {
        $raw = parent::measure();

        $raw['value'] = $raw['value'] / 1000;

        return $raw;
    }
}
