<?php

namespace Tent\Tests;

use Tent\Utils\Logger;
use Tent\Tests\Support\Utils\VoidLogger;

Logger::setInstance(new VoidLogger());