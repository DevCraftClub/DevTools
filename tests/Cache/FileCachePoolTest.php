<?php

declare(strict_types=1);

namespace Devcraft\DevTools\Tests\Cache;

use PHPUnit\Framework\TestCase;
use Devcraft\Cache\FileCachePool;
use Devcraft\Exceptions\InvalidArgument;

final class FileCachePoolTest extends TestCase {

	private string $dir;

	private FileCachePool $pool;

	public function testSetGetHit(): void {
		$item = $this->pool->getItem('ns/key1');
		self::assertFalse($item->isHit());

		$item->set(['a' => 1]);
		self::assertTrue($this->pool->save($item));

		$loaded = $this->pool->getItem('ns/key1');
		self::assertTrue($loaded->isHit());
		self::assertSame(['a' => 1], $loaded->get());
	}

	public function testMissAndDelete(): void {
		self::assertFalse($this->pool->hasItem('missing'));
		$item = $this->pool->getItem('to-delete');
		$item->set('x');
		$this->pool->save($item);
		self::assertTrue($this->pool->deleteItem('to-delete'));
		self::assertFalse($this->pool->hasItem('to-delete'));
	}

	public function testExpiry(): void {
		$item = $this->pool->getItem('exp');
		$item->set('old');
		$item->expiresAfter(1);
		$this->pool->save($item);
		self::assertTrue($this->pool->getItem('exp')->isHit());
		sleep(2);
		self::assertFalse($this->pool->getItem('exp')->isHit());
	}

	public function testClearNamespace(): void {
		foreach(['Translation/a', 'Translation/b', 'Other/c'] as $key) {
			$item = $this->pool->getItem($key);
			$item->set($key);
			$this->pool->save($item);
		}

		$this->pool->clearNamespace('Translation');
		self::assertFalse($this->pool->hasItem('Translation/a'));
		self::assertFalse($this->pool->hasItem('Translation/b'));
		self::assertTrue($this->pool->hasItem('Other/c'));
	}

	public function testClearAll(): void {
		$item = $this->pool->getItem('x/y');
		$item->set(1);
		$this->pool->save($item);
		$this->pool->clear();
		self::assertFalse($this->pool->hasItem('x/y'));
	}

	public function testDeferredCommit(): void {
		$item = $this->pool->getItem('def/key');
		$item->set('deferred');
		$this->pool->saveDeferred($item);
		self::assertFalse($this->pool->hasItem('def/key'));
		self::assertTrue($this->pool->commit());
		self::assertTrue($this->pool->hasItem('def/key'));
		self::assertSame('deferred', $this->pool->getItem('def/key')->get());
	}

	public function testInvalidKey(): void {
		$this->expectException(InvalidArgument::class);
		$this->pool->getItem('bad{key}');
	}

	public function testDefaultTtlNeverExpiresWhenZero(): void {
		$forever = $this->dir . '/forever';
		mkdir($forever, 0775, true);
		$pool = new FileCachePool($forever, 0);
		$item = $pool->getItem('k');
		$item->set('v');
		$pool->save($item);
		self::assertTrue($pool->getItem('k')->isHit());
		$pool->clear();
		@rmdir($forever);
	}

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/devtools-cache-' . bin2hex(random_bytes(4));
		mkdir($this->dir, 0775, true);
		$this->pool = new FileCachePool($this->dir, 3600);
	}

	protected function tearDown(): void {
		$this->pool->clear();
		@rmdir($this->dir);
	}

}
