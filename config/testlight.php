<?php

namespace Ensemble;
use Ensemble\MQTT\Client as MQTTClient;
use Ensemble\Schedule as Schedule;
use Ensemble\Device\Light as Light;

// Create a context device to broker schedules
$conf['devices'][] = $ctx = new Device\ContextDevice('test.schedules');

/**
 * Create a daily scheduler with a test period in just a moment...
 */
$bsched = new Schedule\Schedule();
$bsched->setPoint('00:00:00', 'auto 30%');
$bsched->setPoint('05:00:00', 'auto 30%');
$bsched->setPoint('06:00:00', 'auto 100%');
$bsched->setPoint('22:00:00', 'auto 100%');
$bsched->setPoint('23:00:00', 'auto 30%');


$sd = new Schedule\DailyScheduler('daily.scheduler', 'test.schedules', 'testlightschedule', $bsched);
$conf['devices'][] = $sd;

//$sd = new Light\RainbowScheduler('rainbow.scheduler', 'test.schedules', 'rainbowlightschedule', 900); // 15 min rainbow
//$conf['devices'][] = $sd;

// Create a socket to be controlled and bind it to the schedule in the broker
$client = new MQTTClient('10.0.0.8', 1883);
$conf['devices'][] = $socket = new Light\RGBWCT("light1-test", $client, "light1", 'test.schedules', 'testlightschedule');
