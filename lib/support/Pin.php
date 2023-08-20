<?php

namespace Ensemble\GPIO;

// IF the newer GPIO tools are installed, load the PHP bindings for those instead
if(file_exists('/usr/bin/gpioset')) {
	require 'Pin2.php';
} else {
	require 'Pin1.php';
}
