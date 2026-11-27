<?php

namespace Tent\Utils;

use Tent\Log\Logger;

/**
 * Removes files and directories while tolerating concurrent deletion.
 *
 * Other writers may delete the same path at any time, so existence is never
 * checked up front: the removal is attempted and, only when it fails, the
 * filesystem is inspected to tell the two cases apart:
 *
 * - the path no longer exists: someone else removed it, nothing is logged;
 * - the path still exists: the failure is real and is logged at `warn` level.
 *
 * No exception ever escapes this class; callers only act on the returned
 * {@see RemovalResult} and do not need to log failures themselves.
 *
 * ## Example
 *
 * ```php
 * $remover = new SafeRemover();
 *
 * $remover->removeFile('./cache/users/GET/entry.body');
 * $remover->removeDir('./cache/users/GET');
 * ```
 */
class SafeRemover
{
    /**
     * Removes a file or a symbolic link (the link target is never touched).
     *
     * @param string $path Path of the file or link to remove.
     * @return RemovalResult The outcome of the removal.
     */
    public function removeFile(string $path): RemovalResult
    {
        return $this->attempt(fn (string $target): bool => @unlink($target), $path, 'file');
    }

    /**
     * Removes an empty directory.
     *
     * @param string $path Path of the directory to remove.
     * @return RemovalResult The outcome of the removal.
     */
    public function removeDir(string $path): RemovalResult
    {
        return $this->attempt(fn (string $target): bool => @rmdir($target), $path, 'dir');
    }

    /**
     * Runs a removal operation and classifies its outcome.
     *
     * The error message is read right after the operation, before the existence
     * re-check, so it always describes the removal itself.
     *
     * @param callable $operation Removal operation, receiving the path and returning true on success.
     * @param string   $path      Path to remove.
     * @param string   $kind      Kind of path used in the log message: 'file' or 'dir'.
     * @return RemovalResult The outcome of the removal.
     */
    private function attempt(callable $operation, string $path, string $kind): RemovalResult
    {
        try {
            error_clear_last();
            if ($operation($path)) {
                return RemovalResult::Removed;
            }
            $error = error_get_last()['message'] ?? 'unknown error';
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        // A failed unlink()/rmdir() does not clear the stat cache.
        clearstatcache(true, $path);
        if (!file_exists($path) && !is_link($path)) {
            return RemovalResult::AlreadyGone;
        }

        Logger::warn('cache removal failed — ' . $kind . ': ' . $path . ' — ' . $error);

        return RemovalResult::Failed;
    }
}
