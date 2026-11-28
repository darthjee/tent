<?php

namespace Tent\Tests\Models\FolderLocation;

require_once __DIR__ . '/../../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Models\FolderLocation;
use Tent\Tests\Support\Utils\FileSystemUtils;

class FolderLocationContainsTest extends TestCase
{
    private string $testDir;
    private string $cacheDir;
    private string $outsideDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/folder_location_contains_test_' . uniqid();
        $this->cacheDir = $this->testDir . '/cache';
        $this->outsideDir = $this->testDir . '/outside';
        mkdir($this->cacheDir . '/users/GET', 0755, true);
        mkdir($this->outsideDir, 0755, true);
    }

    protected function tearDown(): void
    {
        FileSystemUtils::removeDirRecursive($this->testDir);
    }

    public function testContainsTheBaseDirectoryItself()
    {
        $this->assertTrue($this->location()->contains($this->cacheDir));
    }

    public function testContainsTheBaseDirectoryWithATrailingSlash()
    {
        $location = new FolderLocation($this->cacheDir . '/');

        $this->assertTrue($location->contains($this->cacheDir));
    }

    public function testContainsADirectoryInsideTheBase()
    {
        $this->assertTrue($this->location()->contains($this->cacheDir . '/users/GET'));
    }

    public function testContainsAFileInsideTheBase()
    {
        $file = $this->cacheDir . '/users/GET/file';
        file_put_contents($file, 'content');

        $this->assertTrue($this->location()->contains($file));
    }

    public function testDoesNotContainADirectoryOutsideTheBase()
    {
        $this->assertFalse($this->location()->contains($this->outsideDir));
    }

    public function testDoesNotContainTheParentOfTheBase()
    {
        $this->assertFalse($this->location()->contains($this->testDir));
    }

    public function testDoesNotContainADirectoryWhoseNameOnlyStartsLikeTheBase()
    {
        mkdir($this->testDir . '/cache-other');

        $this->assertFalse($this->location()->contains($this->testDir . '/cache-other'));
    }

    public function testDoesNotContainAPathWhichDoesNotExist()
    {
        $this->assertFalse($this->location()->contains($this->cacheDir . '/missing'));
    }

    public function testContainsNothingWhenTheBaseDoesNotExist()
    {
        $location = new FolderLocation($this->testDir . '/missing');

        $this->assertFalse($location->contains($this->cacheDir));
    }

    public function testContainsPathsWhenTheBaseIsASymbolicLink()
    {
        symlink($this->cacheDir, $this->testDir . '/link');
        $location = new FolderLocation($this->testDir . '/link');

        $this->assertTrue($location->contains($this->testDir . '/link/users/GET'));
        $this->assertTrue($location->contains($this->cacheDir . '/users'));
        $this->assertFalse($location->contains($this->outsideDir));
    }

    public function testDoesNotContainALinkPointingOutsideTheBase()
    {
        symlink($this->outsideDir, $this->cacheDir . '/link');

        $this->assertFalse($this->location()->contains($this->cacheDir . '/link'));
    }

    public function testDoesNotContainAPathGoingThroughALinkPointingOutsideTheBase()
    {
        mkdir($this->outsideDir . '/GET');
        symlink($this->outsideDir, $this->cacheDir . '/link');

        $this->assertFalse($this->location()->contains($this->cacheDir . '/link/GET'));
    }

    public function testContainsALinkPointingInsideTheBase()
    {
        symlink($this->cacheDir . '/users', $this->cacheDir . '/link');

        $this->assertTrue($this->location()->contains($this->cacheDir . '/link/GET'));
    }

    public function testNoticesADirectorySwappedForALinkAfterAPreviousCheck()
    {
        $dir = $this->cacheDir . '/users/GET';
        $this->assertTrue($this->location()->contains($dir));

        rmdir($dir);
        symlink($this->outsideDir, $dir);

        $this->assertFalse($this->location()->contains($dir));
    }

    private function location(): FolderLocation
    {
        return new FolderLocation($this->cacheDir);
    }
}
