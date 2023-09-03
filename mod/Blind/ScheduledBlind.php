<?php

/**
 * Schedule blinds, including position based on sun position
 */

namespace Ensemble\Device\Blind;
use Ensemble\MQTT;
use Ensemble\Async;

class ScheduledBlind extends MQTT\Tasmota {

    private $driver;
    private bool $reverse = false;

    public function __construct($name, MQTT\Bridge $client, $deviceName, \Ensemble\Device\ContextPointer $schedule) {
        parent::__construct($name, $client, $deviceName);

        $last = null;
        $this->driver = new \Ensemble\Schedule\Driver($this, function($device, $ext) use (&$last) {

            if($ext == 'auto') {
                $ext = $this->getAutoSetting();
            } elseif($ext == 'dusk') {
                $ext = $this->getDuskSetting();
            } elseif($ext == 'dawn') {
                $ext = $this->getDawnSetting();
            }

            $ext = round($ext / 2, 0) * 2; // Round to nearest 2
            if($ext === $last) // Only send values when they change
                return;
            $last = $ext;

            if($this->reverse) {
                $ext = 100 - $ext;
            }

            $device->send($this->topic_command.'TuyaSend2', "2,$ext"); // DpID 2 sets position; TuyaSend2 sends an int to the DpID
        }, $schedule);
    }

    /**
     * For blinds that are working in reverse; because what a PITA it is changing this through tasmota!
     */
    public function setReverse($reverse = true) {
        $this->reverse = $reverse;
    }

    public function getChildDevices() {
        return array($this->driver);
    }

    /*
     * These settings control 'auto' setting. Auto will extend the blind to prevent direct
     * sunlight reaching a viewer at a nominal position behind the window.
     *
     */

    // Location of the window
    public $lat = 50.9288;
    public $lng = -1.3372; // West End Parish Centre, because why not

    // Minimum and maximum extension of the blind
    public $extMin = 0;
    public $extMax = 100;

    // Direction the window faces degrees from north
    public $bearing = 240;

    // Altitude of the horizon in degrees; e.g. where hills occlude the sun before
    // the theoretical horizon
    public $horizon = 0;

    // Vertical position of the window, and vertical distance of viewer from TOP of window
    public $winHeight = 100;
    public $vPos = 80;

    // Horizontal distance of the viewer from the window
    public $distance = 50;

    // The width of the window, and the distance of the viewer from the left hand side of the window
    public $winWidth = 160;
    public $hPos = 50;

    /**
     * Get blind setting for automatic mode at the given timestamp (defaults to now)
     * The properties above ^^ must be configured correctly!
     */
    public function getAutoSetting($timestamp=false) {

        if($timestamp === false) {
            $timestamp = time();
        }

        $bearing = ($this->bearing / 180) * M_PI; // Convert bearing into radians

        // Calculate the angle between the viewer and the top of the window
        $vAngleMax = atan($this->vPos / $this->distance);
        $vAngleMin = atan(($this->vPos - $this->winHeight ) / $this->distance);

        // And the angle between the viewer and the edges of the window
        $hAngleMin = atan($this->hPos / $this->distance);
        $hAngleMax = atan(($this->winWidth - $this->hPos) / $this->distance);

        // Effective H angle for the viewer is the window bearing minus the view angle
        $hAngleMin = $bearing - $hAngleMin;
        $hAngleMax = $bearing + $hAngleMax;

        // Get sun position
        $dt = new \DateTime(date('Y-m-d H:i:s', $timestamp));
        $sc = new \AurorasLive\SunCalc($dt, $this->lat, $this->lng);
        $sunPos = $sc->getSunPosition();
        $sunAzimuth = $sunPos->azimuth + M_PI; // Our bearing is vs north; but sun position is relative to S; so flip 180deg
        $sunAltitude = $sunPos->altitude;

        $azOutRange = $sunAzimuth < $hAngleMin || $sunAzimuth > $hAngleMax;

        echo $dt->format("Y-m-d H:i   ").sprintf("Sun: Az %.2f Alt %.2f    Window: H( %.2f %.2f )  V( %.2f %.2f )   ",
        $sunAzimuth * 180/M_PI, $sunAltitude * 180/M_PI, $hAngleMin * 180/M_PI, $hAngleMax * 180/M_PI, $vAngleMin * 180/M_PI, $vAngleMax * 180/M_PI)
        .($azOutRange ? "Azimuth out of range" : "")."\n";

        // If the sun is at an azimuth outside the range that shines through the window, the blind position is at minimum shade
        if($azOutRange) {
            //echo "Azimuth outside range\n";
            return $this->extMin;
        }

        // If the sun is below the Horizon, set the blind to max
        if($sunAltitude < $this->horizon) {
            return $this->extMax;
        }

        // Scale blind shade based on altitude
        $diff = $vAngleMax - max(0, $sunAltitude); // Once sun goes below horizon, no need to go lower
        $range = $vAngleMax - $vAngleMin;

        $ext = min(100, max(0, ($diff / $range) * ($this->extMax - $this->extMin) + $this->extMin));

        return $ext;
    }

    /**
     * dusk mode closes the blind at dusk
     */
     public function getDuskSetting($timestamp=false) {
         if($timestamp === false) {
             $timestamp = time();
         }

         $sunset = date_sunset($timestamp, SUNFUNCS_RET_TIMESTAMP, $this->lat, $this->lng, 96);
         echo "Sunset is at $sunset\n";

         if($timestamp < $sunset) {
             return $this->extMin;
         } else {
             return $this->extMax;
         }
     }

     /**
      * dawn mode opens the blind at dawn
      */
      public function getDawnSetting($timestamp=false) {
          if($timestamp === false) {
              $timestamp = time();
          }

          $sunrise = date_sunrise($timestamp, SUNFUNCS_RET_TIMESTAMP, $this->lat, $this->lng, 89);
          echo "Sunrise is at $sunrise\n";

          if($timestamp < $sunrise) {
              return $this->extMax;
          } else {
              return $this->extMin;
          }
      }

}
