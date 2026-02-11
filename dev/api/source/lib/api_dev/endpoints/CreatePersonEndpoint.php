<?php

namespace ApiDev;

use ApiDev\Models\Person;
use ApiDev\Exceptions\RequestException;
use ApiDev\Exceptions\InvalidRequestException;
use ApiDev\Exceptions\ServerErrorException;
use ApiDev\Exceptions\InvalidModelException;

/**
 * Endpoint for creating new person records.
 *
 * Handles POST requests to create a new person in the database.
 * Expects a JSON body with first_name, last_name, and birthdate fields.
 */
class CreatePersonEndpoint extends Endpoint
{
    /**
     * @var array|null The parsed JSON request data
     */
    private $data;

    /**
     * @var int|null The ID of the created person (unused in current implementation)
     */
    private $id;

    /**
     * @var Person|null The Person instance being created
     */
    private $person;

    /**
     * Creates a new person and returns the created record as a Response.
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
     * Handles the request to create a new person.
     *
     * @return Response
     * @throws InvalidRequestException
     * @throws ServerErrorException
     */
    private function handleRequest(): Response
    {
        $this->initData();
        $this->createPerson();

        return $this->buildResponse();
    }

    /**
     * Builds the success response with the created person data.
     *
     * @return Response JSON response with HTTP 201 status
     */
    private function buildResponse(): Response
    {
        return new Response(
            $this->person->toJson(),
            201,
            ['Content-Type: application/json']
        );
    }

    /**
     * Parses and validates the JSON request body.
     *
     * @return void
     * @throws InvalidRequestException If JSON is invalid
     */
    private function initData(): void
    {
        $this->data = json_decode($this->request->body(), true);
        if (!is_array($this->data)) {
            throw new InvalidRequestException('Invalid JSON body');
        }
    }

    /**
     * Creates and saves the Person instance.
     *
     * @return void
     * @throws InvalidRequestException If model validation fails
     */
    private function createPerson(): void
    {
        try {
            $this->person = $this->buildPerson();
            $this->person->save();
        } catch (InvalidModelException) {
            throw new InvalidRequestException('At least one field required');
        }
    }

    /**
     * Builds a Person instance from the request data.
     *
     * @return Person The Person instance
     */
    private function buildPerson(): Person
    {
        return new Person([
            'first_name' => $this->data['first_name'] ?? null,
            'last_name' => $this->data['last_name'] ?? null,
            'birthdate' => $this->data['birthdate'] ?? null
        ]);
    }
}
