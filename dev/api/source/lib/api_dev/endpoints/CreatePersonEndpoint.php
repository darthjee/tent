<?php

namespace ApiDev;

use ApiDev\Models\Person;
use ApiDev\Exceptions\RequestException;
use ApiDev\Exceptions\InvalidRequestException;
use ApiDev\Exceptions\ServerErrorException;
use ApiDev\Exceptions\InvalidModelException;

class CreatePersonEndpoint extends Endpoint
{
    private $data;
    private $id;
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

    private function buildResponse(): Response
    {
        $responseData = [
            'id' => $this->person->getId(),
            'first_name' => $this->person->getFirstName(),
            'last_name' => $this->person->getLastName(),
            'birthdate' => $this->person->getBirthdate(),
            'created_at' => $this->person->getCreatedAt(),
            'updated_at' => $this->person->getUpdatedAt(),
        ];

        return new Response(
            json_encode($responseData),
            201,
            ['Content-Type: application/json']
        );
    }

    private function initData(): void
    {
        $this->data = json_decode($this->request->body(), true);
        if (!is_array($this->data)) {
            throw new InvalidRequestException('Invalid JSON body');
        }
    }

    private function createPerson(): void
    {
        try {
            $this->person = $this->buildPerson();
            $this->person->save();
        } catch (InvalidModelException $e) {
            throw new InvalidRequestException('At least one field required');
        }
    }

    private function buildPerson(): Person
    {
        return new Person([
            'first_name' => $this->data['first_name'] ?? null,
            'last_name' => $this->data['last_name'] ?? null,
            'birthdate' => $this->data['birthdate'] ?? null
        ]);
    }
}
