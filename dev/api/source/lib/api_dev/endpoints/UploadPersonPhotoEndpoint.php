<?php

namespace ApiDev;

use ApiDev\Models\Person;
use ApiDev\FileStorage\FileStorageInterface;
use ApiDev\FileStorage\PhpFileStorage;
use ApiDev\Exceptions\RequestException;
use ApiDev\Exceptions\NotFoundException;
use ApiDev\Exceptions\InvalidRequestException;
use ApiDev\Exceptions\UnprocessableEntityException;
use ApiDev\Exceptions\ServerErrorException;

/**
 * Endpoint for uploading a photo for a person.
 *
 * Handles POST /persons/:id/photo.json requests. The uploaded file is saved
 * to the photos directory as <person_id>.jpg. No database changes are made.
 */
class UploadPersonPhotoEndpoint extends Endpoint
{
    /**
     * @var string Default directory where photos are stored
     */
    private const DEFAULT_PHOTOS_DIR = '/home/app/app/photos';

    /**
     * @var string[] MIME types accepted for photo uploads
     */
    private const ALLOWED_MIME_TYPES = ['image/jpeg'];

    /**
     * @var FileStorageInterface Handles file access and persistence
     */
    private FileStorageInterface $fileStorage;

    /**
     * @var string Directory where photos are stored
     */
    private string $photosDir;

    /**
     * Creates a new UploadPersonPhotoEndpoint.
     *
     * @param RequestInterface         $request     The HTTP request
     * @param FileStorageInterface|null $fileStorage File storage abstraction (defaults to PhpFileStorage)
     * @param string                   $photosDir   Directory for photos (defaults to /home/app/app/photos)
     */
    public function __construct(
        RequestInterface $request,
        ?FileStorageInterface $fileStorage = null,
        string $photosDir = self::DEFAULT_PHOTOS_DIR
    ) {
        parent::__construct($request);
        $this->fileStorage = $fileStorage ?? new PhpFileStorage();
        $this->photosDir = $photosDir;
    }

    /**
     * Handles the photo upload request.
     *
     * @return Response
     */
    public function handle(): Response
    {
        try {
            return $this->handleRequest();
        } catch (RequestException $e) {
            return new Response(
                json_encode(['error' => $e->getMessage()]),
                $e->getHttpStatusCode(),
                ['Content-Type: application/json']
            );
        }
    }

    /**
     * Executes the upload flow, throwing on any error.
     *
     * @return Response
     * @throws NotFoundException             If the person does not exist
     * @throws InvalidRequestException       If no valid file was uploaded
     * @throws UnprocessableEntityException  If the file type is not allowed
     * @throws ServerErrorException          If the file cannot be saved
     */
    private function handleRequest(): Response
    {
        $personId = $this->extractPersonId();
        $person = $this->loadPerson($personId);
        $file = $this->validateUpload();
        $this->validateMimeType($file);
        $this->saveFile($personId, $file);

        return $this->buildResponse($person);
    }

    /**
     * Extracts the person ID from the request URL.
     *
     * @return int The person ID
     */
    private function extractPersonId(): int
    {
        preg_match('#/persons/(\d+)/photo\.json#', $this->request->requestUrl(), $matches);
        return (int) $matches[1];
    }

    /**
     * Loads the person by ID, throwing NotFoundException if not found.
     *
     * @param int $id The person ID
     * @return Person
     * @throws NotFoundException
     */
    private function loadPerson(int $id): Person
    {
        $person = Person::find($id);
        if ($person === null) {
            throw new NotFoundException('Person not found');
        }
        return $person;
    }

    /**
     * Validates that a file was uploaded without errors.
     *
     * @return array The validated $_FILES entry
     * @throws InvalidRequestException
     */
    private function validateUpload(): array
    {
        $file = $this->request->uploadedFile('photo');
        if ($file === null || $file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidRequestException('No valid file uploaded');
        }
        return $file;
    }

    /**
     * Validates the MIME type of the uploaded file.
     *
     * @param array $file The file entry from $_FILES
     * @throws UnprocessableEntityException
     */
    private function validateMimeType(array $file): void
    {
        $mimeType = mime_content_type($file['tmp_name']);
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new UnprocessableEntityException('Invalid file type');
        }
    }

    /**
     * Saves the uploaded file to the photos directory as <person_id>.jpg.
     *
     * @param int   $personId The person ID (used as filename)
     * @param array $file     The file entry from $_FILES
     * @throws ServerErrorException
     */
    private function saveFile(int $personId, array $file): void
    {
        $destination = $this->photosDir . '/' . $personId . '.jpg';
        if (!$this->fileStorage->save($file['tmp_name'], $destination)) {
            throw new ServerErrorException('Failed to save file');
        }
    }

    /**
     * Builds a 200 JSON response with the person's data.
     *
     * @param Person $person
     * @return Response
     */
    private function buildResponse(Person $person): Response
    {
        return new Response(
            $person->toJson(),
            200,
            ['Content-Type: application/json']
        );
    }
}
