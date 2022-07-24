<?php
declare(strict_types=1);

namespace PhpWeather\Provider\Brightsky;

use Http\Client\HttpClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PhpWeather\Common\WeatherQuery;
use PhpWeather\Exception\Exception;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class BrightSkyTest extends TestCase
{
    private MockObject|HttpClient $client;
    private MockObject|RequestFactoryInterface $requestFactory;
    private Brightsky $provider;

    public function setUp(): void
    {
        $this->client = $this->createMock(HttpClient::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);

        $this->provider = new Brightsky($this->client, $this->requestFactory);
    }

    /**
     * @throws Exception
     */
    public function testCurrentWeather(): void
    {
        $latitude = 47.873;
        $longitude = 8.004;
        $testQuery = WeatherQuery::create($latitude, $longitude);
        $testString = 'https://api.brightsky.dev/current_weather?lat=47.8739259&lon=8.0043961&units=dwd';

        $request = $this->createMock(RequestInterface::class);
        $this->requestFactory->expects(self::once())->method('createRequest')->with('GET', $testString)->willReturn($request);

        $responseBodyString = file_get_contents(__DIR__.'/resources/currentWeather.json');
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('getBody')->willReturn($responseBodyString);
        $this->client->expects(self::once())->method('sendRequest')->with($request)->willReturn($response);

        $currentWeather = $this->provider->getCurrentWeather($testQuery);
        self::assertSame($latitude, $currentWeather->getLatitude());
        self::assertSame(32.1, $currentWeather->getTemperature());
        self::assertSame('day-cloudy', $currentWeather->getIcon());
        self::assertCount(2, $currentWeather->getSources());
    }

}