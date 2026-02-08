<?php

use Tent\Configuration;
use Tent\Models\Rule;
use Tent\RequestHandlers\ProxyRequestHandler;
use Tent\RequestHandlers\StaticFileHandler;
use Tent\Models\Server;
use Tent\Models\FolderLocation;
use Tent\Models\RequestMatcher;

require_once __DIR__ . '/rules/frontend.php';
require_once __DIR__ . '/rules/backend.php';
