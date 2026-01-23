<?php

namespace Tent\Models;

interface ResponseContent
{
    /**
     * Returns the content of the response.
     *
     * @return string
     */
    public function content(): string;

    /**
     * Returns the headers associated with the response content.
     *
     * @return array
     */
    public function headers(): array;

    /**
     * Checks if the content exists.
     *
     * @return boolean
     */
    public function exists(): bool;
}
