<?php

namespace Ensemble;

/**
 * Implement a queue using a JsonStore backend
 */
class JsonQueue implements Queue {

    public function __construct($fn) {
        $q = new Storage\JsonStore($fn);
        $this->json = $q;

        if($this->json->queue === false) {
            $this->json->queue = array();
        }
    }

    /**
     * Add a command to the queue
     */
    public function push(Command $c) {
        $this->json->queue = array_merge($this->json->queue, array($c->toJSON()));
    }

    /**
     * Return the next command in the queue and remove it
     */
    public function shift() {
        $q = $this->json->queue;
        $next = array_shift($q);
        $this->json->queue = $q;
        return Command::fromJSON($next);
    }

    /**
     * Return the next command in the queue, but don't remove it
     */
    public function peek() {
        return Command::fromJSON(array_shift($q = $this->json->queue));
    }

    /**
     * Return all commands in the queue, but don't remove them
     */
    public function peekAll() {
        $out = array();
        foreach($this->json->queue as $c) {
            $out[] = Command::fromJSON($c);
        }
        return $out;
    }

    /**
     * Count the number of commands in the queue
     */
    public function count() {
        return count($this->json->queue);
    }

    /**
     * Check whether the queue is empty
     */
    public function isEmpty() {
        return $this->count() < 1;
    }

}
