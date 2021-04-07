<?php

namespace Ensemble\System;


/**
 * Based on class from https://gist.github.com/scribu/4736329
 */

/**
 * NB: If lines aren't being read from a C++ program, maybe need to add:
 *		   setbuf(stdout,NULL);
 *     at the start of main()
 */

class Thread
{
	var $process; // process reference
	var $pipes; // stdio
	var $buffer = array(); // stdout buffer
	var $bufferlen = 15; // Lines to store in buffer
	var $output;
	var $error;
	var $timeout;
	var $start_time;

	/**
	 * Start a background task by running $command
	 * Optionally, an associative $args can contain arguments; in the form
	 * array ( "flag" => "value", "flag2" => "value2" )
	 * Values are escaped
	 */
	public function __construct($command, $args=array())
	{
		$this->process = false;
		$this->pipes = array();

		$astr = "";
		foreach($args as $flag=>$value) {
			$astr .= " -{$flag} ".escapeshellarg($value);
		}

		$descriptor = array ( 0 => array ( "pipe", "r" ), 1 => array ( "pipe", "w" ), 2 => array ( "pipe", "w" ) );

        echo "EXEC ".$command.$astr."\n";

		// Open the resource to execute $command
		$this->process = proc_open( $command.$astr, $descriptor, $this->pipes );

		// Set STDOUT and STDERR to non-blocking
		stream_set_blocking( $this->pipes[1], 0 );
		stream_set_blocking( $this->pipes[2], 0 );
	}

	// Close the process
	public function close($sig=SIGTERM)
	{
		proc_terminate($this->process, $sig);
		//posix_kill($this->getPID(), $sig);
	}

	//Get the status of the current runing process
	private $exitcode = false;
	function getStatus()
	{
		$status = proc_get_status( $this->process );

		// Exit code is only returned by proc_get_status once, so cache it
		if($status['running'] == false && $this->exitcode === false) {
			$this->exitcode = $status['exitcode'];
		}

		if($this->exitcode !== false)
			$status['exitcode'] = $this->exitcode;

		return $status;
	}

	function getPID() {
		$status = $this->getStatus();
		return $status['pid'];
	}

	function isRunning() {
		$status = $this->getStatus();
		return $status['running'];
	}

	function getExitCode() {
		return $this->exitcode;
	}

	// Wait for the process to exit
	function waitForExit()
	{
	        do {
	                usleep(100000);
	                $s = $this->getStatus();
	        } while($s !== false && $s['running'] == true);
	}


	// Send a message to the command running
	public function tell( $thought )
	{
		fwrite( $this->pipes[0], $thought );
	}

	// Get the command output produced since last read, as an array of lines
	public function read($pipe=1)
	{
		$buffer = array();

		while ($r = fgets($this->pipes[$pipe]))
		{
			$buffer[] = $r;
		}

		// Cache buffer
		$this->buffer = array_slice(array_merge($this->buffer, $buffer), -1 * $this->bufferlen, $this->bufferlen);

		return $buffer;
	}

	// Get buffered lines
	public function getBuffer() {
		return $this->buffer;
	}

	// What command wrote to STDERR
	function error()
	{
		return $this->read(2);
	}
}
