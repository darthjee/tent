<?php

namespace Tent\Service;

use Tent\Models\File;
use Tent\Models\Response;

class FileReader
{
    private $file;

    public function __construct(File $file)
    {
        $this->file = $file;
    }

    public function readFileToResponse(): Response
    {
        $content = $this->file->content();
        $contentType = $this->file->contentType();
        $contentLength = $this->file->contentLength();

        return new Response(
            $content,
            200,
            [
                "Content-Type: $contentType",
                "Content-Length: $contentLength"
            ]
        );
    }
}