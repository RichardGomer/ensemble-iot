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
        /*for($i = 0; $i < 5; $i++) {
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

        foreach($this->workers as $n=>$worker) {
            echo "    Worker [$n]: ";

            $ll = substr(trim($worker->lastInputLog), 0, 100);

            if(!$worker->isRunning()) {
                echo "DEAD\n";
                //var_Dump($worker->read());
                unset($this->workers[$n]);
            }
            elseif($worker->isStalled()) {
                echo "STALLED  $ll\n";
                $worker->tell("exit\n");

                foreach($worker->getBuffer() as $l) {
                    echo " Worker [$n]:     ".trim($l)."\n";
                }

            }
            elseif(!$worker->isBusy()) {
                echo "AVAILABLE\n";
                $available[] = $worker;
            } else {
                echo "BUSY     $ll\n";
            }
        }
        
        if(count($available) == 0) {
            $this->workers[] = $w = new RequestWorker();
            $n = array_key_last($this->workers);
            echo "  * Created Worker [$n]\n";
        } else {
            $w = $available[0];
            $id = array_search($w, $this->workers);
            echo "    Use Worker [$id]\n";
        }

        return $w;
    }

}

class RequestWorker extends Thread {

    private $lastInput = 0;
    public $lastInputLog = false;

    public function __construct() {
        $this->lastInput = time();
        parent::__construct('php '.escapeShellArg(__DIR__.'/httpSend.cli.php'));
    }

    public function tell($thought, $eof=false) {
        $this->lastInput = time();
        $this->lastInputLog = $thought;
        parent::tell($thought, $eof);
    }

    public function isBusy() {

        // The worker prints out ----- after each request is complete
        $lastline = $this->lastOutput();
        if(strstr($lastline, '------') !== false) {
            return false;
        }

        return true;
    }

    protected function lastOutput() {
        $this->read();
        $buffer = $this->getBuffer();

        foreach(array_reverse($buffer) as $row) {
            $row = trim($row);
            if(strlen($row) > 1) {
                return $row;
            }
        }

        return false;
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

