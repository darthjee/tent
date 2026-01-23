<?php

namespace Tent\Models;

interface ResponseContent
{
    public function content(): string;
    public function headers(): array;
}
