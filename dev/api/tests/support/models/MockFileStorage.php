<?php

namespace ApiDev\FileStorage;

/**
 * Test double for FileStorageInterface.
 *
 * Records save calls for assertions.
 */
class MockFileStorage implements FileStorageInterface
{
    /**
     * @var bool Whether save() should return success
     */
    private bool $saveResult;

    /**
     * @var string|null Last destination path passed to save()
     */
    private ?string $savedTo = null;

    /**
     * @param bool $saveResult Whether save() should succeed (default: true)
     */
    public function __construct(bool $saveResult = true)
    {
        $this->saveResult = $saveResult;
    }

    /**
     * Records the destination and returns the configured result.
     *
     * {@inheritdoc}
     */
    public function save(string $tmpPath, string $destination): bool
    {
        $this->savedTo = $destination;
        return $this->saveResult;
    }

    /**
     * Returns the destination path from the last save() call, or null if never called.
     *
     * @return string|null
     */
    public function getSavedTo(): ?string
    {
        return $this->savedTo;
    }
}
