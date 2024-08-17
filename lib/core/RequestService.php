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

    protected function getWorker() : Thread {
        if($this->worker instanceof Thread && !$this->worker->isRunning()) {
            echo "Worker is dead?\n";
            var_Dump($this->worker->read());
            $this->worker = null;
        }

        if(!$this->worker instanceof Thread) {
            $this->worker = new Thread('php '.escapeShellArg(__DIR__.'/httpSend.cli.php'));
        }

        return $this->worker;
    }

}

