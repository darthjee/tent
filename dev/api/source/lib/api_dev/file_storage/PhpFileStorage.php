<?php

namespace ApiDev\FileStorage;

/**
 * File storage implementation that moves uploaded files using move_uploaded_file().
 */
class PhpFileStorage implements FileStorageInterface
{
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
