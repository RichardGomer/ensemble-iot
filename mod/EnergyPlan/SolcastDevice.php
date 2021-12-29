<?php

/**
 * Collect a solar forecast from solcast
 */

namespace Ensemble\Device\EnergyPlan;
use Ensemble\Schedule;
use Ensemble\Async as Async;
use GuzzleHttp\Client;


class SolcastDevice extends Schedule\SchedulerDevice {

    /**
    * $name - A name for this device
    * $sckey - A solcast API key
    * $scsite - A solcast site ID
    */
    public function __construct($name, $sckey, $scsite) {
        $this->name = $name;

        $this->sckey = $sckey;
        $this->scsite = $scsite;

        $this->client = $client = new Client([
            'timeout' => 15.0
        ]);
    }

    public function reschedule() {
        $device = $this;

        try {
            $url = "https://api.solcast.com.au/rooftop_sites/{$device->scsite}/forecasts?format=json&api_key={$device->sckey}";
            echo "Solcast GET $url\n";
            $res = $this->client->request('GET', $url);
            $json = $res->getBody();
            $data = json_decode($json, true);

            $s = new Schedule\Schedule();

            if(!array_key_exists('forecasts', $data)) {
                throw new Exception("No forecast data in response");
            }

            foreach($data['forecasts'] as $period) {
                $end = strtotime($period['period_end']);
                $start = $end - 30 * 60;

                $s->setPeriod($start, $end, $period['pv_estimate'], false);
            }

            echo $s->prettyPrint();

            return $s;
        } catch (Exception $e) {
            echo "Solcast failed: ".$e->getMessage()."\n";
        }
    }
}
