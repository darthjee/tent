<?php

namespace Tent;

use Tent\ContentType;

/**
 * Abstract RequestHandler for serving file contents as HTTP responses.
 *
 * This class provides the base logic for reading files and returning their contents
 * as HTTP responses. It is intended to be extended by concrete handlers such as
 * StaticFileHandler (serving files from a directory) and FixedFileHandler (serving a fixed file).
 */
abstract class FileHandler implements RequestHandler
{
    abstract protected function getFilePath(Request $request);

    /**
     * Reads the file defined by getFilePath and returns its contents as a Response.
     *
     * The file path is determined by the concrete implementation of getFilePath($request).
     * If the file does not exist or is not a regular file, a MissingResponse is returned.
     * The Content-Type header is determined using the ContentType utility.
     *
     * @param Request $request The incoming HTTP request.
     * @return Response The HTTP response containing the file contents, or MissingResponse if not found.
     * @see ContentType::getContentType()
     */
    public function handleRequest(Request $request)
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
}
