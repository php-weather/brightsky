<?php
declare(strict_types=1);

namespace PhpWeather\Provider\Brightsky;

use DateInterval;
use DateTime;
use DateTimeZone;
use PhpWeather\Common\Source;
use PhpWeather\Common\UnitConverter;
use PhpWeather\Constants\Type;
use PhpWeather\Constants\Unit;
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

    /**
     * @throws ServerException
     */
    protected function mapRawData(float $latitude, float $longitude, array $rawData, ?string $type = null, ?string $units = null): Weather|WeatherCollection
    {
        if (!array_key_exists('weather', $rawData)) {
            throw new ServerException();
        }

        if ($type === Type::CURRENT) {
            /** @var array<string, mixed> $weatherRawData */
            $weatherRawData = $rawData['weather'];

            return $this->mapItemRawdata($latitude, $longitude, $weatherRawData, $type, $units);
        }

        $weatherCollection = new \PhpWeather\Common\WeatherCollection();
        foreach ($rawData['weather'] as $weatherRawData) {
            $weatherCollection->add($this->mapItemRawdata($latitude, $longitude, $weatherRawData, null, $units));
        }

        return $weatherCollection;
    }

    /**
     * @param  float  $latitude
     * @param  float  $longitude
     * @param  array<string, mixed>  $weatherRawData
     * @param  string|null  $type
     * @param  string|null  $units
     * @return Weather
     */
    private function mapItemRawdata(float $latitude, float $longitude, array $weatherRawData, ?string $type = null, ?string $units = null): Weather
    {
        if ($units === null) {
            $units = Unit::METRIC;
        }

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
                $weatherData->setType(Type::FORECAST);
            } else {
                $weatherData->setType(Type::HISTORICAL);
            }
        }

        $weatherData->setTemperature(UnitConverter::mapTemperature($weatherRawData['temperature'], Unit::TEMPERATURE_CELSIUS, $units));
        $weatherData->setDewPoint(UnitConverter::mapTemperature($weatherRawData['dew_point'], Unit::TEMPERATURE_CELSIUS, $units));
        $weatherData->setHumidity($weatherRawData['relative_humidity']);
        $weatherData->setPressure(UnitConverter::mapPressure($weatherRawData['pressure_msl'], Unit::PRESSURE_HPA, $units));
        if (array_key_exists('wind_speed', $weatherRawData)) {
            $weatherData->setWindSpeed(UnitConverter::mapSpeed($weatherRawData['wind_speed'], Unit::SPEED_KMH, $units));
        } elseif (array_key_exists('wind_speed_10', $weatherRawData)) {
            $weatherData->setWindSpeed(UnitConverter::mapSpeed($weatherRawData['wind_speed_10'], Unit::SPEED_KMH, $units));
        }
        if (array_key_exists('wind_direction', $weatherRawData)) {
            $weatherData->setWindDirection($weatherRawData['wind_direction']);
        } elseif (array_key_exists('wind_direction_10', $weatherRawData)) {
            $weatherData->setWindDirection($weatherRawData['wind_direction_10']);
        }
        if (array_key_exists('precipitation', $weatherRawData)) {
            $weatherData->setPrecipitation(UnitConverter::mapPrecipitation($weatherRawData['precipitation'], Unit::PRECIPITATION_MM, $units));
        } elseif (array_key_exists('precipitation_10', $weatherRawData)) {
            $weatherData->setPrecipitation(UnitConverter::mapPrecipitation($weatherRawData['precipitation_10'], Unit::PRECIPITATION_MM, $units));
        }
        $weatherData->setCloudCover($weatherRawData['cloud_cover']);

        $weatherData->setWeathercode($this->mapWeatherCode($weatherRawData));
        $weatherData->setIcon($this->mapIcon($weatherRawData));

        return $weatherData;
    }

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

    protected function getCurrentWeatherQueryString(WeatherQuery $query): string
    {
        $queryArray = $this->getBaseQueryArray($query);

        return sprintf('https://api.brightsky.dev/current_weather?%s', http_build_query($queryArray));
    }

    /**
     * @param  WeatherQuery  $query
     * @return array{'lat': float|null, 'lon': float|null, 'units': string}
     */
    private function getBaseQueryArray(WeatherQuery $query): array
    {
        return [
            'lat' => $query->getLatitude(),
            'lon' => $query->getLongitude(),
            'units' => 'dwd',
        ];
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

}