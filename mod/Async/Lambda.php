<?php

namespace Ensemble\Async;

class Lambda implements Routine {
    public function __construct(\Closure $f) {
        $this->lambda = $f;
    }

    public function execute() {
        $last = yield from ($this->lambda)();
        return $last;
    }
}
