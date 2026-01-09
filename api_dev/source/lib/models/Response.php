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
}
