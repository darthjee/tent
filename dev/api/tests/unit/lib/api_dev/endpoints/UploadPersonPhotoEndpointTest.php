<?php

namespace ApiDev\Tests;

use PHPUnit\Framework\TestCase;
use ApiDev\Mysql\Configuration;
use ApiDev\Models\Person;
use ApiDev\MockRequest;
use ApiDev\FileStorage\MockFileStorage;
use ApiDev\UploadPersonPhotoEndpoint;

require_once __DIR__ . '/../../../../support/tests_loader.php';

class UploadPersonPhotoEndpointTest extends TestCase
{
    private string $photosDir;

    protected function setUp(): void
    {
        $connection = Configuration::connect();
        $connection->execute('DELETE FROM persons');

        $this->photosDir = sys_get_temp_dir() . '/tent_test_photos_' . uniqid();
        mkdir($this->photosDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->photosDir . '/*') as $file) {
            unlink($file);
        }
        if (is_dir($this->photosDir)) {
            rmdir($this->photosDir);
        }
    }

    public function testReturns404WhenPersonNotFound()
    {
        $request = $this->buildRequest('/persons/999/photo.json');
        $endpoint = new UploadPersonPhotoEndpoint($request, new MockFileStorage(), $this->photosDir);
        $response = $endpoint->handle();

        $this->assertEquals(404, $response->getHttpCode());
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Person not found', $data['error']);
    }

    public function testReturns400WhenNoFileUploaded()
    {
        $person = $this->createPerson();
        $request = $this->buildRequest('/persons/' . $person->getId() . '/photo.json');
        $endpoint = new UploadPersonPhotoEndpoint($request, new MockFileStorage(), $this->photosDir);

        $response = $endpoint->handle();

        $this->assertEquals(400, $response->getHttpCode());
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testReturns400WhenFileHasUploadError()
    {
        $person = $this->createPerson();
        $file = $this->buildFileEntry(error: UPLOAD_ERR_NO_FILE);
        $request = $this->buildRequest('/persons/' . $person->getId() . '/photo.json', $file);
        $endpoint = new UploadPersonPhotoEndpoint($request, new MockFileStorage(), $this->photosDir);

        $response = $endpoint->handle();

        $this->assertEquals(400, $response->getHttpCode());
    }

    public function testReturns422WhenMimeTypeIsInvalid()
    {
        $person = $this->createPerson();
        $tmpFile = $this->createTempFile('text/plain', 'hello world');
        $file = $this->buildFileEntry(tmpName: $tmpFile);
        $request = $this->buildRequest('/persons/' . $person->getId() . '/photo.json', $file);
        $endpoint = new UploadPersonPhotoEndpoint($request, new MockFileStorage(), $this->photosDir);

        $response = $endpoint->handle();

        $this->assertEquals(422, $response->getHttpCode());
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('error', $data);

        unlink($tmpFile);
    }

    public function testReturns200AndSavesFileForValidUpload()
    {
        $person = $this->createPerson();
        $tmpFile = $this->createTempFile('image/jpeg');
        $file = $this->buildFileEntry(tmpName: $tmpFile);
        $request = $this->buildRequest('/persons/' . $person->getId() . '/photo.json', $file);
        $fileStorage = new MockFileStorage();
        $endpoint = new UploadPersonPhotoEndpoint($request, $fileStorage, $this->photosDir);

        $response = $endpoint->handle();

        $this->assertEquals(200, $response->getHttpCode());
        $this->assertEquals(['Content-Type: application/json'], $response->getHeaders());

        $data = json_decode($response->getBody(), true);
        $this->assertEquals($person->getId(), $data['id']);

        $expectedPath = $this->photosDir . '/' . $person->getId() . '.jpg';
        $this->assertEquals($expectedPath, $fileStorage->getSavedTo());

        unlink($tmpFile);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function buildRequest(string $url, ?array $file = null): MockRequest
    {
        return new MockRequest([
            'requestMethod' => 'POST',
            'requestUrl'    => $url,
            'uploadedFiles' => $file !== null ? ['photo' => $file] : [],
        ]);
    }

    private function createPerson(): Person
    {
        $person = new Person(['first_name' => 'John', 'last_name' => 'Doe']);
        $person->save();
        return $person;
    }

    private function buildFileEntry(
        string $tmpName = '/tmp/fake',
        int $error = UPLOAD_ERR_OK
    ): array {
        return [
            'tmp_name' => $tmpName,
            'error'    => $error,
            'name'     => 'photo.jpg',
            'type'     => 'image/jpeg',
            'size'     => 100,
        ];
    }

    private function createTempFile(string $mimeType, string $content = ''): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tent_');
        $body = match ($mimeType) {
            'image/jpeg' => "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9",
            'image/png'  => "\x89PNG\r\n\x1A\n",
            'image/gif'  => 'GIF89a',
            default      => $content,
        };
        file_put_contents($path, $body);
        return $path;
    }
}
