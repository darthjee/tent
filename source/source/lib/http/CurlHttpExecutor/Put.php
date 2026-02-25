<?php

namespace Tent\Http\CurlHttpExecutor;

/**
 * Executor for performing HTTP PUT requests using cURL.
 * Inherits common setup and response parsing logic from the Base class.
 */
class Put extends Base
{
    /**
     * Adds extra cURL options specific to PUT requests.
     *
     * This method sets the necessary cURL options to perform a PUT request, including
     * setting the CURLOPT_CUSTOMREQUEST option and providing the request body.
     *
     * @return void
     */
    protected function addExtraCurlOptions(): void
    {
        curl_setopt($this->curlHandle, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $this->body);
    }
}
