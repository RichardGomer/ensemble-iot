<?php

namespace Ensemble;
use Ensemble\MQTT\Client as MQTTClient;
use Ensemble\Device\Forecast as Forecast;


// Create a context device to broker schedules
$conf['devices'][] = $ctx = new Device\ContextDevice('test.context');

require 'dbcreds.php'; // Keep datapoint key out of git!
$conf['devices'][] = new Forecast\ForecastDevice('test.forecast', $datapoint_key, '353868', 'test.context', 'testforecast-');
$conf['devices'][] = new Forecast\RainfallDevice('test.rainfall', 'E12160', 'test.context', 'rainfall');

class ForecastTestDevice extends Async\Device {

    public function __construct() {
        $this->name = '*forecasttest';
    }

    public function getRoutine() {
        $device = $this;
        return new Async\Lambda(function() use ($device) {

            $cmd = Command::create($device, 'test.context', 'getContext');
            $cmd->setArg('field', 'testforecast-T');
            $device->getBroker()->send($cmd);
            $reply = yield new Async\WaitForReply($device, $cmd);

            $vals = $reply->getArg('values');

            var_Dump($vals);


            $cmd = Command::create($device, 'test.context', 'getContext');
            $cmd->setArg('field', 'rainfall');
            $device->getBroker()->send($cmd);
            $reply = yield new Async\WaitForReply($device, $cmd);

            $vals = $reply->getArg('values');

            var_Dump($vals);

        });
    }
}

$conf['devices'][] = new ForecastTestDevice();
