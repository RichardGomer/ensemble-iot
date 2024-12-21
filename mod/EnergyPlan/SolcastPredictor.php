<?php

/**
 * Collect a solar forecast from solcast
 */

namespace Ensemble\Device\EnergyPlan;
use GuzzleHttp\Client;


class SolcastPredictor implements SolarEnergyPredictor {

    private $sckey, $scsite, $client;

    /**
    * $sckey - A solcast API key
    * $scsite - A solcast site ID
    */
    public function __construct($sckey, $scsite) {
        $this->sckey = $sckey;
        $this->scsite = $scsite;

        $this->client = $client = new Client([
            'timeout' => 15.0
        ]);
    }

    public function getSolarPrediction(): EnergySchedule
    {
        $device = $this;

        try {

            $lfile = './var/solcast.json'; // Local json source

            // Fetch data from the cache if possible, or make a request
            if(!file_exists($lfile) || time() - filemtime($lfile) > 3600) { // Refresh once per hour

                if(!touch($lfile)) {
                    throw new \Exception("Cannot create cache file for solcast data. Quitting until that's fixed to avoid API overage.");
                }

                $url = "https://api.solcast.com.au/rooftop_sites/{$device->scsite}/forecasts?format=json&api_key={$device->sckey}";
                echo "Solcast GET $url\n";
                $res = $this->client->request('GET', $url);
                $json = $res->getBody();
                file_put_contents($lfile, $json);

            } else {
                $json = file_get_contents($lfile);
            }

            $data = json_decode($json, true);

            // Convert the data to an EnergySchedule
            $s = new EnergySchedule();

            if(!is_array($data) || !array_key_exists('forecasts', $data)) {
                throw new \Exception("No forecast data in response");
            }

            foreach($data['forecasts'] as $period) {
                $end = strtotime($period['period_end']);
                $start = $end - 30 * 60;

                $s->setPeriod($start, $end, $period['pv_estimate'], false);
            }

            return $s;
        } catch (\Exception $e) {
            echo "Solcast failed: ".$e->getMessage()."\n";
            $s = new EnergySchedule();
            $s->setPeriods(floor(time() / 1800) * 1800, ceil((time() + 24 * 3600) / 1800) * 1800, 0);
            return $s;
        }
    }
}

class SolcastException extends \Exception {}
