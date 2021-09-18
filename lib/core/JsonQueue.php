<?php

namespace Ensemble;

/**
 * Implement a queue using a JsonStore backend
 */
class JsonQueue implements Queue {

    public function __construct($fn) {
        $q = new Storage\JsonStore($fn);
        $this->json = $q;

        if(!is_array($this->json->queue)) {
            $this->json->queue = array();
        }
    }

    protected function getQueue() {
        $q = $this->json->queue;

        if(!is_array($q)) {
            $q = $this->json->queue = array();
            trigger_error("Queue was corrupted - it has been fixed", E_USER_WARNING);
        }

        return $q;
    }

    /**
     * Add a command to the queue; optionally with a threshold time.
     * Commands are essentially invisible until the threshold time is reached; they
     * don't count towards the queue length and won't be returned by peeking or
     * shifting
     */
    public function push(Command $c, $threshold=0) {
        $j = array('threshold'=>$threshold, 'cmd'=>$c->toJSON());
        $this->json->queue = array_merge($this->getQueue(), array($j));
    }

    /**
     * Return the next post-threshold command in the queue and remove it
     */
    public function shift() {
        $this->json->lock();
        $q = $this->getQueue();

        foreach($q as $i=>$c) {
            if($c['threshold'] <= time()) {
                array_splice($q, $i, 1);
                $this->json->queue = $q;
                $this->json->release();
                return Command::fromJSON($c['cmd']);
            }
        }

        $this->json->release();
    }

    /**
     * Return all post-threshold commands in the queue, but don't remove them
     */
    public function peekAll($max=INF) {
        $out = array();
        $q = $this->getQueue();

        foreach($q as $i=>$c) {
            if($c['threshold'] <= time()) {
                $out[] = Command::fromJSON($c['cmd']);
                if(count($out) == $max)
                    break;
            }
        }

        return $out;
    }

    /**
     * Return the next post-threshold command in the queue, but don't remove it
     */
    public function peek() {
        return $this->peekAll(1);
    }

    /**
     * Count the number of post-threshold commands in the queue
     */
    public function count() {
        return count($this->peekAll());
    }

    /**
     * Count ALL commands (ergardless of threshold) in the queue
     */
    public function countAll() {
        return count($this->getQueue());
    }

    /**
     * Check whether the queue is empty of post-threshold commands
     */
    public function isEmpty() {
        return count($this->peekAll(1)) == 0;
    }

}
