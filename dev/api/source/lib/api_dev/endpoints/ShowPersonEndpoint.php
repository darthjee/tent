<?php

namespace ApiDev;

use ApiDev\Models\Person;

/**
 * Endpoint for showing a single person by ID.
 *
 * Handles GET /persons/:id requests. Returns the person as JSON or a 404
 * error if the person does not exist.
 */
class ShowPersonEndpoint extends Endpoint
{
    /**
     * Handles the request to show a single person.
     *
     * @return Response JSON response with person data (200) or error (404)
     */
    public function handle(): Response
    {
        $id = $this->extractPersonId();
        $person = Person::find($id);

        if ($person === null) {
            return new Response(
                json_encode(['error' => 'Person not found']),
                404,
                ['Content-Type: application/json']
            );
        }

        $data = [
            'id' => $person->getId(),
            'first_name' => $person->getFirstName(),
            'last_name' => $person->getLastName(),
            'birthdate' => $person->getBirthdate(),
            'created_at' => $person->getCreatedAt(),
            'updated_at' => $person->getUpdatedAt(),
        ];

        return new Response(
            json_encode($data),
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
}
