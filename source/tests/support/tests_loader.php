<?php

// tests_loader.php

require_once __DIR__ . '/../../source/lib/handlers/RequestHandler.php';
require_once __DIR__ . '/../../source/lib/http/HttpClientInterface.php';
require_once __DIR__ . '/../../source/lib/models/Response.php';

require_once __DIR__ . '/../../source/lib/Configuration.php';
require_once __DIR__ . '/../../source/lib/handlers/FileHandler.php';
require_once __DIR__ . '/../../source/lib/handlers/FixedFileHandler.php';
require_once __DIR__ . '/../../source/lib/handlers/MissingRequestHandler.php';
require_once __DIR__ . '/../../source/lib/handlers/ProxyRequestHandler.php';
require_once __DIR__ . '/../../source/lib/handlers/StaticFileHandler.php';
require_once __DIR__ . '/../../source/lib/http/CurlHttpClient.php';
require_once __DIR__ . '/../../source/lib/models/FolderLocation.php';
require_once __DIR__ . '/../../source/lib/models/ForbiddenResponse.php';
require_once __DIR__ . '/../../source/lib/models/MissingResponse.php';
require_once __DIR__ . '/../../source/lib/models/Request.php';
require_once __DIR__ . '/../../source/lib/models/RequestMatcher.php';
require_once __DIR__ . '/../../source/lib/models/Rule.php';
require_once __DIR__ . '/../../source/lib/models/Server.php';
require_once __DIR__ . '/../../source/lib/service/RequestProcessor.php';
require_once __DIR__ . '/../../source/lib/utils/CurlUtils.php';
require_once __DIR__ . '/../../source/lib/utils/ContentType.php';
