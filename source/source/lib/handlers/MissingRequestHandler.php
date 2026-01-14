<?php

namespace Tent;

use Tent\RequestHandler;

class MissingRequestHandler implements RequestHandler
{
    public function handleRequest($request)
    {
        return new MissingResponse();
    }
}
