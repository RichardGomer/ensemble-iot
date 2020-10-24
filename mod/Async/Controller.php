<?php

/**
 * Support and helpers for asynchronous operations
 * This is the controller
 *
 * The benefit of using Async\Controller over plain native yield is that execution
 * can be passed back to the main thread and resumed later on. Async\Device uses
 * Async\Controller to implement non-blocking Ensemble Devices
 *
 */

namespace Ensemble\Async;

class Controller {

    private $debug = false; // Set true to enable async debugging messages
    private $genprov = array(); // Generator provenance - used to help debug async stack

    /**
     * The stack contains generator objects derived from the async routines
     * that we're managing
     */
    private $stack = array();

    public function __construct(Routine $r) {
        $this->pushRoutine($r);
    }

    private $return = null; // Holds return value of the last async routine, if there is one

    /**
     * Continue execution, for a single step. i.e. until the next routine yields.
     */
    public function continue() {

        if($this->isComplete())
            return;

        $this->debug("Continue routine");

        $top = array_pop($this->stack); // Get the top of the stack

        // If the top item isn't yet a generator, then it needs to be executed to produce one
        if($top instanceof Routine) {
            $newtop = $top->execute();

            $this->debug("ASYNC: ".$this->dbgid($top)." became ".$this->dbgid($newtop));
            $this->genprov[$this->dbgid($newtop)] = $this->dbgid($top);
            $top = $newtop;

            // The routine will either have yielded (to create a Generator) or
            // returned immediately
            if($top instanceof \Generator) {
                $rtn = $top->current();
            }
            else {
                $rtn = $top;
            }
        } elseif($top instanceof \Generator) { // Otherwise we call the current top of the stack, passing the last return value if there is one
            $rtn = $top->send($this->return);
            $this->return = null;
        }

        // If the generator has yielded (not returned) we need to handle the yield
        if($top instanceof \Generator && $top->valid()) {
            $this->stack[] = $top; // Return the current routine to the stack

            if($rtn instanceof Routine) { // Yielded routines are added to the stack
                $this->pushRoutine($rtn);
                $this->debug("New routine was yielded, add to stack");
                $this->continue(); // Continue (in the new routine) immediately
            } elseif($rtn == null) {
                // Null-ish values mean wait; it will be resumed when continue() is called again.
                // This is the only condition that we interpret as progress being blocked, and hence the only
                // condition that passes control back to the process that called continue()
                $this->debug("Routine has yielded to wait");
            } else {
                // Otherwise immediately pass the value back into the Routine
                // This behaviour is important; it allows a Routine to yield something() that could be a Routine OR a literal
                // and make use of the literal without delay
                $this->debug("Literal was yielded, continue Routine");
                $this->return = $rtn;
                $this->continue(); // Continue (in the new routine) immediately
            }
        } else { // The Generator has returned
            if($top instanceof \Generator) {
                $rtn = $top->getReturn();
            } else {
                $rtn = $top;
            }

            if($rtn instanceof Routine) { // If the routine returned a new routine, the new one replaces it
                $this->pushRoutine($rtn);
                $this->debug("New routine was returned, add to stack");
                $this->continue();
            } else { // Otherwise, return the value to the new top of the stack and run that
                $this->debug("Value was returned, pass to parent routine");
                $this->return = $rtn;
                $this->continue();
            }
        }
    }

    public function isComplete() {
        return count($this->stack) == 0;
    }

    protected function getReturn() {
        return $this->return;
    }

    protected function pushRoutine($r) {
        $this->stack[] = $r;
    }

    /**
     * Debugging stuff
     */
    protected function debug($msg) {
        if(!$this->debug)
            return;

        echo "ASYNC: $msg\n       ".$this->dbgid($this).":\n";

        foreach(array_reverse($this->stack, true) as $i=>$r) {
            echo "       [".str_pad($i, 2, " ", STR_PAD_LEFT)."]  ".$this->dbgid($r)."\n";
        }
    }

    protected function dbgid($r) {
        if($r == null)
            return "(NULL)";

        $id = get_class($r)."#".spl_object_id($r);

        if(array_key_exists($id, $this->genprov)) {
            $id .= " (was ".$this->genprov[$id].")";
        }

        return $id;
    }
}
