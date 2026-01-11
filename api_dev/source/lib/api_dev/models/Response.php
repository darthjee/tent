<?php

namespace ApiDev;

class Response
{
    public $body;
    public $httpCode;
    public $headerLines;

    public function __construct($body, $httpCode, $headerLines = [])
    {
        $this->body = $body;
        $this->httpCode = $httpCode;
        $this->headerLines = $headerLines;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getHeaders(): array
    {
        return $this->headerLines;
    }

    public function getBody()
    {
        return $this->body;
    }
}