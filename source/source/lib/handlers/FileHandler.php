<?php

namespace Tent;

abstract class FileHandler implements RequestHandler
{
    abstract protected function getFilePath($request);

    public function handleRequest($request)
    {
        $filePath = $this->getFilePath($request);
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

    protected function getContentType($filePath)
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
