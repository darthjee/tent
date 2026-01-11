<?php

// tests_loader.php
require_once __DIR__ . '/../../source/lib/mysql/Migration.php';
require_once __DIR__ . '/../../source/lib/mysql/ModelConnection.php';
require_once __DIR__ . '/../../source/lib/mysql/MigrationsProcessor.php';
require_once __DIR__ . '/../../source/lib/mysql/Configuration.php';
require_once __DIR__ . '/../../source/lib/mysql/Connection.php';

require_once __DIR__ . '/../../source/lib/api_dev/models/Person.php';
require_once __DIR__ . '/../../source/lib/api_dev/endpoints/ListPersonsEndpoint.php';

require_once __DIR__ . '/database_initializer.php';
