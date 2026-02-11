<?php

namespace ApiDev\Mysql;

use PDO;

/**
 * Database connection wrapper for PDO.
 *
 * Provides a simplified interface for executing SQL queries and managing
 * database connections with prepared statements.
 */
class Connection
{
    /**
     * @var PDO The PDO database connection instance
     */
    private PDO $pdo;

    /**
     * Creates a new Connection instance.
     *
     * @param PDO $pdo The PDO database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Builds a new Connection from database parameters.
     *
     * Creates a PDO connection with error handling and UTF-8 charset support.
     *
     * @param string $host The database host
     * @param int $port The database port
     * @param string $database The database name
     * @param string $username The database username
     * @param string $password The database password
     * @return Connection A new Connection instance
     */
    public static function build(
        string $host,
        int $port,
        string $database,
        string $username,
        string $password
    ): Connection {
        $dsn_parts = [
            "mysql:host={$host}",
            "port={$port}",
            "dbname={$database}",
            "charset=utf8mb4"
        ];
        $dsn = implode(';', $dsn_parts);
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return new self($pdo);
    }

    /**
     * Executes a prepared SQL query with parameters.
     *
     * @param string $sql The SQL query with placeholders
     * @param array $params The parameter values for the query
     * @return \PDOStatement The executed statement
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetches a single row from a query result.
     *
     * @param string $sql The SQL query with placeholders
     * @param array $params The parameter values for the query
     * @return array|false The result row as an associative array, or false if no row found
     */
    public function fetch(string $sql, array $params = []): array|false
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Fetches all rows from a query result.
     *
     * @param string $sql The SQL query with placeholders
     * @param array $params The parameter values for the query
     * @return array Array of result rows as associative arrays
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Executes a SQL statement and returns the number of affected rows.
     *
     * @param string $sql The SQL statement with placeholders
     * @param array $params The parameter values for the statement
     * @return int The number of affected rows
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Returns the ID of the last inserted row.
     *
     * Returns a string representation of the last insert ID.
     * Returns "0" if no insert has occurred.
     *
     * @return string The last insert ID as a string
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Returns the underlying PDO instance.
     *
     * @return PDO The PDO connection
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
