<?php

namespace ApiDev;

use ApiDev\Models\Person;

class CreatePersonEndpoint extends Endpoint
{
    private $data;

    public function handle()
    {
        $this->data = json_decode($this->request->body(), true);

        if (!is_array($this->data)) {
            return new Response(
                json_encode(['error' => 'Invalid JSON body']),
                400,
                ['Content-Type: application/json']
            );
        }

        $attributes = [];

        if (isset($this->data['first_name'])) {
            $attributes['first_name'] = $this->data['first_name'];
        }

        if (isset($this->data['last_name'])) {
            $attributes['last_name'] = $this->data['last_name'];
        }

        if (isset($this->data['birthdate'])) {
            $attributes['birthdate'] = $this->data['birthdate'];
        }

        if (empty($attributes)) {
            return new Response(
                json_encode(['error' => 'At least one field required']),
                400,
                ['Content-Type: application/json']
            );
        }

        $id = Person::getConnection()->insert($attributes);

        $persons = Person::getConnection()->getConnection()->fetchAll(
            "SELECT * FROM persons WHERE id = ?",
            [$id]
        );

        if (empty($persons)) {
            return new Response(
                json_encode(['error' => 'Failed to retrieve created person']),
                500,
                ['Content-Type: application/json']
            );
        }

        $person = new Person($persons[0]);

        $responseData = [
            'id' => $person->getId(),
            'first_name' => $person->getFirstName(),
            'last_name' => $person->getLastName(),
            'birthdate' => $person->getBirthdate(),
            'created_at' => $person->getCreatedAt(),
            'updated_at' => $person->getUpdatedAt(),
        ];

        return new Response(
            json_encode($responseData),
            201,
            ['Content-Type: application/json']
        );
    }
}
