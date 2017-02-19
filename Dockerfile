FROM php:5.6

MAINTAINER Matthias Kleine <info@haus-automatisierung.com>

ENV DEBIAN_FRONTEND noninteractive
ENV TERM xterm

# Install dependencies
RUN apt-get update && apt-get upgrade -y --force-yes && apt-get install -y --force-yes --no-install-recommends apt-utils
RUN apt-get -y --force-yes install git vim

RUN mkdir -p /opt/analytics-mqtt && git clone https://github.com/klein0r/php-analytics-mqtt.git /opt/analytics-mqtt
RUN cd /opt/analytics-mqtt && php composer.phar install

WORKDIR /opt/analytics-mqtt

CMD watch -n 60 "php -d date.timezone=Europe/Berlin -f run.php"