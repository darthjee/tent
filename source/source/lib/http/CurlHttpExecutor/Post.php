<?php

namespace Tent\Http\CurlHttpExecutor;

class Post extends Base
{
    public function request()
    {
        $this->initCurlRequest();

        curl_setopt($this->curlHandle, CURLOPT_POST, true);
        curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $this->body);

        return $this->executeCurlRequest();
    }
}
