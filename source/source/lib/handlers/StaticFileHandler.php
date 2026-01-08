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

        return new Response(
            $content,
            200,
            ["Content-Type: $contentType"]
        );
    }

    private function getContentType($filePath)
    {
        return mime_content_type($filePath);
    }
}
