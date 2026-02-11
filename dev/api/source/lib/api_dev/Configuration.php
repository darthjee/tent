<?php

namespace ApiDev;

/**
 * Singleton configuration manager for route registration.
 *
 * Maintains a centralized registry of route configurations for the application.
 * Provides static methods for registering and retrieving route configurations.
 */
class Configuration
{
    /**
     * @var Configuration|null Singleton instance
     */
    private static $instance;

    /**
     * @var array Array of RouteConfiguration instances
     */
    private $configurations = [];

    /**
     * Returns the singleton instance of Configuration.
     *
     * @return Configuration The singleton instance
     */
    public static function getInstance(): Configuration
    {
        if (self::$instance === null) {
            self::$instance = new Configuration();
        }
        return self::$instance;
    }

    /**
     * Adds a new route configuration to the registry.
     *
     * @param string|null $requestMethod The HTTP method to match (e.g., GET, POST)
     * @param string|null $path The URL path to match
     * @param string $endpoint The fully qualified class name of the endpoint handler
     * @return void
     */
    public static function add(?string $requestMethod, ?string $path, string $endpoint): void
    {
        self::getInstance()->addRoute($requestMethod, $path, $endpoint);
    }

    /**
     * Returns all registered route configurations.
     *
     * @return array Array of RouteConfiguration instances
     */
    public static function getConfigurations(): array
    {
        return self::getInstance()->fetchConfigurations();
    }

    /**
     * Resets the singleton instance (useful for testing).
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Adds a route configuration to the internal registry.
     *
     * @param string|null $requestMethod The HTTP method to match
     * @param string|null $path The URL path to match
     * @param string $endpoint The endpoint class name
     * @return void
     */
    public function addRoute(?string $requestMethod, ?string $path, string $endpoint): void
    {
        $this->configurations[] = new RouteConfiguration($requestMethod, $path, $endpoint);
    }

    /**
     * Returns the registered route configurations.
     *
     * @return array Array of RouteConfiguration instances
     */
    public function fetchConfigurations(): array
    {
        return $this->configurations;
    }
}
