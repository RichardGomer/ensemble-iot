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
class PressureSensor extends \Ensemble\Device\SensorDevice
{
    public function __construct($name, Relay $pump=null) {
        // Start the background process
        $ps = __DIR__.'/MCP300X/mcp300x.py';
        $cmd = "python3 -u $ps";
        $this->proc = new \Ensemble\System\Thread($cmd);
        $this->pump = $pump;
	$this->name = $name;
    }

    public function getPollInterval() {
        return 30;
    }

    /**
     * Read waiting lines from the background process
     */
    private $last = false;
    public function measure() {
        $lines = $this->proc->read();

        // Don't measure pressure if pump is on
        if($this->pump instanceof Relay && $this->pump->isOn()) {
            //echo "Pump is active; skipping pressure measurement\n";
            return false;
        }

        //var_dump($lines);

        foreach($lines as $line) {
            if(preg_match('/:: ([0-9]+)/', $line, $parts)) {
                var_dump($line, $parts);
                $this->last = $parts[1];
            }
        }

        return array('time'=>time(), 'value'=>$this->last);
    }
}
