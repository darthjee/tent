<?php

namespace Tent;

class StaticFileHandler implements RequestHandler
{
    private $folderLocation;

    public function __construct($folderLocation)
    {
        $this->folderLocation = $folderLocation;
    }

    public function handleRequest($request)
    {
        $filePath = $this->folderLocation->basePath() . $request->requestUrl();

        if (!file_exists($filePath) || !is_file($filePath)) {
            return new MissingResponse();
        }

        $content = file_get_contents($filePath);
        $contentType = $this->getContentType($filePath);
        $contentLength = strlen($content);

        return new Response(
            $content,
            200,
            [
                "Content-Type: $contentType",
                "Content-Length: $contentLength"
            ]
        );
    }

    private function getContentType($filePath)
    {
        $mimeType = mime_content_type($filePath);

        // If mime_content_type returns a generic type, use extension-based detection
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
