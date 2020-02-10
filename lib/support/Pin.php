<?php

namespace Ensemble\GPIO;

class Pin
{
	const IN = 1;
	const OUT = 2;

    protected $wpn;

	/**
	 * Constructed with a WiringPi pin number.  But, this is private, so use one of the static methods
	 */
	protected function __construct($wpn){
		$this->wpn = $wpn;
	}

	protected static function exec($cmd){
		$last = exec($cmd, $out);
		return $out;
	}

	protected static function getAllStatus(){

		$info = self::exec('gpio readall');

		$out = array();
		foreach($info as $n=>$line)
		{
			$line = explode('|', $line);

			if(count($line) < 14) continue;

			$pa_bcm = trim($line[1]);
			$pa_wpi = trim($line[2]);
			$pa_name = trim($line[3]);
			$pa_mode = trim($line[4]);
			$pa_val = trim($line[5]);
			$pa_phys = trim($line[6]);

			$pb_bcm = trim($line[13]);
			$pb_wpi = trim($line[12]);
			$pb_name = trim($line[11]);
			$pb_mode = trim($line[10]);
			$pb_val = trim($line[9]);
			$pb_phys = trim($line[8]);

			$out[] = array(
				'BCM'=>$pa_bcm,
				'wPi'=>$pa_wpi,
				'Mode'=>$pa_mode,
				'V'=>$pa_val,
				'Physical'=>$pa_phys,
			);

			$out[] = array(
				'BCM'=>$pb_bcm,
				'wPi'=>$pb_wpi,
				'Mode'=>$pb_mode,
				'V'=>$pb_val,
				'Physical'=>$pb_phys,
			);
		}

		return $out;
	}

	protected function getPinStatus($wpn){

		$all = self::getStatusAll();

		foreach($all as $pin){

			if($pin['wPi'] == $wpn)
				return $pin;

		}

		throw new InvalidPinException("Could not find wPi pin '$wpn'");
	}



	/**
	 * Get a pin by BCM number
	 */
	public static function BCM($bpn, $mode)
	{
		$info = self::getAllStatus();

		foreach($info as $pin)
		{
			if($pin['BCM'] == $bpn)
			{
				return self::getPin($pin['wPi'], $mode);
			}
		}

		throw new InvalidPinException("GPIO pin BCM $ppn doesn't seem to exist");
	}

	public static function phys($ppn, $mode)
	{
		$info = self::getAllStatus();

		foreach($info as $pin)
		{
			if($pin['Physical'] == $ppn)
			{
				return self::getPin($pin['wPi'], $mode);
			}
		}

		throw new InvalidPinException("GPIO pin PHYS $ppn doesn't seem to exist");
	}

	protected static function getPin($wpn, $mode)
	{
		if($mode === self::IN)
		{
			return new InputPin($wpn);
		}
		elseif($mode === self::OUT)
		{
			return new OutputPin($wpn);
		}
		else
		{
			throw new InvalidModeException("Invalid pin mode '$mode'");
		}
	}

	public function getStatus()
	{
		$info = self::getPinStatus($this->wpn);
		return $info;
	}

	public function getWPN()
	{
	    return $this->wpn;
	}

	// Get phsyical pin number of the pin
	public function getPhys()
	{
	$info = self::getAllStatus();

		foreach($info as $pin)
		{
			if($pin['wPi'] == $this->wpn)
			{
				return $pin['Physical'];
			}
		}
	}

	public function getValue()
	{
		$out = self::exec("gpio read {$this->wpn}");

		return (int) $out[0];
	}

}

class OutputPin extends Pin
{
	public function setValue($value)
	{
		$value = $value ? 1 : 0;
		$pin = $this->wpn;

		self::exec("gpio mode $pin out");
		self::exec("gpio write $pin $value");
	}
}

class InputPin extends Pin
{

	public function pullDown()
	{
		self::exec('gpio mode {$this->wpn} down');

	}

	public function pullUp()
	{
		self::exec('gpio mode {$this->wpn} up');
	}
}

class InvalidPinException extends \Exception {};
class InvalidModeException extends \Exception {};
