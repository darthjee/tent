<?php

namespace Tent;

class FolderLocation
{
    private $basePath;

    public function __construct($basePath)
    {
        $this->basePath = $basePath;
    }

    public function basePath()
    {
        return $this->basePath;
    }
}
