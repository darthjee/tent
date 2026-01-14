<?php

namespace Tent;

use Tent\ContentType;

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
        $contentType = ContentType::getContentType($filePath);
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

    // Content type logic moved to Tent\Utils\ContentType
}
