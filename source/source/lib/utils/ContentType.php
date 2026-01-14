<?php

namespace Tent;

/**
 * Utility class for determining the content type (MIME type) of files.
 *
 * Provides a static method to get the appropriate content type for a given file path.
 */
class ContentType
{
    /**
     * Returns the content type (MIME type) for a given file path.
     *
     * Uses mime_content_type, but improves detection for common web file extensions.
     *
     * @param string $filePath Path to the file.
     * @return string The MIME type for the file (e.g., 'text/html', 'application/json').
     */
    public static function getContentType($filePath)
    {
        $mimeType = mime_content_type($filePath);

        if ($mimeType === 'text/plain' || $mimeType === 'application/octet-stream') {
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            $extensionMap = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'json' => 'application/json',
                'html' => 'text/html',
                'htm' => 'text/html',
                'svg' => 'image/svg+xml',
                'xml' => 'application/xml',
            ];
            if (isset($extensionMap[$extension])) {
                return $extensionMap[$extension];
            }
        }
        return $mimeType;
    }
}
