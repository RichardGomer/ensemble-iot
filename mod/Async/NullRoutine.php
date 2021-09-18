<?php

/**
 * An Async routine that does nothing
 */

namespace Ensemble\Async;

class NullRoutine implements Routine {
    public function execute() {
        return;
    }
}
