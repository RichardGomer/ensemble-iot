<?php

namespace Ensemble\Device\Socket;
use Ensemble\MQTT\Client as MQTTClient;

/**
 * Time the socket on the shower pump
 */
class ShowerSocket extends Socket  {

    private $threshold = 5; // Threshold power in watts

    public function __construct($name, MQTTClient $client, $deviceName) {
        parent::__construct($name, $client, $deviceName);
        $this->on();
    }

    public function getPollInterval() {
        return 5;
    }

    private $generator = false;
    public function poll(\Ensemble\CommandBroker $b) {
        parent::poll($b);

        // (Re)Start the program
        if(!$this->generator || !$this->generator->valid()) {
            $this->generator = $this->program();
        } else { // Or continue the program
            $this->generator->next();
        }
    }

    // Interrupt flow briefly as a warning
    public function warn() {
        $this->off();
        sleep(1);
        $this->on();
    }

    protected function program() {

        $current = $this->getPowerMeter();

        // 1: Wait for the socket to go above threshold power
        $power = $current->measure();

        while($power < $this->threshold) {
            echo "Yield to wait for current\n";
            yield;
            $power = $current->measure();
        }

        // 2: Run for 1 minute, then warning; 4 times; then off
        $mins = 4;
        for($i = 0; $i <= $mins; $i++) {
            $time = time();
            echo "Yield at start of period $i\n";
            yield;

            while(time() - $time < 56) {
                // If power drops, exit the program
                $zerocount = 0;
                while($current->measure() < $this->threshold) {
                    $zerocount++;

                    if($zerocount <= 3) {
                        echo "Yield to verify current is off\n";
                        yield;
                    }
                    else {
                        return;
                    }
                }

                echo "Yield to wait for next warning\n";
                yield;
            }

            if($i < $mins) {
                $this->warn();
            } else {
                $this->off();
            }
        }

        // 3: Wait three minutes before resetting
        $time = time();
        echo "Yield to wait for reset\n";
        yield;
        while(time() - $time < 180) {
            yield;
        }

        $this->on();
    }
}
