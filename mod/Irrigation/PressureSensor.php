<?php


/**
 * Irrigation support library
 *
 */

namespace Ensemble\Device\Irrigation;
use Ensemble\GPIO\Relay;

/**
 * Attach to a pressure sensor, implemented using an MCP3008 ADC
 * If Relay is provided and on, measurements are supressed
 */
class PressureSensor
{
    public function __construct(Relay $pump=null) {
        // Start the background process
        $ps = __DIR__.'/MCP300X/mcp300x.py';
        $cmd = "python3 $ps";
        $this->proc = new \Ensemble\System\Thread($cmd);
        $this->pump = $pump;
    }

    /**
     * Read waiting lines from the background process
     */
    private $last = false;
    protected function measure() {
        $lines = $this->proc->read();

        // Don't measure pressure if pump is on
        if($this->pump instanceof Relay && $this->pump->isOn()) {
            return false;
        }

        foreach($lines as $line) {
            if(preg_match('/:: ([0-9]+)/', $line, $parts)) {
                $this->last = $parts[1];
            }
        }

        return array('time'=>time(), 'value'=>$this->last);
    }
}
