<?php

namespace Ensemble\Device\EnergyPlan;

use Ensemble\Schedule\SchedulerDevice;
use Ensemble\Util\Memoise;
use Exception;

class GivEnergyInverter {

    use Memoise;

    private GivEnergyAccount $account;
    private $iserial; // Givenergy inverter serial number
    private $idata; // Other inverter data

    // Maximum battery charge/discharge rate
    const BATTMAXCRATE = 2600;
    const BATTMAXDRATE = 2600;

    /**
     * Convert Amp Hours to KWh, for a given battery voltage.
     * The GE API uses amp-hours to return battery capacity.
     * @var Ensemble\Device\EnergyPlan\Ah2KWh
     */
    public static function Ah2KWh($voltage, $ah) {
        return $ah * $voltage / 1000;
    }

    /**
    * $inverterData
    */
    public function __construct(GivEnergyAccount $account, $inverterData) {
        $this->account = $account;
        $this->idata = $inverterData;
        $this->iserial = $this->idata->serial;
    }

    const SETTING_BATTMIN = 75;
    const SETTING_BATTCRATE = 72;
    const SETTING_BATTDRATE = 73;

    /**
     * Get setting information and values for the given setting IDs
     */
    public function getSetting($id) {
        $setting = $this->account->postJson("inverter/{$this->iserial}/settings/$id/read", ['context' => 'php_GivEnergyInverter']);
        return $setting['data']['value'];
    }

    /**
     * Set a setting
     */
    public function setSetting($id, $value) {
        $this->account->postJson("inverter/{$this->iserial}/settings/$id/write", ['context' => 'php_GivEnergyInverter', 'value' => $value]);
    }

    /**
     * Get aggregate battery capacity in kwh (actual, rather than nominal)
     */
    public function getBatteryCapacity() {
        $batts = $this->idata->connections->batteries;
        return array_reduce($batts, function($carry, $b) {
            return $carry + self::Ah2KWh($b->nominal_voltage, $b->capacity->full);
        }, 0);
    }

    /**
     * Get battery design capacity
     * @return int 
     */
    public function getBatteryDesignCapacity() {
        $batts = $this->idata->connections->batteries;
        return array_reduce($batts, function($carry, $b) {
            return $carry + self::Ah2KWh($b->nominal_voltage, $b->capacity->design);
        }, 0);
    }

    /**
     * Get battery health - currenty capacity as fraction of design capacity
     */
    public function getBatteryHealth () {
        return $this->getBatteryCapacity() / $this->getBatteryDesignCapacity();
    }

    /**
     * Get datapoints for the given date, or today if nothing is specified
     * $dateTS is a unix timestamp, from which a date is derived
     * @memoised
     */
    public function getData($dateTS=false) {
        if($dateTS === false) {
            $dateTS = time();
        }

        $date = date('Y-m-d', $dateTS);

        $data = $this->memoise("data-$date", 15, function() use ($date) {
            $data = $this->account->request("inverter/{$this->iserial}/data-points/{$date}");
        });
        
        return $data;
    }

    /**
     * Get the latest datapoints
     * @memoised
     */
    public function getLatestData() {
        $data = $this->memoise('latest-data', 15, function() {
            $data = $this->account->request("inverter/{$this->iserial}/system-data/latest")->data;
            return $data;
        });

        return $data;
    }

    const SECS_DAY = 24 * 60 * 60;

