<?php

namespace Ensemble\Device\Socket;
use Ensemble\MQTT\Client as MQTTClient;
use Ensemble\Schedule\Schedule as Schedule;
use Ensemble\Async as Async;

/**
 * Socket that polls a context device for a schedule, and operates off of that
 * A scheduled socket has three modes:
 * ON
 * OFF
 * OPOFF - "Opportunistic off" socket will switch off when load drops below 5W for more than 120s
 */
class ScheduledSocket extends Socket {

    public $opoff_threshold = 5; // OPOFF threshold in watts
    public $opoff_time = 180; // OPOFF threshold time in seconds

    public $sched_polltime = 60 * 15; // Poll for a schedule this often

    public $context_device = false;
    public $context_field = false;

    /**
     * Construct with the name of the context device and field name to poll
     * for the (JSON) schedule
     */
    public function __construct($name, MQTTClient $client, $deviceName, $context_device, $context_field) {
        parent::__construct($name, $client, $deviceName);

        $this->context_device = $context_device;
        $this->context_field = $context_field;
    }

    /**
     * The async routine checks for schedule updates and handles current state
     */
    private $schedule = false;
    public function getRoutine() {
        $socket = $this;
        return new Async\Lambda(function() use ($socket) {

            $start = time();

            // 1: Get the schedule from the configured context device
            yield $socket->getRefreshScheduleRoutine();

            if(!$this->schedule) {
                return;
            }

            // 2: Do the schedule
            while(time() < $start + $this->sched_polltime) {

                $mode = strtoupper($socket->schedule->getNow());

                // OPOFF Mode
                if($mode == 'OPOFF') {
                    if($this->isOn()) {
                        yield $this->getOpoffRoutine(); // Yield an OpOff routine
                    } else {
                        yield;
                    }
                }

                if($mode == 'ON') {
                    $this->on();
                }

                if($mode == 'OFF') {
                    $this->off();
                }

                yield;
            }
        });
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

    protected function getRefreshScheduleRoutine() {
        $socket = $this;
        return new Async\TimeoutController(new Async\Lambda(function() use ($socket) {
            $c = \Ensemble\Command::create($socket, $socket->context_device, 'getContext', array('field' => $socket->context_field));
            $socket->getBroker()->send($c);
            $rep = yield new Async\WaitForReply($socket, $c);

            if($rep->isException()) {
                $socket->log("Couldn't fetch schedule: ".$rep->getArg('message'), );
                return;
            }

            $json = $rep->getArg('values')[0]['value'];
            $socket->schedule = Schedule::fromJSON($json);
        }), 60);
    }
}
