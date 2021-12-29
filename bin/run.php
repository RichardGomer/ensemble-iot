<?php

namespace Ensemble;

ini_set('memory_limit', '256M');

require dirname(__DIR__).'/lib/setup.php';

$broker = $conf['broker'] = new CommandBroker(); // Expose the broker during config

// Load device config, if any, based on IP address
$configured = false;
if(!$args['config']) {
    do { // Keep trying until IP auto-config works; this allows time for the network interface to come up
        $ips = getIPs();
        foreach($ips as $ip) {
            if(file_exists($cfn = dirname(__DIR__)."/config/{$ip}.php")) {
                echo "Loaded config {$cfn} based on IP address\n";
                include($cfn);
                $configured = true;
                break;
            }
        }

        if(!$configured) {
            echo "WARNING: No IP-based configuration was found for ".implode(" or ", $ips).". Will try again momentarily.\n";
            sleep(10);
        }
    } while(!$configured);
} else {
    $fn = $args['config'];
    if(file_exists($cfn = dirname(__DIR__)."/config/{$fn}.php")) {
        echo "Loaded specified configuration, {$cfn}\n";
        include($cfn);
    } else {
        echo "Config file '$cfn' does not exist\n";
        exit;
    }
}

/**
 * TODO: Allow multiple brokers to be started; each in a thread.
 * If $conf[] is just an integer-indexed array, then treat each key as a
 * separate config array. For each one:
 * Set a config number; spawn a new thread; have the child thread kick off the
 * broker using the configured parameters.
 * The main thread will just wait until the others are done.
 * Each thread will have its own remote delivery device etc.
 *
 * Options: have threads share the incoming queue but only pick up their own
 * commands? Or use the main thread to split them up somehow? JSONQueue would
 * need to be device-aware to do filtering; otherwise peek/shift don't make sense
 * COULD: Use the main thread as a router; and have the child threads do all routing
 * through it (i.e they have only a default endpoint, receive no announcements).
 * But take their announcements into our own device map (that would happen automatically
 * anyway; as they announce to us) and do routing via a new kind of endpoint - shmop?, jsonqueue?
 *
 * Check: Each thread will do its own announcements, but to a single HTTP
 * endpoint. So check that that works (and that they don't e.g. overwrite
 * one anothers announcements)
 *
 *
 */

/**
 * Start a single broker using the defined config in $conf
 */
function startBroker($conf) {
  // Record runtime status information
  $status = new Storage\JsonStore('devicestatus');
  $status->proc = getmypid();
  $status->started = time();
  $status->ips = $ips = System\IP::getIPs();

  $inputQueue = $conf['queue_in'];
  $remoteQueue = $conf['queue_remote'];
  $deviceMap = $conf['device_map'];


  $broker->setRemoteQueue($remoteQueue);
  $broker->setInputQueue($inputQueue);

  // The announcement device tells other endpoints about our local devices
  // so that they can route commands to them
  $announce = new Device\AnnouncerDevice($conf['endpoint_url'], array(), $deviceMap);
  $broker->addDevice($announce);

  // If there's a default endpoint configured, we'll announce to it
  if($conf['default_endpoint'] !== false) {
      $announce->addRemote($conf['default_endpoint']);
  }

  // ** DISABLE DIRECT LOCAL DELIVERY **
  // Direct local delivery can be disabled, in which case the message broker
  // will push all messages via the remote queue. We need to announce to ourself
  // so that those messages can be routed into our own endpoint.
  if($conf['disable-direct-local']) {
      $broker->disableDirectLocalDelivery();
      $announce->addRemote($conf['endpoint_url']);
  }

  // A remote delivery device is a special device that delivers outgoing commands
  // to remote endpoints
  $remote = new Device\RemoteDeliveryDevice($remoteQueue, $deviceMap);
  $remote->setDefaultEndpoint($conf['default_endpoint']);
  $broker->addDevice($remote); // Add the remote delivery device

  // Register defined devices
  foreach($conf['devices'] as $d) {

      $dn = $d->getDeviceName();

      if(is_array($args['rundevices']) && count($args['rundevices']) > 0 && !in_array($dn, $args['rundevices'])) {
          echo "Skip device ".$dn;
          continue;
      }

      echo "Added device $dn\n";
      $broker->addDevice($d);
  }

  $broker->run();
}

startBroker($conf);
