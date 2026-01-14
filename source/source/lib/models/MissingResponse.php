<?php

namespace Tent;

use Tent\Response;

class MissingResponse extends Response
{
    public function __construct()
    {
        parent::__construct("Not Found", 404, ['Content-Type: text/plain']);
    }
}
