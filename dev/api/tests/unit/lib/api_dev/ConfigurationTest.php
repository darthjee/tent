<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\Configuration;
use ApiDev\RouteConfiguration;
use ApiDev\HealthCheckEndpoint;

require_once __DIR__ . '/../../../support/tests_loader.php';

class ConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        Configuration::reset();
    }

    protected function tearDown(): void
    {
        Configuration::reset();
    }

    public function testGetInstanceReturnsSameInstance()
    {
        $instance1 = Configuration::getInstance();
        $instance2 = Configuration::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function testAddAddsRouteConfiguration()
    {
        Configuration::add('GET', '/health', HealthCheckEndpoint::class);

        $configurations = Configuration::getConfigurations();

        $this->assertCount(1, $configurations);
        $this->assertInstanceOf(RouteConfiguration::class, $configurations[0]);
    }

    public function testAddMultipleRouteConfigurations()
    {
        Configuration::add('GET', '/health', HealthCheckEndpoint::class);
        Configuration::add('POST', '/api', HealthCheckEndpoint::class);
        Configuration::add('PUT', '/users', HealthCheckEndpoint::class);

        $configurations = Configuration::getConfigurations();

        $this->assertCount(3, $configurations);
    }

    public function testGetConfigurationsReturnsEmptyArrayInitially()
    {
        $configurations = Configuration::getConfigurations();

        $this->assertIsArray($configurations);
        $this->assertCount(0, $configurations);
    }

    public function testResetClearsInstance()
    {
        Configuration::add('GET', '/health', HealthCheckEndpoint::class);
        $this->assertCount(1, Configuration::getConfigurations());

        Configuration::reset();

        $configurations = Configuration::getConfigurations();
        $this->assertCount(0, $configurations);
    }

    public function testResetCreatesNewInstance()
    {
        $instance1 = Configuration::getInstance();
        Configuration::reset();
        $instance2 = Configuration::getInstance();

        $this->assertNotSame($instance1, $instance2);
    }

    public function testConfigurationsPersistAcrossGetInstanceCalls()
    {
        Configuration::add('GET', '/health', HealthCheckEndpoint::class);

        $instance1 = Configuration::getInstance();
        $instance2 = Configuration::getInstance();

        $this->assertCount(1, $instance1->fetchConfigurations());
        $this->assertCount(1, $instance2->fetchConfigurations());
        $this->assertSame($instance1->fetchConfigurations(), $instance2->fetchConfigurations());
    }

    public function testAddRouteDirectlyOnInstance()
    {
        $instance = Configuration::getInstance();
        $instance->addRoute('GET', '/health', HealthCheckEndpoint::class);

        $configurations = $instance->fetchConfigurations();

        $this->assertCount(1, $configurations);
        $this->assertInstanceOf(RouteConfiguration::class, $configurations[0]);
    }

    public function testFetchConfigurationsReturnsArray()
    {
        $instance = Configuration::getInstance();
        $instance->addRoute('GET', '/health', HealthCheckEndpoint::class);

        $configurations = $instance->fetchConfigurations();

        $this->assertIsArray($configurations);
    }
}
