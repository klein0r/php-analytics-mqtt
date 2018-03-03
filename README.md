# Google Analytics -> MQTT

## Description

This class helps you to push your Google Analytics Data to an MQTT broker (like Mosquitto)

## Requirements

- Google Analytics Account
- MQTT Broker
- PHP >= 5.6

## Setup

- Create a google service user and set the email to environment variable ANALYTICS_MQTT_SERVICE_ACCOUNT_EMAIL
- Copy the auth.json file to the root directory of this installation / beside run.php 
- Allow that user to access your Google Analytics Data and set the property id to environment variable ANALYTICS_MQTT_ANALYTICS_PROFILE_ID
- Setup MQTT and set the broker IP/hostname to environment variable ANALYTICS_MQTT_BROKER_SERVER

## Docker

You can use docker to run this task. See Dockerfile. It will use watch to push your data every 60 seconds to MQTT

This is an example of a docker-compose configuration

```
version: '2'

services:
    analytics-mqtt:
        build: .
        environment:
            - ANALYTICS_MQTT_SERVICE_ACCOUNT_EMAIL=account-123@*.iam.gserviceaccount.com
            - ANALYTICS_MQTT_ANALYTICS_PROFILE_ID=12345678
            - ANALYTICS_MQTT_BROKER_SERVER=192.168.1.100
            - ANALYTICS_MQTT_BROKER_USER=mqttUsername
            - ANALYTICS_MQTT_BROKER_PASSWORD=T0pSecr3t
        volumes:
            - ./auth.json:/opt/analytics-mqtt/auth.json
```