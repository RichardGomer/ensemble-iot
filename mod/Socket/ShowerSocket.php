<?php

namespace Ensemble\Device\Socket;
use Ensemble\MQTT\Client as MQTTClient;
use Ensemble\Async as Async;

/**
 * Time the socket on the shower pump
 */
class ShowerSocket extends Socket  {

    private $threshold = 5; // Threshold power in watts

    public function __construct($name, MQTTClient $client, $deviceName) {
        parent::__construct($name, $client, $deviceName);
        $this->on();
    }

    // Interrupt flow briefly as a warning
    public function warn() {
        $this->off();
        sleep(1);
        $this->on();
    }

    public function getRoutine() {
        $dev = $this;
        $current = $this->getPowerMeter();

        return new Async\Lambda(function() use ($dev, $current) {

            // 1: Wait for the socket to go above threshold power
            $power = $current->measure();

            while($power < $dev->threshold) {
                $dev->log("Yield to wait for current\n");
                yield;
                $power = $current->measure();
            }

            // 2: Run for 1 minute, then warning; 4 times; then off
            $mins = 4;
            for($i = 0; $i <= $mins; $i++) {
                $time = time();
                $dev->log("Yield at start of period $i\n");
                yield;

                while(time() - $time < 56) {
                    // If power drops, exit the program
                    $zerocount = 0;
                    while($current->measure() < $dev->threshold) {
                        $zerocount++;

                        if($zerocount <= 3) {
                            $dev->log("Yield to verify current is off\n");
                            yield;
                        }
                        else {
                            return;
                        }
                    }

                    $dev->log("Yield to wait for next warning\n");
                    yield;
                }

                if($i < $mins) {
                    $dev->warn();
                } else {
                    $dev->off();
                }
            }

            // 3: Wait three minutes before resetting
            $time = time();
            $dev->log("Yield to wait for reset\n");
            yield;
            while(time() - $time < 180) {
                yield;
            }

            $dev->on();
        });
    }
}
