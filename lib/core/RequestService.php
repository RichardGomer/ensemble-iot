<?php

namespace Ensemble;

use Ensemble\System\Thread;

/**
 * Make HTTP requests in separate processes
 * TODO: We don't retrieve the response, which for many requests is fine; but we could...
 */

class RequestService {

    private ?Thread $worker = null;

    private $maxWorkers = 5; // Maximum number of workers

    private static ?RequestService $instance = null;

    public static function getInstance() : RequestService {

        if(!self::$instance instanceof RequestService) {
            $c = __CLASS__;
            self::$instance = new $c();
        }

        return self::$instance;
    }

    public function request($method, $url, $args) {
        $command = sprintf(
            "%s %s %s\n",
            strtoupper($method),
            $url,
            !empty($args) ? json_encode($args) : ''
        );

        $worker = $this->getWorker();
        $worker->tell($command."\n");

        // For debugging we can print a few lines of what happens
        /*for($i = 0; $i < 10; $i++) {
            usleep(100000);
            var_dump($worker->read());
        }*/

    }

    /**
     * Get a worker, and also do some worker management
     * @return Thread 
     */
    private $workers = [];
    protected function getWorker() : Thread {
        
        // Clean up dead workers; and find busy workers
        $available = [];
        foreach($this->workers as $worker) {
            if(!$worker->isRunning()) {
                echo "Worker is dead?\n";
                var_Dump($worker->read());
                $this->worker = null;
            }

            if($worker->isStalled()) {
                $worker->tell("exit\n");
            }

            if(!$worker->isBusy()) {
                $available[] = $worker;
            }
        }
        
        if(count($available) == 0) {
            $this->workers[] = $w = new RequestWorker();
        } else {
            $w = $available[0];
        }

        return $w;
    }

}

class RequestWorker extends Thread {

    private $lastInput = 0;

    public function __construct() {
        $this->lastInput = time();
        parent::__construct('php '.escapeShellArg(__DIR__.'/httpSend.cli.php'));
    }

    public function tell($thought) {
        $this->lastInput = time();
        parent::tell($thought);
    }

    public function isBusy() {
        $this->read();
        $rows = $this->getBuffer();

        // The worker prints out .s while it is busy; then ----- after each request
        // So if the last line contains a ., it must be busy!
        $lastline = array_pop($rows);
        if(strstr($lastline, '.')) {
            return true;
        }
    }

    /**
     * Check if the worker is stalled - i.e. it is still busy 15s after the last input
     * @var Ensemble\isStalled
     */
    public function isStalled() {
        if(($this->lastInput < time() - 15) && $this->isBusy()) {
            return true;
        }

        return false;
    }
}

