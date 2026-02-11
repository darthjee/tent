<?php

namespace ApiDev\Exceptions;

class InvalidRequestException extends RequestException
{
    public function getHttpStatusCode(): int
    {
        return 400;
    }
}
