<?php

/**
* Forecast Device collects a weather forecast from the Met Office and sends it
* to a context device
*/

/**
 * Weather type codes: (Datapoint 'W' field)
 *
 *    Value	Description
 *    NA	Not available
 *    0	    Clear night
 *    1	    Sunny day
 *    2	    Partly cloudy (night)
 *    3	    Partly cloudy (day)
 *    4	    Not used
 *    5	    Mist
 *    6	    Fog
 *    7	    Cloudy
 *    8 	Overcast
 *    9	    Light rain shower (night)
 *    10	Light rain shower (day)
 *    11	Drizzle
 *    12	Light rain
 *    13	Heavy rain shower (night)
 *    14	Heavy rain shower (day)
 *    15	Heavy rain
 *    16	Sleet shower (night)
 *    17	Sleet shower (day)
 *    18	Sleet
 *    19	Hail shower (night)
 *    20	Hail shower (day)
 *    21	Hail
 *    22	Light snow shower (night)
 *    23	Light snow shower (day)
 *    24	Light snow
 *    25	Heavy snow shower (night)
 *    26	Heavy snow shower (day)
 *    27	Heavy snow
 *    28	Thunder shower (night)
 *    29	Thunder shower (day)
 *    30	Thunder
 */

namespace Ensemble\Device\Forecast;
use Ensemble\Async;
use GuzzleHttp\Client;

class ForecastDevice extends Async\Device {

    /**
    * $devicename - A name for this device
    * $dpkey - A metoffice datapoint API key
    * $dploc - A metoffice datapoint location number
    */
    public function __construct($devicename, $dpkey, $dploc, $contextdevice, $fieldprefix) {
        $this->name = $devicename;

        $this->contextdevice = $contextdevice;
        $this->dpkey = $dpkey;
        $this->dploc = $dploc;
        $this->fieldprefix = $fieldprefix;

        $this->includefields = array(
                'T', 'G', 'H', 'S', 'Pp', 'W'
        );

        $this->interval = 3600 * 3;

        $this->client = $client = new Client([
            'timeout' => 15.0
        ]);
    }

    protected function getRoutine() {
        $device = $this;
        return new Async\Lambda(function() use ($device) {
            $start = time();
            try {
                $url = "http://datapoint.metoffice.gov.uk/public/data/val/wxfcs/all/json/{$this->dploc}?res=3hourly&key={$this->dpkey}";
                echo "Forecast GET $url\n";
                $res = $this->client->request('GET', $url);
                $json = $res->getBody();
                $log = json_decode($json, true);

                $periods = $log['SiteRep']['DV']['Location']['Period'];
                $temps = array();

                foreach($periods as $day) {
                    $date = $day['value'];

                    // Today's forecast normally contains less than 8 periods, because some are in the past!
                    // We need to compensate for that
                    $firstOffset = (8 - count($day['Rep'])) * 3600 * 3;

                    $tsbase = strtotime($date) + $firstOffset;

                    //echo "$date = $tsbase\n";

                    $time = $tsbase;
                    foreach($day['Rep'] as $step) {
                        //echo "@ $time:\n";
                        foreach($step as $f=>$v) {
                            if(in_array($f, $this->includefields)) { // Only include specific fields
                                $fields[$f][$time] = $v;
                                //echo "   $f: $v\n";
                            }
                        }
                        $time += 3600 * 3;
                    }
                }

                $broker = $device->getBroker();
                foreach($fields as $f=>$series) {
                    $cmd = \Ensemble\Command::create($device, $device->contextdevice, 'updateContext');
                    $cmd->setArg('field', $device->fieldprefix.$f);
                    $cmd->setArg('series', $series);
                    $this->getBroker()->send($cmd);
                }

                // Wait until the next reschedule is due
                yield new Async\waitUntil($start + $this->interval);

            } catch (Exception $e) {
                echo "Forecast failed: ".$e->getMessage()."\n";
            }
        });
    }

}
