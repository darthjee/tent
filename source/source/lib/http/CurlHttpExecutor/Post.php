<?php

namespace Tent\Http\CurlHttpExecutor;

class Post extends Base
{
    protected function addExtraCurlOptions()
    {
        curl_setopt($this->curlHandle, CURLOPT_POST, true);
        curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $this->body);
    }
}
