<?php

namespace ApiDev;

abstract class Endpoint
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    abstract public function handle();
}
