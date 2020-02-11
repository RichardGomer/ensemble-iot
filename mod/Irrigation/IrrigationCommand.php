<?php

namespace Ensemble\Device\Irrigation;

/**
 * Represents an irrigation command; a command is just a channel name plus the
 * number of millilitres to discharge
 */
class IrrigationCmd {

    public function __construct($channel, $ml) {
        $this->ml = $ml;
        $this->channel = $channel;
    }

    public function getChannel() {
        return $this->channel;
    }

    public function getMl() {
        return $this->ml;
    }


    // Reporting

    private $flow = false;
    public function setFlow($flow) {
        $this->flow = $flow;
    }

    public function getFlow() {
        return $this->flow;
    }

    private $time = false;
    public function setTime($t) {
        $this->time = $time;
    }

    public function getTime() {
        retrun $this->time;
    }
}
