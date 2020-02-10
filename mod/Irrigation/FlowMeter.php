<?php


/**
 * Irrigation support library
 *
 */

namespace Ensemble\Device\Irrigation;

/**
 * Attach to a flow meter
 */
class FlowMeter
{
    private $totalFlow = 0;

    public function __construct($bcmpin) {
        // Start the background process
        $this->proc = new \Ensemble\System\Thread("sudo ./mods-bin/yfs201flow $bcmpin");
    }

    /**
     * Read waiting lines from the background process
     */
    protected function read() {
        $lines = $this->proc->read();

        foreach($lines as $line) {
            if(preg_match('([0-9]+):([0-9]+)', $line, $parts)) {
                $ms = $parts[1];
                $revs = $parts[2];
                $flow = $this->calculateFlow($revs, $ms);
                $this->totalFlow += $flow;
            }
        }
    }

    /**
     * Calculate the total flow given the number of revolutions on the sensor (actually, partial revolutions)
     * plus the time it took.  Time is passed in to give the option of using a non-linear conversion based on
     * flow rate.
     */
    protected function calculateFlow($revs, $time) {
        return $revs * 2.25; // On the YFS-201, each pulse is 2.25ml +-10%
    }

    public function reset() {
        $this->proc->read(); // Discard any waiting output from the background monitor
        $this->totalFlow = 0;
    }

    // Get flow since last reset
    public function getFlow() {
        $this->read();
        return $this->$totalFlow;
    }
}
