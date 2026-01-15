<?php

// tests_loader.php

require_once __DIR__ . '/lib/handlers/RequestHandler.php';
require_once __DIR__ . '/lib/http/HttpClientInterface.php';
require_once __DIR__ . '/lib/models/Response.php';

require_once __DIR__ . '/lib/Configuration.php';
require_once __DIR__ . '/lib/handlers/FileHandler.php';
require_once __DIR__ . '/lib/handlers/FixedFileHandler.php';
require_once __DIR__ . '/lib/handlers/MissingRequestHandler.php';
require_once __DIR__ . '/lib/handlers/ProxyRequestHandler.php';
require_once __DIR__ . '/lib/handlers/RequestPathValidator.php';
require_once __DIR__ . '/lib/handlers/StaticFileHandler.php';
require_once __DIR__ . '/lib/http/CurlHttpClient.php';
require_once __DIR__ . '/lib/models/FolderLocation.php';
require_once __DIR__ . '/lib/models/ForbiddenResponse.php';
require_once __DIR__ . '/lib/models/MissingResponse.php';
require_once __DIR__ . '/lib/models/Request.php';
require_once __DIR__ . '/lib/models/RequestMatcher.php';
require_once __DIR__ . '/lib/models/Rule.php';
require_once __DIR__ . '/lib/models/Server.php';
require_once __DIR__ . '/lib/service/RequestProcessor.php';
require_once __DIR__ . '/lib/utils/CurlUtils.php';
require_once __DIR__ . '/lib/utils/ContentType.php';
