<?php

namespace Ensemble;
use Ensemble\MQTT\Client as MQTTClient;
use Ensemble\Schedule as Schedule;


// Create a context device to broker schedules
$conf['devices'][] = $ctx = new Device\ContextDevice('test.context');

// Create an Octopus API client
require 'dbcreds.php'; // Keep account info out of git!
$oct = new Schedule\Octopus($octo_key);
$oct->setElecMeter($octo_elec_meter_mpan, $octo_elec_meter_serial);
$oct->setGasMeter($octo_gas_meter_mprn, $octo_gas_meter_serial);
$oct->setTariff($octo_prodcode, $octo_trfcode);

// Create the tariff scheduler
$conf['devices'][] = new Schedule\OctopusTariffDevice('tariffscheduler', 'test.context', 'electariff', $oct);

// Create the usage schedulers
$conf['devices'][] = new Schedule\OctopusGasUsageDevice('gasusagescheduler', 'test.context', 'gasusage', $oct);
$conf['devices'][] = new Schedule\OctopusElecUsageDevice('elecusagescheduler', 'test.context', 'elecusage', $oct);

class ForecastTestDevice extends Async\Device {

    public function __construct() {
        $this->name = '*octopustest';
    }

    public function getRoutine() {
        $device = $this;
        return new Async\Lambda(function() use ($device) {

            yield;

        });
    }
}

$conf['devices'][] = new ForecastTestDevice();