    /**
     * Get energy flow data between two dates
     * @param mixed $from 
     * @param mixed $to 
     * @return void 
     */
    public function getFlow($from=false, $to=false) : array {
     
        if($from === false && $to === false) {
            $to = time();
            $from = time() - self::SECS_DAY;
        } elseif($to == false) {
            $to = time();
        }

        // Round to days
        $from = floor($from / self::SECS_DAY) * self::SECS_DAY;
        $to = ceil($to / self::SECS_DAY) * self::SECS_DAY;
    
        // For longer periods, we need to make multiple requests
        $days = ($to - $from) / (3600 * 24);
        if($days > 20) {
            $data = [];

            for($d = $from; $d < $to; $d += 20 * 3600 * 24) {
                $data[] = $this->getFlow($d, min($to, $d + 20 * 24 * 3600));
            }

            $data = $this->mergeFlowData($data);

            return $data;
        }

        $fromDateTime = date('Y-m-d', $from);
        $toDateTime = date('Y-m-d', $to);
    
        $apiUrl = "inverter/{$this->iserial}/energy-flows";
    
        try {
            $args = [
                'start_time' => $fromDateTime,
                'end_time' => $toDateTime,
                'grouping' => GivEnergyAccount::GROUP_HALFHOURLY
            ];

            $response = $this->account->postJson($apiUrl, $args);
    
            $data = $response;
            
            $type = [
                0 => 'PV_Home',
                1 => 'PV_Battery',
                2 => 'PV_Grid',
                3 => 'Grid_Home',
                4 => 'Grid_Battery',
                5 => 'Battery_Home',
                6 => 'Battery_Grid'
            ];

            $output = [];
            foreach($data['data'] as $p=>$period)
            {
                $total = array_sum($period['data']);
                if($total == 0) continue; // Skip slots with no data

                $output[$p] = ['start' => $period['start_time'], 'end' => $period['end_time'], 'duration'=>null, 'data' => []];

                // Calculate slot duration; helpful to have for power calculations elsewhere (although it's always fixed for a given grouping, above)
                $output[$p]['duration'] = strtotime($output[$p]['end']) - strtotime($output[$p]['start']);

                // And age, so we can easily find recent data
                $output[$p]['age'] = time() - strtotime($output[$p]['end']);

                $flows = &$output[$p]['data'];
                foreach($period['data'] as $k=>$value) {
                    $flows[$type[$k]] = $value;
                }

                // Add some summaries
                $flows['PV_x'] = $flows['PV_Home'] + $flows['PV_Battery'] + $flows['PV_Grid'];
                $flows['Grid_x'] = $flows['Grid_Home'] + $flows['Grid_Battery'];
                $flows['Battery_x'] = $flows['Battery_Home'] + $flows['Battery_Grid'];
                
                $flows['x_Home'] = $flows['Battery_Home'] + $flows['Grid_Home'] + $flows['PV_Home'];
                $flows['x_Battery'] = $flows['PV_Battery'] + $flows['Grid_Battery'];
                $flows['x_Grid'] = $flows['PV_Grid'] + $flows['Battery_Grid'];
            }

            return $output;
        } catch (\Exception $e) {
            // Handle API errors
            throw new \Exception("Error fetching energy flow data: " . $e->getMessage());
        }

    }

    /**
     * Merge multiple flow-data arrays into one
     */
    protected function mergeFlowData($data) {
        foreach($data as $page) {
            foreach($page as $flow) {
                $output[$flow['start']] = $flow; // This removes duplicate slots :)
            }
        }

        return array_values($output); // Make keys numeric again
    }

    /**
     * Get total load now - load is what's being consumed by the house
     * @return void 
     */
    public function getCurrentLoad() {
        $data = $this->getLatestData();

        return $data->consumption;
    }


    /**
     * Get the charge in the battery, in KWh
     * @return void 
     */
    public function getBatterySOC() : float {
        $data = $this->getLatestData();

        return ($data->battery->percent / 100) * $this->getBatteryCapacity();
    }


    /**
     * Get the configured battery charge rate (max) in KW
     */
    public function getBatteryChargeRate() : float {
        return $this->memoise('battcrate', 7200, function() {
            return $this->getSetting(self::SETTING_BATTCRATE) / 1000;
        });
    }

    /**
     * Get the configured battery discharge rate (max) in KW
     * @return float
     */
    public function getBatteryDischargeRate() : float {
        return $this->memoise('battdrate', 7200, function() {
            return $this->getSetting(self::SETTING_BATTDRATE) / 1000;
        });
    }

    /**
     * Get configured max battery charge/discharge rates in KW - these are configured LOCALLY rather 
     * than on the inverter itself; see self::MAXBATTDRATE / self::BATTMAXCRATE
     */
    public function getBatteryMaxChargeRate() : float {
        return self::BATTMAXCRATE / 1000;
    }

    public function getBatteryMaxDischargeRate() : float {
        return self::BATTMAXDRATE / 1000;
    }

    /**
     * Get the configured battery minimum charge level in KWh
     * @return float 
     */
    public function getBatteryMinCharge() : float {
        return $this->memoise('battminc', 7200, function() {
            return ($this->getSetting(self::SETTING_BATTMIN) / 100) * $this->getBatteryCapacity();
        });
    }


