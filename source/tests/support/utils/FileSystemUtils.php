<?php

namespace Tent\Tests\Support\Utils;

class FileSystemUtils
{
    /**
     * Recursively deletes all files and folders inside the given directory, then removes the directory itself.
     *
     * @param string $dir
     * @return void
     */
    public static function removeDirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isFile() || $file->isLink()) {
                unlink($file->getPathname());
            } elseif ($file->isDir()) {
                rmdir($file->getPathname());
            }
        }
        rmdir($dir);
    }
}
