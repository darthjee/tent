<?php

namespace Tent\Http\CurlHttpExecutor;

class Get extends Base
{
    public function request()
    {
        $this->initCurlRequest();

        return $this->executeCurlRequest();
    }
}
