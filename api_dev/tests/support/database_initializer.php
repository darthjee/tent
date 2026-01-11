<?php

namespace ApiDev\Tests;

\ApiDev\Mysql\Configuration::configure(
    getenv('API_DEV_MYSQL_HOST') ?: 'localhost',
    getenv('API_DEV_MYSQL_TEST_DATABASE') ?: 'api_tent_test_db',
    getenv('API_DEV_MYSQL_USER') ?: 'root',
    getenv('API_DEV_MYSQL_PASSWORD') ?: '',
    getenv('API_DEV_MYSQL_PORT') ?: 3306
);