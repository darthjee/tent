<?php

namespace Tent\Service;

use Tent\Models\File;
use Tent\Models\Response;
use Tent\Validators\RequestPathValidator;
use Tent\Exceptions\FileNotFoundException;
use Tent\Exceptions\InvalidFilePathException;

class FileReader
{
    private $file;

    public function __construct(File $file)
    {
        $this->file = $file;
    }

    public function readFileToResponse(): Response
    {
        $this->validateFilePath();
        $this->checkFileExistance();
        
        $content = $this->file->content();
        $contentType = $this->file->contentType();
        $contentLength = $this->file->contentLength();

        return new Response(
            $content,
            200,
            [
                "Content-Type: $contentType",
                "Content-Length: $contentLength"
            ]
        );
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
        $path = $this->file->path();
        $validator = new RequestPathValidator($path);
        if (!$validator->isValid()) {
            throw new InvalidFilePathException("Invalid file path: $path");
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