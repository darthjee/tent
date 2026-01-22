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
abstract class FileHandler extends RequestHandler
{
    /**
     * Returns the file path to be used as the response body for the given request.
     *
     * This method must be implemented by subclasses to determine how the file path is resolved.
     * For example, StaticFileHandler uses the request URL and a base directory, while
     * FixedFileHandler always returns a fixed path.
     *
     * @param RequestInterface $request The incoming HTTP request (implements RequestInterface).
     * @return string The file path to be served as the response body.
     *
     * @see StaticFileHandler
     * @see FixedFileHandler
     */
    abstract protected function getFilePath(RequestInterface $request);

    /**
     * Reads the file defined by getFilePath and returns its contents as a Response.
     *
     * The file path is determined by the concrete implementation of getFilePath($request).
     * If the file does not exist or is not a regular file, a MissingResponse is returned.
     * The Content-Type header is determined using the ContentType utility.
     *
     * @param RequestInterface $request The incoming HTTP request (implements RequestInterface).
     * @return Response The HTTP response containing the file contents, or MissingResponse if not found.
     * @see ContentType::getContentType()
     */
    protected function processsRequest(RequestInterface $request): Response
    {
        try {
            $this->validateFilePath($request->requestPath());
            $filePath = $this->getFilePath($request);
            $this->checkFileExistance($filePath);

            return $this->readAndReturnFile($filePath);
        } catch (InvalidFilePathException $e) {
            return new ForbiddenResponse();
        } catch (FileNotFoundException $e) {
            return new MissingResponse();
        }
    }

    /**
     * Validates the file path for traversal attacks.
     * Throws InvalidFilePathException if path is invalid.
     *
     * @param string $path File path to validate.
     * @throws InvalidFilePathException If the file path is invalid.
     * @return void
     */
    protected function validateFilePath(string $path): void
    {
        $validator = new RequestPathValidator($path);
        if (!$validator->isValid()) {
            throw new InvalidFilePathException("Invalid file path: $path");
        }
    }

    /**
     * Checks if the file exists and is a regular file. Throws FileNotFoundException if not.
     *
     * @param string $filePath File path to be checked.
     * @throws FileNotFoundException If the file does not exist or is not a regular file.
     * @return void
     */
    protected function checkFileExistance(string $filePath): void
    {
        if (!file_exists($filePath) || !is_file($filePath)) {
            throw new FileNotFoundException("File not found: $filePath");
        }
    }

    /**
     * Reads the file and returns a Response with its contents and headers.
     *
     * @param string $filePath File path to be read.
     * @return Response The HTTP response containing the file contents.
     */
    protected function readAndReturnFile(string $filePath): Response
    {
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
    }
}
