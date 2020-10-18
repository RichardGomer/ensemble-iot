<?php

/**
 * Run an async operation, but apply a timeout
 * If execution lasts for more than the specified number of seconds, an exception is thrown
 * Use this to guard against operations that a) get stuck, or b) are time sensitive and could
 * have undesirable consequences if they execute over a protracted amount of time
 */
namespace Ensemble\Async;

class TimeoutController extends Controller implements Routine {

    public function __construct(Routine $r, $timeout=30) {
        $this->start = time();
        $this->timeout = $timeout;

        parent::__construct($r);
    }

    public function continue() {
        $this->checkTimeout();
        return parent::continue();
    }

    protected function checkTimeout() {
        if(time() > $this->start + $this->timeout) {
            throw new TimeoutException("Execution of Async routine exceeded timeout");
        }
    }

    /**
     * If used as a Routine, run the wrapped routine until it completes or the
     * timeout is reached
     */
    public function execute() {
        while(!$this->isComplete()) {
            $res = $this->continue();
            yield $res;
        }

        return $res;
    }
}

class TimeoutException extends \Exception {}
