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
            escapeshellarg($url),
            !empty($args) ? escapeshellarg(json_encode($args)) : ''
        );

        $this->getWorker()->tell($command);

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

