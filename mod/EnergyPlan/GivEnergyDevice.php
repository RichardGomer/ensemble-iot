<?php

<?php

/**
 * Collect a solar forecast from solcast
 */

namespace Ensemble\Device\EnergyPlan;
use Ensemble\Schedule;
use Ensemble\Async as Async;
use GuzzleHttp\Client;
use Ensemble\BasicDevice;

class GivEnergyDevice extends BasicDevice {

    /**
    * $name - A name for this device
    * $apiKey - GivEnergy API key
    * $inverterSerial - Inverter serial number
    */
    public function __construct($name, $apiKey, $inverterSerial) {
        $this->gekey = $apiKey;
        $this->geinv = $inverterSerial;

        $this->client = $client = new Client([
            'timeout' => 15.0
        ]);
    }

    protected function request($url, $page=1) {
        $base = 'https://api.givenergy.cloud/v1';

        $response = $client->get(
            $base.$url,
            [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->gekey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'page'=> $page,
                    'pageSize' => 100
                ],
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
    protected function requestAll($url, $max=20) {
        $page = 1;
        do {
            $res = $this->request($url, $page);
            $out[] = $res;
        } while();
    }

    /**
     * Get datapoints for the given date, or today if nothing is specified
     * $dateTS is a unix timestamp, from which a date is derived
     */
    public function getData($dateTS=false) {
        if($dateTS === false) {
            $dateTS = time();
        }

        $date = date('Y-m-d');

        $json = $this->request("inverter/{$this->geinv}/data-points/{$date}");

    }

    public function getFlow($from, $to) {

    }

    /**
     * From this main device, you can derive sub devices that collect specific
     * data. Most are SchedulerDevice, and return timeseries data for a specific
     * aspect of the system
     */

    /**
     * Get the battery device
     @return GivEnergyBatteryDevice
     */
    public function getBatterySOCDevice() {

    }

    /**
     * Get the solar meter device
     */
    public function getSolarMeterDevice() {

    }

    /**
     * Get the household meter device
     */
    public function getHouseMeterDevice() {

    }

    /**
     * Get the grid export meter device
     */
    public function getGridExportMeterDevice() {

    }

    /**
     * Get the grid import meter device
     */
    public function getGridImportMeterDevice() {

    }
}

class GivEnergySubDevice extends Schedule\SchedulerDevice {
    protected function reschedule() {

    }
}

class GivEnergyBatterySOCDevice extends GivEnergySubDevice {

}
