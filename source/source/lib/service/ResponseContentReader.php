<?php

namespace Tent\Service;

use Tent\Models\Response;
use Tent\Validators\RequestPathValidator;
use Tent\Exceptions\FileNotFoundException;
use Tent\Exceptions\InvalidFilePathException;
use Tent\Models\ResponseContent;
use Tent\Models\RequestInterface;

/**
 * Service class responsible for reading files and returning their contents as Responses.
 *
 * This class handles file path validation and existence checks before reading the file.
 */
class ResponseContentReader
{
    /**
     * @var ResponseContent The source for content to be read.
     *
     * This is typically a File or Cache object implementing ResponseContent.
     *
     * @see ResponseContent
     * @see File
     * @see Cache
     */
    private ResponseContent $responseContent;

    /**
     * @var string The request path to read.
     */
    private string $path;

    /**
     * @var RequestInterface The HTTP request associated with the request path.
     */
    private RequestInterface $request;

    /**
     * Constructs a ResponseContentReader for the given request path and folder location.
     *
     * @param RequestInterface $request         The HTTP request containing the request path.
     * @param ResponseContent  $responseContent The source for content to be read.
     *
     * The source `$responseContent` is typically a File or Cache object implementing ResponseContent.
     *
     * @see ResponseContent
     * @see File
     * @see Cache
     */
    public function __construct(RequestInterface $request, ResponseContent $responseContent)
    {
        $this->path = $request->requestPath();
        $this->request = $request;
        $this->responseContent = $responseContent;
    }

    /**
     * Reads the ResponseContent and returns its contents as a Response.
     *
     * @throws InvalidFilePathException If the request path is invalid.
     * @throws FileNotFoundException If the ResponseContent does not exist correctly.
     * @return Response The HTTP response containing the file content.
     */
    public function getResponse(): Response
    {
        $this->validate();

        return new Response([
            'body' => $this->responseContent->content(),
            'httpCode' => 200,
            'headers' => $this->responseContent->headers(),
            'request' => $this->request
        ]);
    }

    /**
     * Validates the file path and existence.
     *
     * @throws InvalidFilePathException If the file path is invalid.
     * @throws FileNotFoundException If the file does not exist or is not a regular file.
     * @return void
     */
    protected function validate(): void
    {
        $this->validateFilePath();
        $this->checkFileExistance();
    }

    /**
     * Validates the request path for traversal attacks.
     * Throws InvalidFilePathException if path is invalid.
     *
     * @throws InvalidFilePathException If the request path is invalid.
     * @return void
     */
    protected function validateFilePath(): void
    {
        $validator = new RequestPathValidator($this->path);
        if (!$validator->isValid()) {
            throw new InvalidFilePathException("Invalid request path: $this->path");
        }
    }

    /**
     * Checks if the ResponseContent exists correctly. Throws FileNotFoundException if not.
     *
     * @throws FileNotFoundException If the ResponseContent does not exist correctly.
     * @return void
     */
    protected function checkFileExistance(): void
    {
        if (!$this->responseContent->exists()) {
            throw new FileNotFoundException("File not found: " . $this->path);
        }
    }
}
