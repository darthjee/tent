<?php

namespace Tent;

class FixedFileHandler extends FileHandler
{
    private $filePath;

    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    protected function getFilePath($request)
    {
        return $this->filePath;
    }
}
