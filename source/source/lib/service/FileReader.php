<?php

namespace Tent\Service;

use Tent\Models\File;

class FileReader
{
    private $file;

    public function __construct(File $file)
    {
        $this->file = $file;
    }
}