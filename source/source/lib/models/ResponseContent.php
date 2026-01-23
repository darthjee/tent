<?php

namespace Tent\Models;

interface ResponseContent
{
    public function content(): string;

    public function contentType(): string
    
    public function contentLength(): int
}
