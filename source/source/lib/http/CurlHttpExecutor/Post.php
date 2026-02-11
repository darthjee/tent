<?php

namespace Tent\Http\CurlHttpExecutor;

/**
 * Executor for performing HTTP POST requests using cURL.
 * Inherits common setup and response parsing logic from the Base class.
 */
class Post extends Base
{
    protected function addExtraCurlOptions(): void
    {
        curl_setopt($this->curlHandle, CURLOPT_POST, true);
        curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $this->body);
    }
}
