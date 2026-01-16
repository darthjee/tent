<?php

namespace Tent\Handlers;

use Tent\Utils\ContentType;
use Tent\Models\Request;
use Tent\Models\Response;
use Tent\Models\MissingResponse;
use Tent\Models\ForbiddenResponse;
use Tent\Exceptions\FileNotFoundException;

/**
 * Abstract RequestHandler for serving file contents as HTTP responses.
 *
 * This class provides the base logic for reading files and returning their contents
 * as HTTP responses. It is intended to be extended by concrete handlers such as
 * StaticFileHandler (serving files from a directory) and FixedFileHandler (serving a fixed file).
 */
abstract class FileHandler implements RequestHandler
{
    /**
     * Returns the file path to be used as the response body for the given request.
     *
     * This method must be implemented by subclasses to determine how the file path is resolved.
     * For example, StaticFileHandler uses the request URL and a base directory, while
     * FixedFileHandler always returns a fixed path.
     *
     * @param Request $request The incoming HTTP request.
     * @return string The file path to be served as the response body.
     *
     * @see StaticFileHandler
     * @see FixedFileHandler
     */
    abstract protected function getFilePath(Request $request);

    /**
     * Reads the file defined by getFilePath and returns its contents as a Response.
     *
     * The file path is determined by the concrete implementation of getFilePath($request).
     * If the file does not exist or is not a regular file, a MissingResponse is returned.
     * The Content-Type header is determined using the ContentType utility.
     *
     * @param Request $request The incoming HTTP request.
     * @return Response The HTTP response containing the file contents, or MissingResponse if not found.
     * @see ContentType::getContentType()
     */
    public function handleRequest(Request $request)
    {
        try {
            // Validate request path for traversal attacks
            $validator = new RequestPathValidator($request->requestUrl());
            if (!$validator->isValid()) {
                return new ForbiddenResponse();
            }

            $filePath = $this->getFilePath($request);
            $this->checkFileExistance($filePath);

            $content = file_get_contents($filePath);
            $contentType = ContentType::getContentType($filePath);
            $contentLength = strlen($content);

            return new Response(
                $content,
                200,
                [
                    "Content-Type: $contentType",
                    "Content-Length: $contentLength"
                ]
            );
        } catch (FileNotFoundException $e) {
            return new MissingResponse();
        }
    }

    /**
     * Checks if the file exists and is a regular file. Throws FileNotFoundException if not.
     *
     * @param string $filePath
     * @throws FileNotFoundException
     */
    protected function checkFileExistance(string $filePath): void
    {
        if (!file_exists($filePath) || !is_file($filePath)) {
            throw new FileNotFoundException("File not found: $filePath");
        }
    }
}
