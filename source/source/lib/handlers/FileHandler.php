<?php

namespace Tent\Handlers;

use Tent\Utils\ContentType;
use Tent\Models\RequestInterface;
use Tent\Models\Response;
use Tent\Models\MissingResponse;
use Tent\Models\ForbiddenResponse;
use Tent\Exceptions\FileNotFoundException;
use Tent\Exceptions\InvalidFilePathException;
use Tent\Validators\RequestPathValidator;

/**
 * Abstract RequestHandler for serving file contents as HTTP responses.
 *
 * This class provides the base logic for reading files and returning their contents
 * as HTTP responses. It is intended to be extended by concrete handlers such as
 * StaticFileHandler (serving files from a directory) and FixedFileHandler (serving a fixed file).
 */
abstract class FileHandler
{
}
