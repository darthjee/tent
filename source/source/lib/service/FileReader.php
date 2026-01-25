<?php

namespace Tent\Service;

use Tent\Models\File;
use Tent\Models\FolderLocation;
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
class FileReader
{
    /**
     * @var ResponseContent The file content wrapper.
     */
    private ResponseContent $content;

    /**
     * @var string The file path to read.
     */
    private string $path;

    /**
     * @var RequestInterface The HTTP request associated with the file read.
     */
    private RequestInterface $request;

    /**
     * Constructs a FileReader for the given file path and folder location.
     *
     * @param RequestInterface $request  The HTTP request containing the file path.
     * @param FolderLocation   $location The base folder location.
     */
    public function __construct(RequestInterface $request, FolderLocation $location)
    {
        $this->path = $request->requestPath();
        $this->request = $request;
        $this->content = new File($this->path, $location);
    }

    /**
     * Reads the file and returns its contents as a Response.
     *
     * @throws InvalidFilePathException If the file path is invalid.
     * @throws FileNotFoundException If the file does not exist or is not a regular file.
     * @return Response The HTTP response containing the file content.
     */
    public function getResponse(): Response
    {
        $this->validate();

        return new Response([
            'body' => $this->content->content(),
            'httpCode' => 200,
            'headers' => $this->content->headers(),
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
     * Validates the file path for traversal attacks.
     * Throws InvalidFilePathException if path is invalid.
     *
     * @throws InvalidFilePathException If the file path is invalid.
     * @return void
     */
    protected function validateFilePath(): void
    {
        $validator = new RequestPathValidator($this->path);
        if (!$validator->isValid()) {
            throw new InvalidFilePathException("Invalid file path: $this->path");
        }
    }

    /**
     * Checks if the file exists and is a regular file. Throws FileNotFoundException if not.
     *
     * @throws FileNotFoundException If the file does not exist or is not a regular file.
     * @return void
     */
    protected function checkFileExistance(): void
    {
        if (!$this->content->exists()) {
            throw new FileNotFoundException("File not found: " . $this->path);
        }
    }
}
