<?php

namespace Ensemble\Device\LayZSpa;

use Ensemble\Async as Async;
use Ensemble\Async\waitForDelay;
use Ensemble\Device\Subscription as Subscription;
use Ensemble\KeyValue\SubscriptionStore;

class SpaDevice extends Async\Device {

    protected BestwaySpa $spa;
    public function __construct(string $name, BestwaySpa $spa) {
        $this->name = $name;
        $this->spa = $spa;
    }

    public function getRoutine() {
        return new waitForDelay(120); // Nothing to do atm
    }

    public function getTempSensor($interval = 600) {
        return new TempSensor($this->getDeviceName().'-tempsensor-'.uniqid(), $this->spa, $interval);
    }

}

class TempSensor extends \Ensemble\Device\SensorDevice {

    protected BestwaySpa $spa;
    private $interval;
    public function __construct($name, BestwaySpa $spa, $interval)
    {
        $this->name = $name;
        $this->spa = $spa;
        $this->interval = $interval;
    }

    public function getPollInterval()
    {
        return $this->interval;
    }

    public function measure() {
        try {
            return ['time' => time(), 'value' => $this->spa->getCurrentTemp()];
        } catch(\Exception $e) {
            // Just try again later
            echo "Couldn't get spa temperature: ".$e->getMessage()."\n";
            return false;
        }
    }

}