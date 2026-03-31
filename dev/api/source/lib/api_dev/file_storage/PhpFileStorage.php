<?php

namespace ApiDev\FileStorage;

/**
 * File storage implementation that uses PHP's built-in upload superglobals.
 *
 * Reads uploaded files from $_FILES and moves them to the destination
 * path using move_uploaded_file().
 */
class PhpFileStorage implements FileStorageInterface
{
    /**
     * Returns the uploaded file data from $_FILES for the given field.
     *
     * @param string $field The name of the file input field
     * @return array|null The $_FILES[$field] array, or null if not present
     */
    public function getFile(string $field): ?array
    {
        return isset($_FILES[$field]) ? $_FILES[$field] : null;
    }

    /**
     * Moves an uploaded file to its final destination, creating directories
     * as needed.
     *
     * @param string $tmpPath     Temporary path of the uploaded file
     * @param string $destination Full destination path
     * @return bool True on success, false on failure
     */
    public function save(string $tmpPath, string $destination): bool
    {
        $dir = dirname($destination);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return move_uploaded_file($tmpPath, $destination);
    }
}
