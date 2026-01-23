<?php

namespace Tent\Service;

use Tent\Models\File;
use Tent\Models\FolderLocation;
use Tent\Models\Response;
use Tent\Validators\RequestPathValidator;
use Tent\Exceptions\FileNotFoundException;
use Tent\Exceptions\InvalidFilePathException;

/**
 * Service class responsible for reading files and returning their contents as Responses.
 *
 * This class handles file path validation and existence checks before reading the file.
 */
class FileReader
{
    private File $file;
    private string $path;

    /**
     * Constructs a FileReader for the given file path and folder location.
     *
     * @param string         $path     The file path to read.
     * @param FolderLocation $location The base folder location.
     */
    public function __construct(string $path, FolderLocation $location)
    {
        $this->path = $path;
        $this->file = new File($path, $location);
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

        return new Response(
            $this->file->content(),
            200,
            $this->file->headers()
        );
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
        if (!$this->file->exists()) {
            throw new FileNotFoundException("File not found: " . $this->path);
        }
    }
}
