<?php

use League\Flysystem\Config;
use \HocVT\Flysystem\Filer\FilerAdapter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HocVT\Flysystem\Filer\FilerAdapter
 */
class FilerAdapterTest  extends TestCase
{
    /**
     * The memory adapter.
     *
     * @var \HocVT\Flysystem\Filer\FilerAdapter
     */
    protected $adapter;

    protected function setUp(): void
    {
        $this->adapter = new FilerAdapter();
        $this->adapter->write('file.txt', 'contents', new Config());
    }

    public function __destruct()
    {
        $this->adapter->deleteDir('baz');
        $this->adapter->deleteDir('dir');
        $this->adapter->deleteDir('foo');
        $this->adapter->delete('file.txt');
        $this->adapter->delete('new_file.txt');
    }

    public function testRootHasTimestamp()
    {
        $this->assertArrayHasKey('timestamp', $this->adapter->getTimestamp(''));
    }

    public function testCopy()
    {
        $this->assertTrue($this->adapter->copy('file.txt', 'dir/new_file.txt'));
        $this->assertSame('contents', $this->adapter->read('dir/new_file.txt')['contents']);

        $this->assertFalse($this->adapter->copy('file.txt', 'dir/new_file.txt/other.txt'));
    }

    public function testCreateDir()
    {
        $result = $this->adapter->createDir('dir/subdir', new Config());
        $this->assertSame(3, count($result));
        $this->assertSame('dir/subdir', $result['path']);
        $this->assertSame('dir', $result['type']);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertTrue($this->adapter->has('dir'));
        $this->assertTrue($this->adapter->has('dir/subdir'));

        $result = $this->adapter->createDir('dir', new Config());
        $this->assertSame(3, count($result));
        $this->assertSame('dir', $result['path']);
        $this->assertSame('dir', $result['type']);
        $this->assertArrayHasKey( 'timestamp', $result);

        $this->assertFalse($this->adapter->createDir('file.txt', new Config()));
        $this->assertFalse($this->adapter->createDir('file.txt/dir', new Config()));
    }

    public function testDelete()
    {
        $this->assertTrue($this->adapter->delete('file.txt'));
        $this->assertFalse($this->adapter->has('file.txt'));
        $this->assertFalse($this->adapter->delete('file.txt'));
    }

    public function testDeleteDir()
    {
        $this->adapter->createDir('dir/subdir', new Config());
        $this->assertTrue($this->adapter->deleteDir('dir'));
        $this->assertFalse($this->adapter->has('dir/subdir'));
        $this->assertFalse($this->adapter->has('dir'));

        $this->assertFalse($this->adapter->deleteDir('dir'));
    }

    public function testGetMetadata()
    {
        $meta = $this->adapter->getMetadata('file.txt');

        $this->assertSame(5, count($meta));
        $this->assertSame('file.txt', $meta['path']);
        $this->assertSame('file', $meta['type']);
        $this->assertSame(8, $meta['size']);
        $this->assertSame('public', $meta['visibility']);
        $this->assertTrue(is_int($meta['timestamp']));

        $this->adapter->write('dir/file.txt', '', new Config(['mimetype' => 'mime/type']));

        $meta = $this->adapter->getMetadata('dir/file.txt');

        $this->assertCount(6, $meta);
        $this->assertSame('mime/type', $meta['mimetype']);
    }

    public function testGetMimetype()
    {
        $this->adapter->write('dir/file.txt', 'contents', new Config(['mimetype' => 'mime/type']));

        $meta = $this->adapter->getMimetype('file.txt');
        $this->assertSame('text/plain', $meta['mimetype']);

        $meta = $this->adapter->getMimetype('dir/file.txt');
        $this->assertSame('mime/type', $meta['mimetype']);
    }

    public function testGetSize()
    {
        $meta = $this->adapter->getSize('file.txt');
        $this->assertSame(8, $meta['size']);
    }

