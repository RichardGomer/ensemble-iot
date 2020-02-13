<?php

/**
 * Load config and libraries
 */

namespace Ensemble;
use Garden\Cli\Cli;

/**
 * Basic configuration
 * Set some common environment stuff up; this is shared between the API and the
 * daemon
 */
require 'setup-common.php';

// Command line options
if(php_sapi_name() === 'cli') {
    $cli = new Cli();
    $cli->description("Run the ensemble-iot daemon")
    ->opt('local-ep:l', 'Specify the URL of our local endpoint, disables auto-guessing', false)
    ->opt('default-ep:d', 'Specify the URL of the default remote endpoint', false)
    ->opt('disable-direct-local', 'Disable direct local delivery, ie force all messages via endpoint (mostly for testing)', false, 'boolean')
    ->opt('config:c', 'The name of the config file (in ./config/) to use, excluding .php suffix; disables IP-based auto-loading', false);
    $args = $cli->parse($argv);
} else {
    $args = array(
        'local-ep' => false,
        'default-ep' => false,
        'disable-direct-local' => false,
        'config' => false
    );
}

$conf['disable-direct-local'] = false; // If true, the CommandBroker will be configured to route all messages via the local endpoint, mostly useful for testing

if($args['disable-direct-local']) {
    $conf['disable-direct-local'] = true;
}

function getIPs() {
    $ips = System\IP::getIPs();
    if(count($ips) < 1) {
            echo "Can't detect local IP addresses\n";
            exit;
    }
    return $ips;
}

if(!$args['local-ep']) {
    $conf['endpoint_url'] = 'http://'.getIPS()[0].'/ensemble-iot/1.0/'; // Endpoint URL is the URL of our own local API endpoint; we try to auto-configure it
} else {
    $conf['endpoint_url'] = $args['local-ep'];
}

if(!$args['default-ep']) {
    $conf['default_endpoint'] = false; // Where we send commands for unknown devices, false to disable default route
} else {
    $conf['default_endpoint'] = $args['default-ep'];
}

$conf['devices'] = array(); // Put device modules in here

// Load device config, if any, based on IP address
if(!$args['config']) {
    $ips = getIPs();
    foreach($ips as $ip) {
        if(file_exists($cfn = dirname(__DIR__)."/config/{$ip}.php")) {
            include($cfn);
            break;
        }
    }
} else {
    $fn = $args['config'];
    if(file_exists($cfn = dirname(__DIR__)."/config/{$fn}.php")) {
        include($cfn);
    } else {
        echo "Config file '$cfn' does not exist\n";
        exit;
    }
}
