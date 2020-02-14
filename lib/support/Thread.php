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
	var $buffer; // output buffer
	var $output;
	var $error;
	var $timeout;
	var $start_time;

	public function __construct($command)
	{
		$this->process = false;
		$this->pipes = array();

		$descriptor = array ( 0 => array ( "pipe", "r" ), 1 => array ( "pipe", "w" ), 2 => array ( "pipe", "w" ) );

		// Open the resource to execute $command
		$this->process = proc_open( $command, $descriptor, $this->pipes );

		// Set STDOUT and STDERR to non-blocking
		stream_set_blocking( $this->pipes[1], 0 );
		stream_set_blocking( $this->pipes[2], 0 );
	}

	// Close the process
	public function close()
	{
		$r = proc_close( $this->process );
		$this->process = false;
		return $r;
	}

	//Get the status of the current runing process
	function getStatus()
	{
		return proc_get_status( $this->process );
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

		return $buffer;
	}

	// What command wrote to STDERR
	function error()
	{
		return $this->read(2);
	}
}
