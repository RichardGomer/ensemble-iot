<?php

namespace Ensemble\Schedule;

/**
 * An Async routine for fetching data from a context device
 */

class FetchRoutine extends \Ensemble\Device\FetchContextRoutine {

    public function execute() {
        $json = yield from parent::execute();
        $schedule = Schedule::fromJSON($json);
        return $schedule;
    }
}