    /**
     * Get current solar power
     */
    public function getSolarPower() : float {
        $data = $this->getLatestData();

        return $data->solar->power;
    }

    /**
     * Enable charging/discharging modes
     * 
     * Only one mode will be activated at once
     * Times must be passed in as timestamps
     * 
     * The number of KWh to discharge is applied by amending the duration, based on the configured charge/discharge power
     */
    protected function calcDuration($tsfrom, $tsto, $kwh, $kw) {

        if($kwh == 0) {
            return ['00:00', '00:00', 0];
        }

        $duration = min(($kwh / $kw)  * 3600, $tsto - $tsfrom);
        $tsto = $tsfrom + $duration;
        $from = date('H:i', $tsfrom);
        $to = date('H:i', $tsto);
        echo "{$kwh}KWh @ {$kw}KW => $duration seconds. $from -> $to \n";

        return [$from, $to, $duration];
    }

    // Calculate the charge/discharge power in WATTS required to charge/discharge the given number of KWh between the given times
    protected function calcPower($tsfrom, $tsto, $kwh) {
        $secs = $tsto - $tsfrom;

        // Ignore short periods
        if($secs < 10) {
            return 0;
        }

        $from = date('H:i', $tsfrom);
        $to = date('H:i', $tsto);

        $kw = $kwh * 1000 / ($secs / 3600);
        echo "(Dis)charge {$kwh}KWh $from->$to => {$kw}W\n";
        return [$from, $to, $kw];
    }

    const SETTING_ECOMODE_ENABLE = 24;
    const SETTING_DCDISCHARGE_ENABLE = 56;
    const SETTING_DCDISCHARGE_START = 53;
    const SETTING_DCDISCHARGE_END = 54;
    public function enableDischarge(int $tsfrom, int $tsto, float $kwh) {

        list($from, $to, $power) = $this->calcPower($tsfrom, $tsto, $kwh);

        $this->setSetting(self::SETTING_ACCHARGE_ENABLE, false); // Disable AC charging while in this mode; just in case
        $this->setSetting(self::SETTING_DCDISCHARGE_ENABLE, false);
        $this->setSetting(self::SETTING_ECOMODE_ENABLE, false); // Discharge doesn't work with eco mode enabled?
        sleep(1);
        $this->setSetting(self::SETTING_BATTDRATE, $power);
        $this->setSetting(self::SETTING_DCDISCHARGE_START, $from);
        $this->setSetting(self::SETTING_DCDISCHARGE_END, $to);
        $this->setSetting(self::SETTING_DCDISCHARGE_ENABLE, true);
    }

    const SETTING_ACCHARGE_ENABLE = 66;
    const SETTING_ACCHARGE_START = 64;
    const SETTING_ACCHARGE_END = 65;
    public function enableCharge(int $tsfrom, int $tsto, float $kwh) {

        list($from, $to, $power)  = $this->calcPower($tsfrom, $tsto, $kwh);

        $this->setSetting(self::SETTING_DCDISCHARGE_ENABLE, false); // Disable DC discharge; just in case
        $this->setSetting(self::SETTING_ACCHARGE_ENABLE, false);
        $this->setSetting(self::SETTING_ECOMODE_ENABLE, false);
        sleep(1);
        $this->setSetting(self::SETTING_BATTCRATE, $power);
        $this->setSetting(self::SETTING_ACCHARGE_START, $from);
        $this->setSetting(self::SETTING_ACCHARGE_END, $to);
        $this->setSetting(self::SETTING_ACCHARGE_ENABLE, true);
    }

    /**
     * Disable all charging and discharging
     */
    public function disableBattery() {
        $this->setSetting(self::SETTING_DCDISCHARGE_ENABLE, false);
        $this->setSetting(self::SETTING_ACCHARGE_ENABLE, false);
        $this->setSetting(self::SETTING_ECOMODE_ENABLE, false);
    }

    /**
     * Enable auto-charge/discharge mode
     */
    public function enableAuto() {
        $this->setSetting(self::SETTING_DCDISCHARGE_ENABLE, false);
        $this->setSetting(self::SETTING_ACCHARGE_ENABLE, false);
        $this->setSetting(self::SETTING_ECOMODE_ENABLE, true);
        $this->setSetting(self::SETTING_BATTCRATE, self::BATTMAXCRATE);
        $this->setSetting(self::SETTING_BATTDRATE, self::BATTMAXDRATE);
    }


}
