<?php

namespace Tent;

class MissingRequestHandler
{
    public function handleRequest($request)
    {
        return new MissingResponse();
    }
}
