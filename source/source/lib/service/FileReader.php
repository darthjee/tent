<?php

namespace Tent\Service;

use Tent\Models\File;
use Tent\Models\FolderLocation;
use Tent\Models\Response;
use Tent\Validators\RequestPathValidator;
use Tent\Exceptions\FileNotFoundException;
use Tent\Exceptions\InvalidFilePathException;

class FileReader
{
    private File $file;
    private string $path;

    public function __construct(string $path, FolderLocation $location)
    {
        $this->path = $path;
        $this->file = new File($path, $location);
    }

    public function getResponse(): Response
    {
        $this->validate();
        
        $contentType = $this->file->contentType();
        $contentLength = $this->file->contentLength();

        return new Response(
            $this->file->content(),
            200,
            [
                "Content-Type: $contentType",
                "Content-Length: $contentLength"
            ]
        );
    }

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
        $filePath = $this->file->fullPath();
        if (!file_exists($filePath) || !is_file($filePath)) {
            throw new FileNotFoundException("File not found: $filePath");
        }
    }
}