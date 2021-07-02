<?php


class Linktap {

    protected $key = '';
    protected $base_url = 'https://www.link-tap.com/api/';
    protected $curl;
    protected $taps = array(
        'front_garden' => '<DEVICE ID>',
    );
    protected $gateway = '<GATEWAY ID>';
    protected $username = '<USERNAME>';

    protected $ch;
    protected $fh;
    protected $cache_file = 'cache.json';

    public function __construct() {
        $this->fh = fopen('linktap.log', 'a');
    }

    private function log($data) {
        $fh = fopen('linktap.log', 'a');
        $now = date('d-m-Y h:i:s');
        fwrite($this->fh, $now . " :: " . $data . PHP_EOL);
        fclose($fh);
    }

    private function post($data, $endpoint) {
        //add our never changing variables
        $data['username'] = $this->username;
        $data['apiKey'] = $this->key;

        //build our curl object
        $ch = curl_init($this->base_url . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $this->log("Posting data to $endpoint");
        $response = json_decode(curl_exec($ch));
        if ($response->result != 'ok') {
            $this->log('ERROR: ' . $response->message);
        }

        return $response;
    }

    public function getAllDevices() {
        $data = array();
        // if my cache file is older than 5 minutes, plus a bit extra, refresh the data
        // The API rate limits to one request per hour
        if (file_exists($this->cache_file) && (time() - filemtime($this->cache_file)) < 5 * 61) {
            $this->log("Load from cache " . (time() - filemtime($this->cache_file)));
            $response = json_decode(file_get_contents($this->cache_file));
        } else {
            $this->log('load from api');
            $response = $this->post($data, 'getAllDevices');
            file_put_contents($this->cache_file, json_encode($response));
        }
        return $response;
    }

    public function getWorkMode($tap) {
        $all_devices = $this->getAllDevices();
        if ($all_devices->result == 'error') {
            var_dump($all_devices->result);
        }
        foreach ($all_devices->devices as $device) {
            foreach ($device->taplinker as $taplinker) {
                if ($taplinker->taplinkerId == $this->taps[$tap]) {
                    $this->log('Getting current workMode for ' . $tap);
                    return $taplinker->workMode;
                }
            }
        }
    }

    public function getWateringStatus($tap) {
        $status_cache_file = 'watering.json';
        if (file_exists($status_cache_file) && (time() - filemtime($status_cache_file)) < 30) {
                $this->log("Load from watering cache " . (time() - filemtime($status_cache_file)));
                $response = json_decode(file_get_contents($status_cache_file));
        } else {
            $data = array(
                'taplinkerId' => $this->taps[$tap],
            );
            $response = $this->post($data, 'getWateringStatus');
            file_put_contents($status_cache_file, json_encode($response));
        }
        return $response->status;
    }

    public function checkWateringStatus($tap) {
        $all_devices = $this->getAllDevices();
        if ($all_devices->result == 'error') {
            var_dump($all_devices->message);
        }
        foreach ($all_devices->devices as $device) {
            foreach ($device->taplinker as $taplinker) {
                if ($taplinker->taplinkerId == $this->taps[$tap]) {
                    return $taplinker->watering;
                }
            }
        }
    }

    // workMode: currently activated work mode. ‘O’ is for Odd-Even Mode, ‘M’ is for Instant Mode, ‘I’ is for Interval Mode, ‘T’ is for 7-Day Mode, ‘Y’ is for Month
    public function setWorkMode($mode, $tap) {
        $this->log('Setting WorkMode for ' . $tap . ' to ' . $mode);
        switch ($mode) {
            case 'I':
                $this->activateIntervalMode($tap);
                break;
            case 'O':
                $this->activateOddEvenMode($tap);
                break;
            case 'Y':
                $this->activateMonthMode($tap);
                break;
            case 'T':
                $this->activateSevenDayMode($tap);
                break;
        }

    }

    public function startInstantMode($duration, $tap) {
        $data = array(
            'gatewayId' => $this->gateway,
            'taplinkerId' => $this->taps[$tap],
            'action' => 'true',
            'duration' => $duration,
        );
        $response = $this->post($data, 'activateInstantMode');
        return $response;
    }

    public function stopInstantMode($tap) {
        // Lets get the current workmode, so we can get it again after turning it off.
        // Sending an instant off puts the taplinker into instant mode, reverting any timers or schedules
        $workmode = $this->getWorkMode($tap);
        $data = array(
            'gatewayId' => $this->gateway,
            'taplinkerId' => $this->taps[$tap],
            'action' => 'false',
            'duration' => 0,
        );
        $response = $this->post($data, 'activateInstantMode');
        $this->setWorkMode($workmode, $tap);
        return $response;
    }

    public function activateSevenDayMode($tap) {
        $data = array(
            'gatewayId' => $this->gateway,
            'taplinkerId' => $this->taps[$tap],
        );
        $response = $this->post($data, 'activateSevenDayMode');
        if ($response->result == 'error' && $response->message == 'The minimum interval of calling this API is 5 minute.') {
            echo 'Hit rate limit ... waiting ...' . PHP_EOL;
            sleep(310);
            $response = $this->post($data, 'activateSevenDayMode');
        }
        return $response;
    }

    public function activateMonthMode($tap) {
        $data = array(
            'gatewayId' => $this->gateway,
            'taplinkerId' => $this->taps[$tap],
        );
        $response = $this->post($data, 'activateMonthMode');
        if ($response->result == 'error' && $response->message == 'The minimum interval of calling this API is 5 minute.') {
            sleep(310);
            $response = $this->post($data, 'activateSevenDayMode');
        }
        return $response;
    }

    public function activateIntervalMode($tap) {
        $data = array(
                'gatewayId' => $this->gateway,
                'taplinkerId' => $this->taps[$tap],
        );
        $response = $this->post($data, 'activateIntervalMode');
        if ($response->result == 'error' && $response->message == 'The minimum interval of calling this API is 5 minute.') {
                sleep(310);
                $response = $this->post($data, 'ativateIntervalMode');
        }
        return $response;
    }

    public function activateOddEvenMode($tap) {
        $data = array(
            'gatewayId' => $this->gateway,
            'taplinkerId' => $this->taps[$tap],
        );
        $response = $this->post($data, 'activateOddEvenMode');
        if ($response->result == 'error' && $response->message == 'The minimum interval of calling this API is 5 minute.') {
            sleep(310);
            $response = $this->post($data, 'activateSevenDayMode');
        }
        return $response;
    }
}
