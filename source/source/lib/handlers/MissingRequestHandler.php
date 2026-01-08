<?php

namespace Tent;

class MissingRequestHandler implements RequestHandler
{
    public function handleRequest($request)
    {
        return new MissingResponse();
    }
}
