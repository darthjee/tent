<?php

// tests_loader.php

require_once __DIR__ . '/lib/request_handlers/RequestHandler.php';
require_once __DIR__ . '/lib/http/HttpClientInterface.php';
require_once __DIR__ . '/lib/models/Response.php';
require_once __DIR__ . '/lib/models/RequestInterface.php';

require_once __DIR__ . '/lib/Configuration.php';
require_once __DIR__ . '/lib/exceptions/FileNotFoundException.php';
require_once __DIR__ . '/lib/exceptions/InvalidFilePathException.php';
require_once __DIR__ . '/lib/request_handlers/MissingRequestHandler.php';
require_once __DIR__ . '/lib/request_handlers/ProxyRequestHandler.php';
require_once __DIR__ . '/lib/request_handlers/StaticFileHandler.php';
require_once __DIR__ . '/lib/http/CurlHttpClient.php';
require_once __DIR__ . '/lib/middlewares/Middleware.php';
require_once __DIR__ . '/lib/middlewares/FileCacheMiddleware.php';
require_once __DIR__ . '/lib/middlewares/SetHeadersMiddleware.php';
require_once __DIR__ . '/lib/middlewares/SetPathMiddleware.php';
require_once __DIR__ . '/lib/models/FolderLocation.php';
require_once __DIR__ . '/lib/models/ResponseContent.php';
require_once __DIR__ . '/lib/models/Cache.php';
require_once __DIR__ . '/lib/models/File.php';
require_once __DIR__ . '/lib/models/FileCache.php';
require_once __DIR__ . '/lib/models/ForbiddenResponse.php';
require_once __DIR__ . '/lib/models/MissingResponse.php';
require_once __DIR__ . '/lib/models/ProcessingRequest.php';
require_once __DIR__ . '/lib/models/Request.php';
require_once __DIR__ . '/lib/models/RequestMatcher.php';
require_once __DIR__ . '/lib/matchers/ResponseMatcher.php';
require_once __DIR__ . '/lib/matchers/StatusCodeMatcher.php';
require_once __DIR__ . '/lib/models/Rule.php';
require_once __DIR__ . '/lib/models/Server.php';
require_once __DIR__ . '/lib/service/ResponseContentReader.php';
require_once __DIR__ . '/lib/service/RequestProcessor.php';
require_once __DIR__ . '/lib/service/ResponseCacher.php';
require_once __DIR__ . '/lib/utils/CacheFilePath.php';
require_once __DIR__ . '/lib/utils/CurlUtils.php';
require_once __DIR__ . '/lib/utils/ContentType.php';
require_once __DIR__ . '/lib/utils/FileUtils.php';
require_once __DIR__ . '/lib/utils/HttpCodeMatcher.php';
require_once __DIR__ . '/lib/validators/RequestPathValidator.php';
