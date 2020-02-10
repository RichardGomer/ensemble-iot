<?php

/**
 * Functions for working with system information
 */

namespace Ensemble\System;

// Get an array of IPv4 addresses assigned to our network interfaces
class IP {
    public static function getIPs() {
        $out = array();
        $data = exec("ip address | grep \"inet \"", $out);
        $ips = array();
        foreach($out as $line) {
            if(preg_match('/^inet ([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\/([0-9]{1,2})/', trim($line), $matches))
                $ips[] = $matches[1];
        }

        $outips = array();
        // Scrub loopback IPs
        foreach($ips as $n=>$ip) {
            if(!preg_match('/^127\./', $ip)) {
                $outips[] = $ip;
            }
        }

        return $outips;
    }
}
