<?php

namespace ApiDev\FileStorage;

/**
 * Test double for FileStorageInterface.
 *
 * Returns a pre-configured file entry and records save calls for assertions.
 */
class MockFileStorage implements FileStorageInterface
{
    /**
     * @var array|null File data returned by getFile(), or null to simulate no upload
     */
    private ?array $file;

    /**
     * @var bool Whether save() should return success
     */
    private bool $saveResult;

    /**
     * @var string|null Last destination path passed to save()
     */
    private ?string $savedTo = null;

    /**
     * @param array|null $file       File data to return (null simulates no upload)
     * @param bool       $saveResult Whether save() should succeed (default: true)
     */
    public function __construct(?array $file = null, bool $saveResult = true)
    {
        $this->file = $file;
        $this->saveResult = $saveResult;
    }

    /**
     * {@inheritdoc}
     */
    public function getFile(string $field): ?array
    {
        return $this->file;
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
