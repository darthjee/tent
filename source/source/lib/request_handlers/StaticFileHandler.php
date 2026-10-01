<?php

namespace Tent\RequestHandlers;

use Tent\Models\FolderLocation;
use Tent\Models\RequestInterface;
use Tent\Models\Response;
use Tent\Content\File;
use Tent\Exceptions\FileNotFoundException;
use Tent\Exceptions\InvalidFilePathException;
use Tent\Log\Logger;
use Tent\Models\MissingResponse;
use Tent\Models\ForbiddenResponse;
use Tent\Models\NotModifiedResponse;
use Tent\Service\ConditionalRequestMatcher;
use Tent\Service\ResponseContentReader;

/**
 * FileHandler that serves static files based on the request URL and a base directory.
 *
 * This handler returns the contents of a file located by combining the base directory
 * (provided by FolderLocation) and the requestPath from the incoming request. It is
 * typically used to serve static assets such as HTML, CSS, JS, images, etc.
 *
 * @example Serving index.html for root path:
 * ```php
 * Configuration::buildRule([
 *     'handler' => [
 *         'type' => 'static',
 *         'location' => '/var/www/html/static/'
 *     ],
 *     'matchers' => [
 *         ['method' => 'GET', 'uri' => '/', 'type' => 'exact']
 *     ],
 *     'middlewares' => [
 *         [
 *             'class' => 'Tent\Middlewares\SetPathMiddleware',
 *             'path' => '/index.html'
 *         ]
 *     ]
 * ]);
 * ```
 *
 * @example Serving static files from a folder:
 * ```php
 * Configuration::buildRule([
 *     'handler' => [
 *         'type' => 'static',
 *         'location' => '/var/www/html/static'
 *     ],
 *     'matchers' => [
 *         ['method' => 'GET', 'uri' => '/assets', 'type' => 'begins_with']
 *     ]
 * ]);
 * ```
 *
 * @example Serving files with conditional GET support (ETag / Last-Modified / 304):
 * ```php
 * Configuration::buildRule([
 *     'handler' => [
 *         'type' => 'static',
 *         'location' => '/var/www/html/photos',
 *         'conditional' => true
 *     ],
 *     'matchers' => [
 *         ['method' => 'GET', 'uri' => '/photos', 'type' => 'begins_with']
 *     ]
 * ]);
 * ```
 *
 * With `'conditional' => true` (default `false`), `GET` responses carry `ETag`
 * (a quoted hash of the file size and mtime) and `Last-Modified` headers. When the
 * request's `If-None-Match` matches the ETag (supporting `*`, lists and weak `W/`
 * comparison), or, when `If-None-Match` is absent, `If-Modified-Since` is at or after
 * the file's mtime, a `304 Not Modified` with an empty body is returned without
 * reading the file. Response middlewares still run on the `304`. `HEAD` and other
 * methods, as well as `403` / `404` responses, are unchanged.
 */
class StaticFileHandler extends RequestHandler
{
    /**
     * @var FolderLocation The base directory for static files.
     */
    private FolderLocation $folderLocation;

    /**
     * @var boolean Whether to emit validators and answer conditional GETs with 304.
     */
    private bool $conditional;

    /**
     * @param FolderLocation $folderLocation The base directory for static files.
     * @param boolean        $conditional    Whether to emit ETag / Last-Modified and
     *                                       answer conditional GETs with 304.
     */
    public function __construct(FolderLocation $folderLocation, bool $conditional = false)
    {
        $this->folderLocation = $folderLocation;
        $this->conditional = $conditional;
    }

    /**
     * Builds a StaticFileHandler using named parameters.
     *
     * Example:
     *   StaticFileHandler::build(['location' => './some_folder', 'conditional' => true])
     *
     * @param array $params Associative array with keys 'location' (string) and
     *                      optional 'conditional' (bool, default false).
     * @return StaticFileHandler
     */
    public static function build(array $params): self
    {
        $folderLocation = new FolderLocation($params['location'] ?? '');
        return new self($folderLocation, (bool) ($params['conditional'] ?? false));
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
            $file = new File($request->requestPath(), $this->folderLocation);
            $fileReader = new ResponseContentReader($request, $file);

            if ($this->isConditional($request)) {
                return $this->conditionalResponse($request, $file, $fileReader);
            }

            return $fileReader->getResponse();
        } catch (InvalidFilePathException $e) {
            return new ForbiddenResponse($request);
        } catch (FileNotFoundException $e) {
            Logger::debug(
                '[404] - static file not found — uri: ' . $request->requestPath() .
                ', resolved path: ' . $this->folderLocation->basePath() . '/' . ltrim($request->requestPath(), '/')
            );
            return new MissingResponse($request);
        }
    }

    /**
     * Checks whether the conditional flow applies to the request.
     *
     * @param RequestInterface $request The incoming HTTP request.
     * @return boolean True when the option is enabled and the method is GET.
     */
    private function isConditional(RequestInterface $request): bool
    {
        return $this->conditional && strtoupper((string) $request->requestMethod()) === 'GET';
    }

    /**
     * Returns a 304 when the request's validators match the file, otherwise the
     * regular 200 response with ETag / Last-Modified added.
     *
     * @param RequestInterface      $request    The incoming HTTP request.
     * @param File                  $file       The requested file.
     * @param ResponseContentReader $fileReader The reader for the file.
     * @throws InvalidFilePathException If the request path is invalid.
     * @throws FileNotFoundException If the file does not exist.
     * @return Response
     */
    private function conditionalResponse(
        RequestInterface $request,
        File $file,
        ResponseContentReader $fileReader
    ): Response {
        $fileReader->ensureReadable();

        $matcher = new ConditionalRequestMatcher($request, $file->etag(), $file->lastModified());
        if ($matcher->isNotModified()) {
            return new NotModifiedResponse($request, $file->validatorHeaders());
        }

        $response = $fileReader->getResponse();
        $response->setHeaders(array_merge($response->headers(), $file->validatorHeaders()));

        return $response;
    }
}
