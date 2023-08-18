<?php

namespace Ensemble\GPIO;

use Exception;

class Pin
{
	const IN = 1;
	const OUT = 2;

    // Map physical pins to BCM/GPIO lines, for Raspi Zero W
    const Map_PhysBCM = array(
        3 => 2,
        5 => 3,
        7 => 4,
        8 => 14,
        10 => 15,
        11 => 17,
        12 => 18,
        13 => 27,
        15 => 22,
        16 => 23,
        18 => 24,
        19 => 10,
        21 => 9,
        22 => 25,
        23 => 11,
        24 => 8,
        26 => 7,
        27 => 0,
        28 => 1,
        29 => 5,
        31 => 6,
        32 => 12,
        33 => 13,
        35 => 19,
        36 => 16,
        37 => 26,
        38 => 20,
        40 => 21,
    );

    protected string $chip; // Chip name
    protected int $line; // GPIO line number

	/**
	 * Constructed with a WiringPi pin number.  But, this is private, so use one of the static methods
	 */
	protected function __construct($line, $chip){
		$this->chip = $chip;
        $this->line = $line;
	}

	protected static function exec($cmd){
		$last = exec($cmd, $out);
		return $out;
	}

    protected static function getLines($chip) {
        $lines = self::exec("gpioinfo '$chip'");

        foreach($lines as $l) {
            if(preg_match('/^\s+line\s+([0-9]{1,2}):\s+"([a-z0-9_\-]+)"\s+("(.*)"|unused)\s+(output|input)/i', $l, $matches)) {

                list($x, $line, $name, $assignedUse, $mode) = $matches;


                $pins[$line] = [
                    'name' => $name,
                    'mode' => $mode,
                    'line' => $line
                ];
            }
        }

        return $pins;
    }

    /**
     * Get a pin by physical number, on the RaspiZero W
     */
    public static function phys($phys, $mode) {
        if(!array_key_exists($phys, self::Map_PhysBCM)) {
            throw new InvalidPinException("Physical pin $phys does not map to a GPIO line");
        }

        return self::getPin(self::Map_PhysBCM[$phys], $mode, 'gpiochip0');
    }


	/**
	 * Get a pin by BCM number; this is the same as the line/GPIO number
	 */
	public static function BCM($bpn, $mode, $chip="gpiochip0")
	{
		return self::getPin($bpn, $mode, $chip);
	}
    
	protected static function getPin($bcm, $mode, $chip="gpiochip0")
	{
		if($mode === self::IN)
		{
			return new InputPin($bcm, $chip);
		}
		elseif($mode === self::OUT)
		{
			return new OutputPin($bcm, $chip);
		}
		else
		{
			throw new InvalidModeException("Invalid pin mode '$mode'");
		}
	}

    /**
     * Get the status of a pin by line/GPIO number
     */
    protected function getPinStatus(int $line){

		$all = self::getLines($this->chip);

		foreach($all as $pin){

			if($pin['line'] == $line)
				return $pin;

		}

		throw new InvalidPinException("Could not find status for line '$line'");
	}

	public function getStatus()
	{
		$info = self::getPinStatus($this->line);
		return $info;
	}

	// Get phsyical pin number of the pin
	public function getPhys()
	{
	    if($this->chip !== 'gpiochip0') {
            throw new \Exception("Pin mappings are only available for gpiochip0 (on Raspberry Pi)");
        }
	}

    protected function getValue() {}

}

class OutputPin extends Pin
{
    private int $value = 0;
	public function setValue($value)
	{
		$this->value = $value ? 1 : 0;
		$pin = $this->wpn;

		self::exec("gpioset '{$this->chip}' {$this->line}={$this->value}");
	}

    public function getValue() {
        return $this->value;
    }
}

class InputPin extends Pin
{
	public function getValue()
	{
		$out = self::exec("gpioget '{$this->chip}' {$this->line}");

		return (int) $out[0];
	}
}

class InvalidPinException extends \Exception {};
class InvalidModeException extends \Exception {};
