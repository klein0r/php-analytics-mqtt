<?php

$serviceAccountEmail = getenv('ANALYTICS_MQTT_SERVICE_ACCOUNT_EMAIL');
$profile = getenv('ANALYTICS_MQTT_ANALYTICS_PROFILE_ID');
$mqttBroker = getenv('ANALYTICS_MQTT_BROKER_SERVER');
$mqttUser = getenv('ANALYTICS_MQTT_BROKER_USER') ?: null;
$mqttPassword = getenv('ANALYTICS_MQTT_BROKER_PASSWORD') ?: null;

require_once 'vendor/autoload.php';
require_once 'vendor/bluerhinos/phpmqtt/phpMQTT.php';

class GoogleToMQTTServiceWrapper
{
    const SERVICE_NAME = 'Analytics-MQTT';

    private $serviceAccountEmail = null;
    private $profileId = null;
    private $analyticsRef = null;

    private $mqtt = null;
    private $mqttUser = null;
    private $mqttPassword = null;

    function __construct($serviceAccountEmail, $profileId, $mqttBroker, $mqttUser = null, $mqttPassword = null)
    {
        if (!$serviceAccountEmail) {
            throw new InvalidArgumentException('Service Account Mail cannot be empty');
        }

        if (!$profileId) {
            throw new InvalidArgumentException('Profile Id cannot be empty');
        }

        if (!$mqttBroker) {
            throw new InvalidArgumentException('MQTT Broker cannot be empty');
        }

        $this->serviceAccountEmail = $serviceAccountEmail;
        $this->profileId = $profileId;

        $this->mqtt = new \Bluerhinos\phpMQTT($mqttBroker, 1883, self::SERVICE_NAME);
        $this->mqttUser = $mqttUser;
        $this->mqttPassword = $mqttPassword;
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
                $client->fetchAccessTokenWithAssertion();
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
        if ($value && $this->mqtt->connect(true, null, $this->mqttUser, $this->mqttPassword)) {
            $this->mqtt->publish($path, (string)$value);
            $this->mqtt->close();

            return $value;
        }

        return false;
    }
}

$obj = new GoogleToMQTTServiceWrapper(
    $serviceAccountEmail,
    $profile,
    $mqttBroker,
    $mqttUser,
    $mqttPassword
);

echo $obj->publishResults('/Web/Analytics/Last7Days', '7daysAgo', 'today', 'ga:pageviews') . PHP_EOL;
echo $obj->publishResults('/Web/Analytics/Last28Days', '28daysAgo', 'today', 'ga:pageviews') . PHP_EOL;
echo $obj->publishRealtimeResults('/Web/Analytics/Realtime/ActiveUsers', 'rt:activeUsers') . PHP_EOL;