    public function testGetTimestamp()
    {
        $meta = $this->adapter->getTimestamp('file.txt');
        $this->assertTrue(is_int($meta['timestamp']));
    }

    public function testGetVisibility()
    {
        $this->assertSame('public', $this->adapter->getVisibility('file.txt')['visibility']);
    }

    public function testHas()
    {
        $this->assertTrue($this->adapter->has('file.txt'));
        $this->assertFalse($this->adapter->has('no_file.txt'));
    }

    public function testListContents()
    {
        $result = $this->adapter->listContents('');
        $this->assertSame(5, count($result));
        $this->assertNotContains('/', $result[0]['path']);

        $this->adapter->write('dir/file.txt', 'contents', new Config());
        $this->assertTrue($this->adapter->has('dir/file.txt'));

        $result = $this->adapter->listContents('', true);var_dump($result);
        $this->assertSame(3, count($result));

        $result = $this->adapter->listContents('dir', true);
        $this->assertSame(1, count($result));

        $this->assertSame([], $this->adapter->listContents('no_dir'));
    }

    public function testRead()
    {
        $this->assertSame('contents', $this->adapter->read('file.txt')['contents']);
        $this->assertSame('file.txt', $this->adapter->read('file.txt')['path']);
    }

    public function testReadStream()
    {
        $result = $this->adapter->readStream('file.txt');

        $this->assertSame('contents', stream_get_contents($result['stream']));
        $this->assertSame('file.txt', $result['path']);
    }

    public function testRename()
    {
        $this->assertTrue($this->adapter->rename('file.txt', 'dir/subdir/file.txt'));
        $this->assertTrue($this->adapter->has('dir'));
        $this->assertTrue($this->adapter->has('dir/subdir'));

        $this->assertFalse($this->adapter->rename('dir/subdir/file.txt', 'dir/subdir/file.txt/new_file.txt'));
    }

    public function testSetVisibility()
    {
        $result = $this->adapter->setVisibility('file.txt', 'private');
        $this->assertSame('private', $result['visibility']);
        $this->assertSame('private', $this->adapter->getVisibility('file.txt')['visibility']);

        $this->assertFalse($this->adapter->setVisibility('no_file.txt', 'public'));
    }

    public function testUpdate()
    {
        $result = $this->adapter->update('file.txt', 'new contents', new Config(['visibility' => 'private']));
        $this->assertSame('file.txt', $result['path']);
        $this->assertSame('private', $result['visibility']);
        $this->assertSame('new contents', $this->adapter->read('file.txt')['contents']);
    }

    public function testWrite()
    {
        $result = $this->adapter->write('new_file.txt', 'new contents', new Config());
        $this->assertSame('new_file.txt', $result['path']);
        $this->assertSame('file', $result['type']);
        $this->assertFalse($this->adapter->write('file.txt/new_file.txt', 'contents', new Config()));
    }

    public function testTimestampCanBeConfigured()
    {
        $now = 1460000000;

        $adapter = new FilerAdapter();

//        $adapter->createDir('foo/bar', new Config(['timestamp' => $now]));
//        $this->assertEquals($now, $adapter->getTimestamp('foo')['timestamp']);
//        $this->assertEquals($now, $adapter->getTimestamp('foo/bar')['timestamp']);

        $adapter->write('baz/file.txt', 'content', new Config(['timestamp' => $now]));
//        $this->assertEquals($now, $adapter->getTimestamp('baz')['timestamp']);
        $this->assertEquals($now, $adapter->getTimestamp('baz/file.txt')['timestamp']);

        $earlier = 1300000000;

        $adapter->update('baz/file.txt', 'new contents', new Config(['timestamp' => $earlier]));
//        $this->assertEquals($now, $adapter->getTimestamp('baz')['timestamp']);
        $this->assertEquals($earlier, $adapter->getTimestamp('baz/file.txt')['timestamp']);
    }
}