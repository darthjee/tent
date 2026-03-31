<?php

namespace ApiDev\FileStorage;

/**
 * Interface for file storage operations.
 *
 * Abstracts access to uploaded files and file-system writes,
 * enabling the endpoint to be tested without real file uploads.
 */
interface FileStorageInterface
{
    /**
     * Returns the uploaded file data for the given form field.
     *
     * @param string $field The name of the file input field
     * @return array|null The file data array (keys: tmp_name, error, type, …),
     *                    or null if no file was uploaded for that field
     */
    public function getFile(string $field): ?array;

    /**
     * Moves an uploaded file to its final destination.
     *
     * @param string $tmpPath     Temporary path of the uploaded file
     * @param string $destination Full destination path
     * @return bool True on success, false on failure
     */
    public function save(string $tmpPath, string $destination): bool;
}
