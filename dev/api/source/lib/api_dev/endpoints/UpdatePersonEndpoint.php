<?php

namespace ApiDev;

use ApiDev\Models\Person;
use ApiDev\Exceptions\RequestException;
use ApiDev\Exceptions\NotFoundException;
use ApiDev\Exceptions\UnprocessableEntityException;
use ApiDev\Exceptions\InvalidModelException;

/**
 * Endpoint for updating an existing person record.
 *
 * Handles PATCH /persons/:id requests. Accepts a JSON body with any subset of
 * first_name, last_name, and birthdate. Returns the updated person as JSON
 * or an appropriate error response.
 */
class UpdatePersonEndpoint extends Endpoint
{
    /**
     * @var array|null The parsed JSON request data
     */
    private ?array $data = null;

    /**
     * @var Person|null The Person instance being updated
     */
    private ?Person $person = null;

    /**
     * Updates an existing person and returns the updated record as a Response.
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
     * Handles the request to update an existing person.
     *
     * @return Response
     * @throws NotFoundException If the person is not found
     * @throws UnprocessableEntityException If the request body is invalid
     */
    private function handleRequest(): Response
    {
        $id = $this->extractPersonId();
        $this->findPerson($id);
        $this->initData();
        $this->applyUpdates();

        return $this->buildResponse();
    }

    /**
     * Builds the success response with the updated person data.
     *
     * @return Response JSON response with HTTP 200 status
     */
    private function buildResponse(): Response
    {
        return new Response(
            $this->person->toJson(),
            200,
            ['Content-Type: application/json']
        );
    }

    /**
     * Extracts the person ID from the request URL.
     *
     * @return int The person ID
     */
    private function extractPersonId(): int
    {
        preg_match('#/persons/(\d+)#', $this->request->requestUrl(), $matches);
        return (int) $matches[1];
    }

    /**
     * Finds the person by ID or throws a NotFoundException.
     *
     * @param int $id The person ID to look up
     * @return void
     * @throws NotFoundException If the person does not exist
     */
    private function findPerson(int $id): void
    {
        $this->person = Person::find($id);
        if ($this->person === null) {
            throw new NotFoundException('Person not found');
        }
    }

    /**
     * Parses and validates the JSON request body.
     *
     * @return void
     * @throws UnprocessableEntityException If JSON is invalid
     */
    private function initData(): void
    {
        $this->data = json_decode($this->request->body(), true);
        if (!is_array($this->data)) {
            throw new UnprocessableEntityException('At least one field required');
        }
    }

    /**
     * Merges accepted fields from the request data into the person's attributes and saves.
     *
     * @return void
     * @throws UnprocessableEntityException If no accepted fields are present or model is invalid
     */
    private function applyUpdates(): void
    {
        $accepted = ['first_name', 'last_name', 'birthdate'];
        $attributes = $this->person->getAttributes();
        $hasField = false;

        foreach ($accepted as $field) {
            if (array_key_exists($field, $this->data)) {
                $attributes[$field] = $this->data[$field];
                $hasField = true;
            }
        }

        if (!$hasField) {
            throw new UnprocessableEntityException('At least one field required');
        }

        try {
            $this->person = new Person($attributes);
            $this->person->save();
        } catch (InvalidModelException) {
            throw new UnprocessableEntityException('At least one field required');
        }
    }
}
