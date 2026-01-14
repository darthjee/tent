<?php

namespace Tent;

class ContentType
{
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
