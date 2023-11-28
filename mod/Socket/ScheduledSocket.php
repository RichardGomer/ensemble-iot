<?php

namespace Ensemble\Device\Socket;
use Ensemble\MQTT as MQTT;
use Ensemble\Schedule as Schedule;
use Ensemble\Async as Async;
use Ensemble\Device as Device;

/**
 * Socket that polls a context device for a schedule, and operates off of that
 * A scheduled socket has three modes:
 * ON
 * OFF
 * OPOFF - "Opportunistic off" socket will switch off when load drops below 5W for more than 120s
 *
 * Implemented using a schedule driver internally
 */
class ScheduledSocket extends Socket {

    public $opoff_threshold = 5; // OPOFF threshold in watts
    public $opoff_time = 300; // OPOFF threshold time in seconds

    public $sched_polltime = 60 * 15; // Poll for a schedule this often

    public $context_device = false;
    public $context_field = false;

    /**
     * Construct with the name of the context device and field name to poll
     * for the (JSON) schedule
     */
    public function __construct($name, MQTT\Bridge $bridge, Device\ContextPointer $ctxptr, $deviceName, $powerNum="") {
        parent::__construct($name, $bridge, $deviceName, $powerNum);

        $this->ctx = $ctxptr;

        $setFunc = function($device, $mode) {
            $mode = strtoupper($mode);

		echo "Socket mode is $mode\n";

            // OPOFF Mode
            if($mode == 'OPOFF') {
                if($device->isOn()) {
                    yield $device->getOpoffRoutine(); // Yield an OpOff routine
                } else {
                    yield;
                }
            }

            if($mode == 'ON') {
                $device->on();
            }

            if($mode == 'OFF') {
                $device->off();
            }
        };

        // Set up a schedule driver on this socket
        $this->driver = new Schedule\Driver($this, $setFunc, $ctxptr);
    }

    public function getDriver() {
        return $this->driver;
    }

    public function getChildDevices() {
        return array($this->driver);
    }

    /**
     * Return a subroutine that waits for low power on the socket before turning it off
     */
    protected function getOpoffRoutine() {
        $socket = $this;
        $current = $this->getPowerMeter();
        return new Async\TimeoutController(new Async\Lambda(function() use ($socket, $current) {

            // 1: Wait for the socket to go below threshold power
            $power = $current->measure();
            while($power > $socket->opoff_threshold) {
                $socket->log("Yield to wait for initial low current");
                yield;
                $power = $current->measure();
            }

            $lowtime = time();

            // 2: Wait for it to stay there
            do {
                $socket->log("Yield to wait for continuously low current\n");
                yield; // Yield to wait for next measurement

                $power = $current->measure();

                if($power > $socket->opoff_threshold) {
                    $socket->log("Opportunistic off was interrupted\n");
                    return; // Opoff attempt failed, it will be restarted if still required
                }
            }
            while(time() <= $lowtime + $socket->opoff_time);

            $socket->off();
            $socket->log("Opportunistic off completed\n");

        }) , $this->sched_polltime);
    }
}
