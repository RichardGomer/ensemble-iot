<?php

namespace Ensemble\Device\Irrigation;

/**
 * Represents an irrigation command; a command is just a channel name plus the
 * number of millilitres to discharge
 */
class IrrigationCmd implements PiotCommand {

    public static function

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
}
