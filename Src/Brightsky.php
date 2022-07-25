<?php
declare(strict_types=1);

namespace PhpWeather\Provider\Brightsky;

use DateInterval;
use DateTime;
use DateTimeZone;
use JetBrains\PhpStorm\ArrayShape;
use PhpWeather\Common\Source;
use PhpWeather\Exception\ServerException;
use PhpWeather\HttpProvider\AbstractHttpProvider;
use PhpWeather\Weather;
use PhpWeather\WeatherCollection;
use PhpWeather\WeatherQuery;

class Brightsky extends AbstractHttpProvider
{
    /**
     * @var Source[]|null
     */
    private ?array $sources = null;

    public function getSources(): array
    {
        if ($this->sources === null) {
            $this->sources = [
                new Source(
                    'brightsky',
                    'Bright Sky',
                    'https://brightsky.dev/'
                ),
                new Source(
                    'dwd',
                    'Deutscher Wetterdienst',
                    'https://www.dwd.de/'
                ),
            ];
        }

        return $this->sources;
    }

    /**
     * @throws ServerException
     */
    protected function mapRawData(float $latitude, float $longitude, array $rawData, ?string $type = null, ?string $units = null): Weather|WeatherCollection
    {
        if (!array_key_exists('weather', $rawData)) {
            throw new ServerException();
        }

        if ($type === Weather::CURRENT) {
            /** @var array<string, mixed> $weatherRawData */
            $weatherRawData = $rawData['weather'];

            return $this->mapItemRawdata($latitude, $longitude, $weatherRawData, $type);
        }

        $weatherCollection = new \PhpWeather\Common\WeatherCollection();
        foreach ($rawData['weather'] as $weatherRawData) {
            $weatherCollection->add($this->mapItemRawdata($latitude, $longitude, $weatherRawData));
        }

        return $weatherCollection;
    }

    protected function getCurrentWeatherQueryString(WeatherQuery $query): string
    {
        $queryArray = $this->getBaseQueryArray($query);

        return sprintf('https://api.brightsky.dev/current_weather?%s', http_build_query($queryArray));
    }

    protected function getForecastWeatherQueryString(WeatherQuery $query): string
    {
        $queryArray = $this->getBaseQueryArray($query);

        if ($query->getDateTime() !== null) {
            $queryArray['date'] = $query->getDateTime()->format('c');
        } else {
            $queryArray['date'] = date('c');
        }

        return sprintf('https://api.brightsky.dev/weather?%s', http_build_query($queryArray));
    }

    protected function getHistoricalWeatherQueryString(WeatherQuery $query): string
    {
        $queryArray = $this->getBaseQueryArray($query);

        $date = $query->getDateTime() ?? new DateTime();
        $lastDate = DateTime::createFromInterface($date)->add(new DateInterval('PT2H'));
        $queryArray['date'] = $date->format('c');
        $queryArray['last_date'] = $lastDate->format('c');

        return sprintf('https://api.brightsky.dev/weather?%s', http_build_query($queryArray));
    }

    protected function getHistoricalTimeLineWeatherQueryString(WeatherQuery $query): string
    {
        return $this->getForecastWeatherQueryString($query);
    }

    protected function mapUnits(string $units): string
    {
        if ($units === WeatherQuery::IMPERIAL) {
            return 'si';
        }

        return 'dwd';
    }

    /**
     * @param  array<string, mixed>  $weatherRawData
     * @return int|null
     */
    private function mapWeatherCode(array $weatherRawData): ?int
    {
        $icon = $weatherRawData['icon'];

        return match ($icon) {
            'clear-day', 'clear-night' => 0,
            'partly-cloudy-day', 'partly-cloudy-night' => 2,
            'cloudy' => 3,
            'fog' => 45,
            'rain' => 63,
            'snow' => 73,
            'thunderstorm' => 95,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $weatherRawData
     * @return string|null
     */
    private function mapIcon(array $weatherRawData): ?string
    {
        $icon = $weatherRawData['icon'];

        return match ($icon) {
            'clear-day' => 'day-sunny',
            'clear-night' => 'night-clear',
            'partly-cloudy-day' => 'day-cloudy',
            'partly-cloudy-night' => 'night-cloudy',
            'cloudy', 'rain', 'fog', 'snow', 'thunderstorm', 'sleet', 'hail' => $icon,
            'wind' => 'strong-wind',
            default => null,
        };
    }

    /**
     * @param  float  $latitude
     * @param  float  $longitude
     * @param  array<string, mixed>  $weatherRawData
     * @param  string|null  $type
     * @return Weather
     */
    private function mapItemRawdata(float $latitude, float $longitude, array $weatherRawData, ?string $type = null): Weather
    {
        $weatherData = (new \PhpWeather\Common\Weather())
            ->setLatitude($latitude)
            ->setLongitude($longitude);
        foreach ($this->getSources() as $source) {
            $weatherData->addSource($source);
        }

        $utcDateTime = (new DateTime())->setTimezone(new DateTimeZone('UTC'));
        $utcDateTime->setTimestamp(strtotime($weatherRawData['timestamp']));

        $weatherData->setUtcDateTime($utcDateTime);
        $weatherData->setType($type);
        if ($weatherData->getType() === null) {
            $now = new DateTime();
            if ($weatherData->getUtcDateTime() > $now) {
                $weatherData->setType(Weather::FORECAST);
            } else {
                $weatherData->setType(Weather::HISTORICAL);
            }
        }

        $weatherData->setTemperature($weatherRawData['temperature'])
            ->setHumidity($weatherRawData['relative_humidity'] / 100)
            ->setPressure($weatherRawData['pressure_msl']);
        if (array_key_exists('wind_speed', $weatherRawData)) {
            $weatherData->setWindSpeed($weatherRawData['wind_speed']);
        } elseif (array_key_exists('wind_speed_10', $weatherRawData)) {
            $weatherData->setWindSpeed($weatherRawData['wind_speed_10']);
        }
        if (array_key_exists('wind_direction', $weatherRawData)) {
            $weatherData->setWindDirection($weatherRawData['wind_direction']);
        } elseif (array_key_exists('wind_direction_10', $weatherRawData)) {
            $weatherData->setWindDirection($weatherRawData['wind_direction_10']);
        }
        if (array_key_exists('precipitation', $weatherRawData)) {
            $weatherData->setPrecipitation($weatherRawData['precipitation']);
        } elseif (array_key_exists('precipitation_10', $weatherRawData)) {
            $weatherData->setPrecipitation($weatherRawData['precipitation_10']);
        }
        $weatherData->setCloudCover($weatherRawData['cloud_cover']);

        $weatherData->setWeathercode($this->mapWeatherCode($weatherRawData));
        $weatherData->setIcon($this->mapIcon($weatherRawData));

        return $weatherData;
    }

    /**
     * @param  WeatherQuery  $query
     * @return array<string, mixed>
     */
    #[ArrayShape(['lat' => "float|null", 'lon' => "float|null", 'units' => "string"])] private function getBaseQueryArray(WeatherQuery $query): array
    {
        return [
            'lat' => $query->getLatitude(),
            'lon' => $query->getLongitude(),
            'units' => $this->mapUnits($query->getUnits()),
        ];
    }

}