<?php

namespace ApiDev;

use ApiDev\Models\Person;

class CreatePersonEndpoint extends Endpoint
{
    public function handle()
    {
        $data = json_decode($this->request->body(), true);
        
        if (!is_array($data)) {
            return new Response(
                json_encode(['error' => 'Invalid JSON body']),
                400,
                ['Content-Type: application/json']
            );
        }
        
        $attributes = [];
        
        if (isset($data['first_name'])) {
            $attributes['first_name'] = $data['first_name'];
        }
        
        if (isset($data['last_name'])) {
            $attributes['last_name'] = $data['last_name'];
        }
        
        if (isset($data['birthdate'])) {
            $attributes['birthdate'] = $data['birthdate'];
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
