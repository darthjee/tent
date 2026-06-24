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
     * When uploaded files are present, builds a CURLFile array so curl generates
     * a fresh multipart/form-data body with the correct boundary. Otherwise,
     * forwards the raw body string as-is.
     *
     * @return void
     */
    protected function addExtraCurlOptions(): void
    {
        curl_setopt($this->curlHandle, CURLOPT_POST, true);

        if (!empty($this->uploadedFiles)) {
            $fields = $this->postFields;
            foreach ($this->uploadedFiles as $fieldName => $file) {
                $fields[$fieldName] = new \CURLFile(
                    $file['tmp_name'],
                    $file['type'] ?? '',
                    $file['name'] ?? ''
                );
            }
            curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $fields);
        } else {
            curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $this->body);
        }
    }
}
