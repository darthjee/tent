<?php

namespace Tent\Models;

/**
 * Represents a single uploaded file entry from the $_FILES superglobal.
 *
 * Wraps the raw associative array for a single uploaded file and exposes
 * a toCurlFile() method to convert it into a \CURLFile instance suitable
 * for use in multipart/form-data POST requests.
 */
class UploadedFile
{
    /**
     * @var string Temporary file path on the server
     */
    private string $tmpName;

    /**
     * @var string MIME type of the uploaded file
     */
    private string $type;

    /**
     * @var string Original filename as provided by the client
     */
    private string $name;

    /**
     * Constructs an UploadedFile from a $_FILES entry array.
     *
     * @param array $file A single entry from $_FILES, with keys:
     *   - 'tmp_name': string — the temporary path of the uploaded file
     *   - 'type':     string (optional) — MIME type
     *   - 'name':     string (optional) — original filename.
     */
    public function __construct(array $file)
    {
        $this->tmpName = $file['tmp_name'];
        $this->type = $file['type'] ?? '';
        $this->name = $file['name'] ?? '';
    }

    /**
     * Converts this uploaded file into a \CURLFile instance.
     *
     * @return \CURLFile A CURLFile ready to be used in a CURLOPT_POSTFIELDS array
     */
    public function toCurlFile(): \CURLFile
    {
        return new \CURLFile($this->tmpName, $this->type, $this->name);
    }
}
