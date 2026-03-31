<?php

namespace ApiDev\FileStorage;

/**
 * Interface for file storage operations.
 *
 * Abstracts file-system writes, enabling the endpoint to be tested
 * without real file uploads.
 */
interface FileStorageInterface
{
    /**
     * Moves an uploaded file to its final destination.
     *
     * @param string $tmpPath     Temporary path of the uploaded file
     * @param string $destination Full destination path
     * @return bool True on success, false on failure
     */
    public function save(string $tmpPath, string $destination): bool;
}
