<?php

namespace ApiDev;

use ApiDev\Models\Person;
use ApiDev\Exceptions\InvalidJsonException;
use ApiDev\Exceptions\InvalidDataException;

class CreatePersonEndpoint extends Endpoint
{
    private $data;
    private $id;
    private $person;

    public function handle()
    {
        try {
            $this->initData();

            $this->createPerson();

            $persons = Person::getConnection()->getConnection()->fetchAll(
                "SELECT * FROM persons WHERE id = ?",
                [$this->id]
            );

            if (empty($persons)) {
                return new Response(
                    json_encode(['error' => 'Failed to retrieve created person']),
                    500,
                    ['Content-Type: application/json']
                );
            }

            $this->person = new Person($persons[0]);

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
        } catch (InvalidJsonException $e) {
            return new Response(
                json_encode(['error' => 'Invalid JSON body']),
                400,
                ['Content-Type: application/json']
            );
        } catch (InvalidDataException $e) {
            return new Response(
                json_encode(['error' => 'At least one field required']),
                400,
                ['Content-Type: application/json']
            );
        }
    }

    private function initData()
    {
        $this->data = json_decode($this->request->body(), true);
        if (!is_array($this->data)) {
            throw new InvalidJsonException();
        }
    }

    private function createPerson()
    {
        $firstName = $this->data['first_name'] ?? null;
        $lastName = $this->data['last_name'] ?? null;
        $birthdate = $this->data['birthdate'] ?? null;

        if (is_null($firstName) && is_null($lastName) && is_null($birthdate)) {
            throw new InvalidDataException();
        }

        $attributes = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'birthdate' => $birthdate
        ];

        $this->id = Person::getConnection()->insert($attributes);
    }
}
