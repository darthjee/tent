<?php

namespace Tent\Handlers;

use Tent\Models\FolderLocation;
use Tent\Models\RequestInterface;

/**
 * FileHandler that serves static files based on the request URL and a base directory.
 *
 * This handler returns the contents of a file located by combining the base directory
 * (provided by FolderLocation) and the requestPath from the incoming request. It is
 * typically used to serve static assets such as HTML, CSS, JS, images, etc.
 */
class StaticFileHandler extends FileHandler
{
    private $folderLocation;

    /**
     * @param FolderLocation $folderLocation The base directory for static files.
     */
    public function __construct(FolderLocation $folderLocation)
    {
        $this->folderLocation = $folderLocation;
    }

    /**
     * Builds a StaticFileHandler using named parameters.
     *
     * Example:
     *   StaticFileHandler::build(['location' => './some_folder'])
     *
     * @param array $params Associative array with key 'location' (string).
     * @return StaticFileHandler
     */
    public static function build(array $params): self
    {
        $folderLocation = new FolderLocation($params['location'] ?? '');
        return new self($folderLocation);
    }

    /**
     * Returns the file path for the static file to be served, based on the request URL.
     *
     * @param RequestInterface $request The incoming HTTP request.
     * @return string The full file path to the static asset.
     */
    protected function getFilePath(RequestInterface $request)
    {
        return $this->folderLocation->basePath() . $request->requestPath();
    }
}
