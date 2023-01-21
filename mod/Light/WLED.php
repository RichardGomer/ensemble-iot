<?php

namespace Ensemble\Device\Light;
use Ensemble\Device\BasicDevice;
use Ensemble\Async;
use Ensemble\Schedule;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class WLED extends BasicDevice implements RGBWCT {

    private $ip; // IP address of device

    public function __construct($name, $ip) {
        $this->name = $name;

        $this->ip = $ip;
    }

    public function getPollInterval() {
        return 0;
    }

    /**
     * Set the current scheme using a piece of settings json
     */
    public function setScheme($json) {
        if(is_string($json)) {
            $json = json_decode($json);
        }

        $this->applyJSON($json);
    }

    private $on = false;

    public function on() {
        $this->on = true;
        $this->applyJSON(['on'=>true]);
    }

    public function off() {
        $this->on = false;
        $this->applyJSON(['on'=>false]);
    }

    public function setRGB($r, $g, $b) {
        $this->applyJSON([
            'seg' => [
                [
                    'id' => 0,
                    'col' => [ [ $r, $g, $b ] ]
                ]
            ]
        ]);
    }

    /**
     * Convert Tasmota CT (in mireds) into RGB values for WLED
     */
    public function setCT($ct) {

        $k = 1000000 / $ct;

        $temp = $k / 100;
        if ($temp < 66) {
            $r = 255;
            $g = $temp - 2;
            $g = -155.25485562709179 - 0.44596950469579133 * $g + 104.49216199393888 * log($g);
            $b = $temp - 10;
            $b = $temp < 20 ? 0 : -254.76935184120902 + 0.8274096064007395 * $b + 115.67994401066147 * log($b);
        } else {
            $r = $temp - 55;
            $r = 351.97690566805693 + 0.114206453784165 * $r - 40.25366309332127 * log($r);
            $g = $temp - 50;
            $g = 325.4494125711974 + 0.07943456536662342 * $g - 28.0852963507957 * log($g);
            $b = 255;
        }

        echo "CONVERT {$ct}m => {$k}K => $r,$g,$b\n";

        $this->setRGB(floor($r), floor($g), floor($b));
    }

    /**
     * Set brightness only, using a percentage (0-100)
     */
    public function setBrightness($percent) {
        $int = round($percent * 2.55, 0);

        $this->applyJSON(['bri' => $int, 'on'=>$this->on]);
    }

    protected function applyJSON($object) {
        $url = "http://{$this->ip}/json/state";

        $client = new Client();

        $res = $client->post($url, [
            RequestOptions::JSON => $object,
            RequestOptions::CONNECT_TIMEOUT => 0.5 // Short timeout to prevent blocking the thread for too long
        ]);

        $json = json_encode($object);

        echo "WLED POST {$json} {$res->getStatusCode()} {$res->getReasonPhrase()}\n";
    }

}
