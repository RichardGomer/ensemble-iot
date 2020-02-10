<?php

/**
 * A simple multi-channel data logger
 */

namespace Ensemble\Log;


/**
 * This is a text logger, it just writes lines to a log file
 */
class TextLog
{
    protected $filename;
    function __construct($filename, $prefix='') {
        $this->filename = $filename;
        $this->prefix = $prefix;
    }

    function log($str) {
        $prefix = $this->prefix == '' ? '' : ' ('.$this->prefix.')';
        $l = date('[Y-m-d H:i:s]').$prefix.' '.$str."\n";
        file_put_contents($this->filename, $l, FILE_APPEND);
    }
}
