<?php

namespace ApiDev;

use ApiDev\Models\Person;

/**
 * Endpoint for listing all persons.
 * 
 * Retrieves all person records from the database and returns them as JSON.
 */
class ListPersonsEndpoint extends Endpoint
{
    /**
     * @var array|null Cached list of Person instances
     */
    private $persons = null;

    /**
     * Handles the request to list all persons.
     * 
     * @return Response JSON response with array of person data and HTTP 200
     */
    public function handle(): Response
    {
        $body = json_encode($this->getData());
        $headers = ['Content-Type: application/json'];
        return new Response($body, 200, $headers);
    }

    /**
     * Returns the list of Person instances, fetching from database if needed.
     * 
     * @return array Array of Person model instances
     */
    protected function getPersons(): array
    {
        if ($this->persons !== null) {
            return $this->persons;
        }
        $this->persons = \ApiDev\Models\Person::all();
        return $this->persons;
    }

    /**
     * Transforms Person instances into an array of data arrays for JSON output.
     * 
     * @return array Array of associative arrays with person data
     */
    protected function getData(): array
    {
        return array_map(function ($person) {
            return [
                'id' => $person->getId(),
                'first_name' => $person->getFirstName(),
                'last_name' => $person->getLastName(),
                'birthdate' => $person->getBirthdate(),
                'created_at' => $person->getCreatedAt(),
                'updated_at' => $person->getUpdatedAt(),
            ];
        }, $this->getPersons());
    }
}
