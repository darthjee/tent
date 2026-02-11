<?php

namespace ApiDev\Exceptions;

class ServerErrorException extends RequestException
{
    public function getHttpStatusCode(): int
    {
        return 500;
    }
}
