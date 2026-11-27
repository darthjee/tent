<?php

namespace Tent\Utils;

/**
 * Outcome of a tolerant filesystem removal performed by {@see SafeRemover}.
 */
enum RemovalResult
{
    /**
     * This call removed the path.
     */
    case Removed;

    /**
     * The operation failed but the path no longer exists (concurrent deletion).
     */
    case AlreadyGone;

    /**
     * The operation failed and the path still exists; a `warn` has already been logged.
     */
    case Failed;
}
