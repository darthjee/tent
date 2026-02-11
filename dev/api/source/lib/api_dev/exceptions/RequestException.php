<?php

namespace ApiDev\Exceptions;

abstract class RequestException extends \Exception
{
    abstract public function getHttpStatusCode(): int;
}
