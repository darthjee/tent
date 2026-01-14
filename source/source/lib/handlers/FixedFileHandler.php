<?php

namespace Tent;

class FixedFileHandler implements RequestHandler
{
    private $filePath;

    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    public function handleRequest($request)
    {
        if (!file_exists($this->filePath) || !is_file($this->filePath)) {
            return new MissingResponse();
        }

        $content = file_get_contents($this->filePath);
        $contentType = $this->getContentType($this->filePath);
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
