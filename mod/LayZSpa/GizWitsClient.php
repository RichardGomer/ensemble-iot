<?php

namespace Ensemble\Device\LayZSpa;

use GuzzleHttp\Client;

/**
 * Control Lay-z-spa wifi-connected devices using the official GizWits API
 * 
 * This file implements generic support for the GizWits API and the Bestway Airjet device type
 * see LayZSpaDevice.php for the ensemble-iot binding
 */
class GizWitsClient
{
    public $apiLoginEndpoint =   "https://euapi.gizwits.com/app/login";
    public $apiStatusEndpoint =  "https://euapi.gizwits.com/app/devdata/";
    public $apiControlEndpoint = "https://euapi.gizwits.com/app/control/";
    public $apiBindingEndpoint = "https://euapi.gizwits.com/app/bindings";
    public $apiApplicationId = "98754e684ec045528b073876c34c7348";

    private $apiUserToken = false, $apiUserId = false, $apiTokenExpires = 0;
    private $email = false, $password = false;

    private $deviceId = false;

    public function login($email, $password) {
        $res = $this->makeRequest($this->apiLoginEndpoint, 'POST', 
            [ 'username' => $email, 'password' => $password, 'lang' => 'en' ]
        );

        $this->email = $email;
        $this->password = $password;

        $this->apiUserToken = $res['token'];
        $this->apiUserId = $res['uid'];
        $this->apiTokenExpires = $res['expire_at'];
    }

    private $devices = [];
    public function getDevices() {
        $res = $this->makeRequest($this->apiBindingEndpoint, 'GET');
        
        $this->devices = [];

        foreach($res['devices'] as $d) {
            $this->devices[] = GizWitsDevice::createFromSpec($this, $d);
        }

        return $this->devices;
    }

    /**
     * Get a specific device by product key
     */
    public function getDeviceByProductKey($pk) {
        foreach($this->devices as $d) {
            if($d->getProductKey() == $pk) {
                return $d;
            }
        }

        throw new \Exception("Device with product key '$pk' was not found");
    }

    public function __construct()
    {

    }

    public function makeRequest($url, $method, $payload = [])
    {
        $client = new Client();

        $headers = [
            'Content-Type' => 'application/json',
            'X-Gizwits-Application-Id' => $this->apiApplicationId
        ];

        if($this->apiUserToken !== false) {
            if($this->apiTokenExpires <= time() - 30) {
                $this->login($this->email, $this->password);
            }
            $headers['X-Gizwits-User-token'] = $this->apiUserToken;
        }

        $options = [
            'headers' => $headers,
            'json' => $payload,
            'debug' => false
        ];

        $response = $client->request($method, $url, $options);

        return json_decode($response->getBody()->getContents(), true);
    }
}

class GizWitsDevice {

    public static function createFromSpec(GizWitsClient $client, $spec) {

        // Try to determine the product type, or fallback to a generic device type
        switch($spec['product_name']) {
            case 'Airjet':
                return new BestwaySpa($client, $spec['did'], $spec['product_key'], $spec);
                break;
            default:
                return new self($client, $spec['did'], $spec['product_key'], $spec);
                break;
        }
        
    }

    private GizWitsClient $client;
    private $deviceId = false;
    private $prodKey = false;
    private $info = [];

    private $status = [];
    private $statusFetchTime = 0;
    private $statusCacheTime = 60; // How often to update status information

    protected final function __construct(GizWitsClient $client, $deviceId, $prodId, $info=[]) {
        $this->client = $client;
        $this->deviceId = $deviceId;
        $this->prodKey = $prodId;
        $this->info = $info;
    }

    public function getDeviceId() {
        return $this->deviceId;
    }

    public function getProductKey() {
        return $this->prodKey;
    }

    public function getInfo() {
        return $this->info;
    }

    public function getStatus($key=false) {
        if($this->statusFetchTime < time() - $this->statusCacheTime) {
            $all = $this->client->makeRequest($this->client->apiStatusEndpoint.'/'.$this->deviceId.'/latest', 'GET');
            $this->status = $all['attr'];
            $this->statusFetchTime = time();
            var_Dump($this->status);
        }

        if($key === false)
            return $this->status;
        
        if(array_key_exists($key, $this->status)) {
            return $this->status[$key];
        } else {
            throw new \Exception("Status key '$key' not found");
        }
    }

    protected function sendControl($args=[]) {
        return $this->client->makeRequest($this->client->apiControlEndpoint.'/'.$this->deviceId, 'POST', ['attrs'=>$args]);
    }
}

class BestwaySpa extends GizWitsDevice {

    // Enable heater (also enables filter)
    public function on() {
        $this->sendControl(['power' => 1]); // Turn on main power
        $this->sendControl(['filter_power' => 1]); // Turn on filter/pump
        $this->sendControl(['heat_power' => 1]); // Turn on heater
    }

    // Disable heater; leaves filter on
    public function off() {
        $this->sendControl(['heat_power' => 0]); // Turn off heater
    }

    public function filterIsOn() {
        return $this->getStatus('filter_power') == 1;
    }

    public function heaterIsOn() {
        return $this->getStatus('heat_power') == 1;
    }

    public function getDesiredTemp() {
        return $this->getStatus('temp_set');
    }

    public function getCurrentTemp() {
        return $this->getStatus('temp_now');
    }

    // Set desired temperature
    public function setTemp($temp) {
        $temp = min(40, max(5, $temp)); // Temp must be between 5 and 40
        $this->sendControl(['temp_set' => $temp]);
    }

}