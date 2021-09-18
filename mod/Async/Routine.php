<?php

namespace Ensemble\Async;

/**
 * A routine is an aysnc operation that can be put into an Async\Controller
 *
 */
interface Routine {
    /**
     * Execute this Async routine.
     *
     * The method can yield during execution, and can yield another instance of Routine
     * to spawn a child routine. Control will not pass back to the original routine until
     * the child has completed.
     *
     * The return value can be an AsyncRoutine (which will be executed) or a value to be
     * passed back to the previous operation on the stack, i.e. to be the result of that
     * routine's yield statement
     */
    public function execute();
}
