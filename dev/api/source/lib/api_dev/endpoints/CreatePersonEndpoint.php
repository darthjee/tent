<?php

namespace ApiDev;

use ApiDev\Models\Person;
use ApiDev\Exceptions\InvalidRequestException;
use ApiDev\Exceptions\ServerErrorException;

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
            $this->retrievePerson();

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
        } catch (InvalidRequestException $e) {
            return new Response(
                json_encode(['error' =>$e->getMessage()]),
                400,
                ['Content-Type: application/json']
            );
        } catch (ServerErrorException $e) {
            return new Response(
                json_encode(['error' => $e->getMessage()]),
                500,
                ['Content-Type: application/json']
            );
        }
    }

    private function initData()
    {
        $this->data = json_decode($this->request->body(), true);
        if (!is_array($this->data)) {
            throw new InvalidRequestException('Invalid JSON body');
        }
    }

    private function createPerson()
    {
        $firstName = $this->data['first_name'] ?? null;
        $lastName = $this->data['last_name'] ?? null;
        $birthdate = $this->data['birthdate'] ?? null;

        if (is_null($firstName) && is_null($lastName) && is_null($birthdate)) {
            throw new InvalidRequestException('At least one field required');
        }

        $attributes = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'birthdate' => $birthdate
        ];

        $this->id = Person::getConnection()->insert($attributes);
    }

    private function retrievePerson()
    {
        $persons = Person::getConnection()->getConnection()->fetchAll(
            "SELECT * FROM persons WHERE id = ?",
            [$this->id]
        );

        if (empty($persons)) {
            throw new ServerErrorException('Failed to retrieve created person');
        }

        $this->person = new Person($persons[0]);
    }
}
