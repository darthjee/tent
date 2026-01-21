<?php

namespace Tent\Handlers;

use Tent\Models\RequestInterface;

/**
 * @deprecated Use StaticFileHandler with SetPathMiddleware instead.
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
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Builds a FixedFileHandler using named parameters.
     *
     * Example:
     *   FixedFileHandler::build(['file' => './some/path/file.txt'])
     *
     * @param array $params Associative array with key 'file' (string).
     * @return FixedFileHandler
     */
    public static function build(array $params): self
    {
        return new self($params['file'] ?? '');
    }

    /**
     * Returns the fixed file path, ignoring the request.
     *
     * @param RequestInterface $request The incoming HTTP request (ignored).
     * @return string The file path to be served.
     */
    protected function getFilePath(RequestInterface $request)
    {
        return $this->filePath;
    }
}
