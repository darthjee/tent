<?php

namespace Tent\Http\CurlHttpExecutor;

/**
 * Executor for performing HTTP POST requests using cURL.
 * Inherits common setup and response parsing logic from the Base class.
 */
class Post extends Base
{
    /**
     * Adds extra cURL options specific to POST requests.
      *
      * This method sets the necessary cURL options to perform a POST request, including
      * setting the CURLOPT_POST option and providing the request body.
      *
      * @return void
     */
    protected function addExtraCurlOptions(): void
    {
        curl_setopt($this->curlHandle, CURLOPT_POST, true);
        curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $this->body);
    }
}
