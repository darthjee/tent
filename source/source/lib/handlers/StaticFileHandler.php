<?php

namespace Tent\Handlers;

use Tent\Models\FolderLocation;
use Tent\Models\RequestInterface;
use Tent\Models\Response;
use Tent\Exceptions\FileNotFoundException;
use Tent\Exceptions\InvalidFilePathException;
use Tent\Validators\RequestPathValidator;
use Tent\Utils\ContentType;
use Tent\Models\MissingResponse;
use Tent\Models\ForbiddenResponse;
use Tent\Models\File;
use Tent\Service\FileReader;

/**
 * FileHandler that serves static files based on the request URL and a base directory.
 *
 * This handler returns the contents of a file located by combining the base directory
 * (provided by FolderLocation) and the requestPath from the incoming request. It is
 * typically used to serve static assets such as HTML, CSS, JS, images, etc.
 */
class StaticFileHandler extends RequestHandler
{
    private $folderLocation;
    private $filePath;

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
     * Reads the file defined by the request path and returns its contents as a Response.
     *
     * The file path determined by combining the base directory and request path.
     *
     * The file path is validated to prevent directory traversal attacks.
     *
     * If the file does not exist or is not a regular file, a MissingResponse is returned.
     *
     * The Content-Type header is determined using the ContentType utility.
     *
     * @param RequestInterface $request The incoming HTTP request (implements RequestInterface).
     * @return Response The HTTP response containing the file contents, or MissingResponse if not found.
     * @see ContentType::getContentType()
     */
    protected function processsRequest(RequestInterface $request): Response
    {
        try {
            $fileReader = new FileReader($request->requestPath(), $this->folderLocation);

            return $fileReader->getResponse();
        } catch (InvalidFilePathException $e) {
            return new ForbiddenResponse();
        } catch (FileNotFoundException $e) {
            return new MissingResponse();
        }
    }
}
