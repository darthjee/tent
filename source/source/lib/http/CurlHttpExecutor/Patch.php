<?php

namespace Tent\Http\CurlHttpExecutor;

/**
 * Executor for performing HTTP PATCH requests using cURL.
 * Inherits common setup and response parsing logic from the Base class.
 */
class Patch extends Base
{
    /**
     * Adds extra cURL options specific to PATCH requests.
     *
     * This method sets the necessary cURL options to perform a PATCH request, including
     * setting the CURLOPT_CUSTOMREQUEST option and providing the request body.
     *
     * @return void
     */
    protected function addExtraCurlOptions(): void
    {
        curl_setopt($this->curlHandle, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $this->body);
    }
}
