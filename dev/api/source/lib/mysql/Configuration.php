<?php

namespace ApiDev\Mysql;

use ApiDev\Mysql\Connection;

/**
 * Singleton configuration manager for database connections.
 *
 * Manages database connection configuration and provides factory methods
 * for creating connections and managing databases.
 */
class Configuration
{
    /**
     * @var Configuration|null Singleton instance
     */
    private static $instance;

    /**
     * @var string The database host
     */
    private $host;

    /**
     * @var string The database name
     */
    private $database;

    /**
     * @var string The database username
     */
    private $username;

    /**
     * @var string The database password
     */
    private $password;

    /**
     * @var int The database port
     */
    private $port;

    /**
     * @var Connection|null Cached database connection
     */
    private $connection;

    /**
     * Returns the singleton Configuration instance.
     *
     * @return Configuration|null The singleton instance, or null if not configured
     */
    public static function getInstance(): ?Configuration
    {
        return self::$instance;
    }

    /**
     * Configures the database connection settings.
     *
     * Creates and stores the singleton Configuration instance with the provided settings.
     *
     * @param string $host The database host
     * @param string $database The database name
     * @param string $username The database username
     * @param string $password The database password
     * @param int $port The database port (default: 3306)
     * @return Configuration The configured instance
     */
    public static function configure(
        string $host,
        string $database,
        string $username,
        string $password,
        int $port = 3306
    ): Configuration {
        self::$instance = new Configuration($host, $database, $username, $password, $port);
        return self::$instance;
    }

    /**
     * Returns a database connection using the configured settings.
     *
     * @return Connection The database connection
     */
    public static function connect(): Connection
    {
        $config = self::getInstance();
        return $config->getConnection();
    }

    /**
     * Returns the database connection, creating it if necessary.
     *
     * @return Connection The database connection
     */
    public function getConnection(): Connection
    {
        if ($this->connection === null) {
            $this->connection = Connection::build(
                $this->host,
                $this->port,
                $this->database,
                $this->username,
                $this->password
            );
        }
        return $this->connection;
    }

    /**
     * Creates a database connection without specifying a database name.
     *
     * Useful for creating databases or performing operations that don't require
     * a specific database to be selected.
     *
     * @param string $host The database host
     * @param string $username The database username
     * @param string $password The database password
     * @param int $port The database port (default: 3306)
     * @return Connection The database connection
     */
    public static function connectWithoutDatabase(
        string $host,
        string $username,
        string $password,
        int $port = 3306
    ): Connection {
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return new \ApiDev\Mysql\Connection($pdo);
    }

    /**
     * Creates a database if it doesn't already exist.
     *
     * @param string $host The database host
     * @param string $username The database username
     * @param string $password The database password
     * @param int $port The database port
     * @param string $databaseName The name of the database to create
     * @return void
     */
    public static function createDatabase(
        string $host,
        string $username,
        string $password,
        int $port,
        string $databaseName
    ): void {
        $conn = self::connectWithoutDatabase($host, $username, $password, $port);
        $command = "CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        $conn->getPdo()->exec($command);
    }

    /**
     * Checks if a database exists.
     *
     * @param string $host The database host
     * @param string $username The database username
     * @param string $password The database password
     * @param int $port The database port
     * @param string $databaseName The database name to check
     * @return bool True if the database exists, false otherwise
     */
    public static function databaseExists(
        string $host,
        string $username,
        string $password,
        int $port,
        string $databaseName
    ): bool {
        $conn = self::connectWithoutDatabase($host, $username, $password, $port);
        $command = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$databaseName}'";
        $stmt = $conn->getPdo()->query($command);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Ensures a database exists, creating it if necessary.
     *
     * @param string $host The database host
     * @param string $username The database username
     * @param string $password The database password
     * @param int $port The database port
     * @param string $databaseName The database name
     * @return void
     */
    public static function ensureDatabaseExists(
        string $host,
        string $username,
        string $password,
        int $port,
        string $databaseName
    ): void {
        if (!self::databaseExists($host, $username, $password, $port, $databaseName)) {
            self::createDatabase($host, $username, $password, $port, $databaseName);
        }
    }

    /**
     * Creates a new Configuration instance.
     *
     * @param string $host The database host
     * @param string $database The database name
     * @param string $username The database username
     * @param string $password The database password
     * @param int $port The database port (default: 3306)
     */
    public function __construct(string $host, string $database, string $username, string $password, int $port = 3306)
    {
        $this->host = $host;
        $this->database = $database;
        $this->username = $username;
        $this->password = $password;
        $this->port = $port;
    }

    /**
     * Returns the configured database host.
     *
     * @return string The database host
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Returns the configured database name.
     *
     * @return string The database name
     */
    public function getDatabase(): string
    {
        return $this->database;
    }

    /**
     * Returns the configured database username.
     *
     * @return string The database username
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Returns the configured database password.
     *
     * @return string The database password
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * Returns the configured database port.
     *
     * @return int The database port
     */
    public function getPort(): int
    {
        return $this->port;
    }
}
