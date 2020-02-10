<?php

namespace Ensemble;

interface Queue {

    /**
     * Add a command to the queue
     */
    public function push(Command $c);

    /**
     * Return the next command in the queue and remove it
     */
    public function shift();

    /**
     * Return the next command in the queue, but don't remove it
     */
    public function peek();

    /**
     * Return all commands in the queue, but don't remove them
     */
    public function peekAll();

    /**
     * Count the number of commands in the queue
     */
    public function count();

    /**
     * Check whether the queue is empty
     */
    public function isEmpty();


}
