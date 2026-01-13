<?php

namespace ApiDev;

use ApiDev\Models\Person;

class ListPersonsEndpoint extends Endpoint
{
    private $persons = null;

    public function handle()
    {
        $body = json_encode($this->getData());
        $headers = ['Content-Type: application/json'];
        return new Response($body, 200, $headers);
    }

    protected function getPersons()
    {
        if ($this->persons !== null) {
            return $this->persons;
        }
        $this->persons = \ApiDev\Models\Person::all();
        return $this->persons;
    }

    protected function getData()
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
