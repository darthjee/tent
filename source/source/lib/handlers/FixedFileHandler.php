<?php

namespace Tent;

/**
 * FileHandler that always returns the contents of a fixed file.
 *
 * The file path is defined in the constructor and the same file is served for every request,
 * regardless of the incoming Request object.
 */
class FixedFileHandler extends FileHandler
{
    /**
     * @var string The path to the file to be served for all requests.
     */
    private $filePath;

    /**
     * @param string $filePath The path to the file to be served for all requests.
     */
    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Returns the fixed file path, ignoring the request.
     *
     * @param Request $request The incoming HTTP request (ignored).
     * @return string The file path to be served.
     */
    protected function getFilePath($request)
    {
        return $this->filePath;
    }
}
