<?php

/**
 * Connect to GivEnergy solar PV/battery system
 */

namespace Ensemble\Device\EnergyPlan;

use Ensemble\Schedule;
use Ensemble\Async as Async;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Ensemble\BasicDevice;
use Exception;

class GivEnergyAccount {
    private Client $client; // Guzzle client

    private $gekey; // Givenergy API key
    private $username; // Givenergy account username

    const GROUP_HALFHOURLY = 0;
    const GROUP_DAILY = 1;
    const GROUP_MONTHLY = 2;
    const GROUP_YEARLY = 3;
    const GROUP_TOTAL = 4;

    public function __construct($apiKey, $username)
    {
        $this->username = $username;
        $this->gekey = $apiKey;

        $this->client = $client = new Client([
            'timeout' => 15.0
        ]);
    }

    protected $base = 'https://api.givenergy.cloud/v1/';

    protected function getHeaders() {
        return [
            'Authorization' => 'Bearer '.$this->gekey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Make a get request for a paginated resource
     * @param mixed $url 
     * @param int $page 
     * @return mixed 
     * @throws Exception 
     */
    public function request($url, $page=1) {

        $response = $this->client->get(
            $this->base.$url,
            [
                'headers' => $this->getHeaders(),
                'query' => [
                    'page'=> $page,
                    'pageSize' => 100
                ]
            ]
        );

        $json = $response->getBody();

        if(!($res = json_decode($json))) {
            throw new \Exception("Cannot decode JSON from GivEnergy API");
        }

        return $res;
    }

    /**
     * Request the given URL and retrieve all pages of the collection
     */
    public function requestAll($url, $max=20) {
        $page = 1;
        do {
            $res = $this->request($url, $page);
            $out[] = $res;

            $lastPage = $res['meta']['last_page'];
            $page++;
        } while($page <= $lastPage);

        return $out;
    }

    /**
     * Make a POSt request with a JSON body
     */
    public function postJson($url, $args) {

            $response = $this->client->post($this->base.$url, [
                'headers' => $this->getHeaders(),
                RequestOptions::JSON => $args
            ]);

            $json = $response->getBody();

            if(!($res = json_decode($json, true))) {
                throw new \Exception("Cannot decode JSON from GivEnergy API");
            }

            return $res;
    }

    /**
     * Get an array of inverters from the account
     * This doesn't work because you aren't allowed to list the inverters on your
     * own account through the API.
     */
    public function getInverters() : array {
        $data = $this->request("communication-device");

        $inverters = [];
        foreach($data->data as $dongle) {
            $idata = $dongle->inverter;
            $inverters[] = new GivEnergyInverter($this, $idata);
        }

        return $inverters;
    }
}


