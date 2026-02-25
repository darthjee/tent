<?php

namespace Tent\Http\CurlHttpExecutor;

/**
 * Executor for performing HTTP DELETE requests using cURL.
 * Inherits common setup and response parsing logic from the Base class.
 */
class Delete extends Base
{
    /**
     * Adds extra cURL options specific to DELETE requests.
     *
     * This method sets the necessary cURL options to perform a DELETE request, including
     * setting the CURLOPT_CUSTOMREQUEST option and optionally providing the request body.
     *
     * @return void
     */
    protected function addExtraCurlOptions(): void
    {
        curl_setopt($this->curlHandle, CURLOPT_CUSTOMREQUEST, 'DELETE');
        if ($this->body !== null) {
            curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $this->body);
        }
    }
}
