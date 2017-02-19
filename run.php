<?php

$serviceAccountEmail = getenv('ANALYTICS_MQTT_SERVICE_ACCOUNT_EMAIL');
$profile = getenv('ANALYTICS_MQTT_ANALYTICS_PROFILE_ID');

require_once 'vendor/autoload.php';
require_once 'vendor/bluerhinos/phpmqtt/phpMQTT.php';

class GoogleToMQTTServiceWrapper
{
    const SERVICE_NAME = 'Analytics-MQTT';

    private $serviceAccountEmail = null;
    private $profileId = null;
    private $analyticsRef = null;
    private $mqtt = null;

    function __construct($serviceAccountEmail, $profileId)
    {
        $this->serviceAccountEmail = $serviceAccountEmail;
        $this->profileId = $profileId;

        $this->mqtt = new phpMQTT('192.168.178.11', 1883, self::SERVICE_NAME);
    }

    /**
     * @return Google_Service_Analytics
     */
    protected function getService()
    {
        if (is_null($this->analyticsRef)) {
            $client = new Google_Client();
            $client->setApplicationName(self::SERVICE_NAME);
            $client->setAuthConfig(__DIR__ . '/auth.json');
            $client->addScope(Google_Service_Analytics::ANALYTICS_READONLY);
            $client->setSubject($this->serviceAccountEmail);

            $this->analyticsRef = new Google_Service_Analytics($client);

            if ($client->isAccessTokenExpired()) {
                $client->refreshTokenWithAssertion();
            }
        }

        return $this->analyticsRef;
    }

    public function publishResults($mqttPath, $startDate, $endDate, $metrics)
    {
        $data = $this->getService()->data_ga->get(
            'ga:' . $this->profileId,
            $startDate,
            $endDate,
            $metrics
        );

        return $this->publishValue($mqttPath, $this->parseResults($data));
    }

    /**
     * @param $metrics
     *
     * @return bool|int
     */
    public function publishRealtimeResults($mqttPath, $metrics)
    {
        $data = $this->getService()->data_realtime->get(
            'ga:' . $this->profileId,
            $metrics
        );

        return $this->publishValue($mqttPath, $this->parseResults($data));
    }

    /**
     * @param $results
     *
     * @return bool|int
     */
    protected function parseResults($results)
    {
        // Parses the response from the Core Reporting API and prints
        // the profile name and total sessions.
        if (count($results->getRows()) > 0) {
            // Get the entry for the first entry in the first row.
            $rows = $results->getRows();
            $value = $rows[0][0];

            return (int)$value > 0 ? (int)$value : false;
        } else {
            return false;
        }
    }

    protected function publishValue($path, $value)
    {
        if ($value && $this->mqtt->connect()) {
            $this->mqtt->publish($path, (string)$value);
            $this->mqtt->close();

            return $value;
        }

        return false;
    }
}

$obj = new GoogleToMQTTServiceWrapper(
    $serviceAccountEmail,
    $profile
);

echo $obj->publishResults('/Web/Analytics/Last7Days', '7daysAgo', 'today', 'ga:pageviews') . PHP_EOL;
echo $obj->publishResults('/Web/Analytics/Last28Days', '28daysAgo', 'today', 'ga:pageviews') . PHP_EOL;
echo $obj->publishRealtimeResults('/Web/Analytics/Realtime/ActiveUsers', 'rt:activeUsers') . PHP_EOL;