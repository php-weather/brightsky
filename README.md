# PHP Weather Provider for Bright Sky

![Packagist Version](https://img.shields.io/packagist/v/php-weather/brightsky)  
![GitHub Release Date](https://img.shields.io/github/release-date/php-weather/brightsky)
![GitHub commits since tagged version](https://img.shields.io/github/commits-since/php-weather/brightsky/0.1.0)
![GitHub last commit](https://img.shields.io/github/last-commit/php-weather/brightsky)  
![GitHub Workflow Status](https://img.shields.io/github/workflow/status/php-weather/brightsky/PHP%20Composer)
![GitHub](https://img.shields.io/github/license/php-weather/brightsky)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/php-weather/brightsky)

This is the [Bright Sky](https://brightsky.dev/) provider from PHP Weather.

> Bright Sky is an open-source project aiming to make some of the more popular data — in particular weather observations from the DWD station network and weather forecasts from the MOSMIX model — available in a free, simple JSON API.

## Installation

Via Composer

```shell
composer require php-weather/brightsky
```

## Usage

```php
$httpClient = new \Http\Adapter\Guzzle7\Client();
$brightSky = new \PhpWeather\Provider\Brightsky\Brightsky($httpClient);

$latitude = 47.873;
$longitude = 8.004;

$currentWeatherQuery = \PhpWeather\Common\WeatherQuery::create($latitude, $longitude);
$currentWeather = $brightSky->getCurrentWeather($currentWeatherQuery);
```