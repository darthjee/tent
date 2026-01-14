<?php

namespace Tent;

class StaticFileHandler extends FileHandler
{
    private $folderLocation;

    public function __construct($folderLocation)
    {
        $this->folderLocation = $folderLocation;
    }

    protected function getFilePath($request)
    {
        return $this->folderLocation->basePath() . $request->requestUrl();
    }
}
