<?php

namespace Tent;

interface HttpClientInterface
{
    public function request($url, $headers);
}
