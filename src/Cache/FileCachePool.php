<?php

declare(strict_types=1);

namespace Devcraft\Cache;

use Throwable;
use FilesystemIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Devcraft\Exceptions\InvalidArgument;

/**
 * File-based PSR-6 cache pool. Keys may contain `/` as a namespace separator
 * (extension beyond the PSR-6 reserved character set).
 *
 * On disk: `{baseDir}/{key}.cache` with JSON envelope `{e, f, v}`
 * (expiry unix timestamp, format `j`|`s`, value).
 *
 * Extension: {@see clearNamespace()} deletes every key under prefix `prefix/`.
 */
final class FileCachePool implements CacheItemPoolInterface {

	/** @var array<string, CacheItem> */
	private array $deferred = [];

	/**
	 * @param   string    $baseDir            Cache root directory
	 * @param   int|null  $defaultTtlSeconds  Default TTL; null or 0 means no expiry
	 */
	public function __construct(
		private string        $baseDir,
		private readonly ?int $defaultTtlSeconds = NULL,
	) {
		$this->baseDir = rtrim($baseDir, "/\\");
	}

	public function getItem(string $key): CacheItemInterface {
		$this->validateKey($key);
		$item = new CacheItem($key);
		$path = $this->pathForKey($key);

		if(!is_file($path)) {
			$item->markMiss();

			return $item;
		}

		$envelope = $this->readEnvelope($path);

		if($envelope === NULL) {
			@unlink($path);
			$item->markMiss();

			return $item;
		}

		$expiry = $envelope['e'];

		if($expiry !== NULL && $expiry <= time()) {
			@unlink($path);
			$item->markMiss();

			return $item;
		}

		$item->markHit($envelope['v'], $expiry);

		return $item;
	}

	/**
	 * @param   array<string>  $keys
	 *
	 * @return iterable<string, CacheItemInterface>
	 */
	public function getItems(array $keys = []): iterable {
		$result = [];

		foreach($keys as $key) {
			$result[$key] = $this->getItem($key);
		}

		return $result;
	}

	public function hasItem(string $key): bool {
		return $this->getItem($key)->isHit();
	}

	public function clear(): bool {
		$this->deferred = [];

		if(!is_dir($this->baseDir)) {
			return true;
		}

		$this->wipeDirectoryContents($this->baseDir);

		return true;
	}

	public function deleteItem(string $key): bool {
		$this->validateKey($key);
		unset($this->deferred[$key]);
		$path = $this->pathForKey($key);

		if(is_file($path)) {
			@unlink($path);
		}

		$this->removeEmptyParents(dirname($path));

		return true;
	}

	/**
	 * @param   array<string>  $keys
	 */
	public function deleteItems(array $keys): bool {
		$ok = true;

		foreach($keys as $key) {
			$ok = $this->deleteItem($key) && $ok;
		}

		return $ok;
	}

	public function save(CacheItemInterface $item): bool {
		if(!$item instanceof CacheItem) {
			return false;
		}

		$key = $item->getKey();
		$this->validateKey($key);

		$expiry = $item->expiryUnix();

		if($item->usesDefaultExpiry()) {
			$ttl = $this->defaultTtlSeconds;

			if($ttl !== NULL && $ttl > 0) {
				$expiry = time() + $ttl;
			} else {
				$expiry = NULL;
			}
		}

		if($expiry !== NULL && $expiry <= time()) {
			return $this->deleteItem($key);
		}

		$path = $this->pathForKey($key);
		$dir  = dirname($path);

		if(!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
			return false;
		}

		$payload = $this->encodeEnvelope($item->rawValue(), $expiry);
		$tmp     = $path . '.tmp.' . bin2hex(random_bytes(4));

		if(@file_put_contents($tmp, $payload, LOCK_EX) === false) {
			@unlink($tmp);

			return false;
		}

		if(!@rename($tmp, $path)) {
			@unlink($tmp);

			return false;
		}

		return true;
	}

	public function saveDeferred(CacheItemInterface $item): bool {
		if(!$item instanceof CacheItem) {
			return false;
		}

		$this->validateKey($item->getKey());
		$this->deferred[$item->getKey()] = $item;

		return true;
	}

	public function commit(): bool {
		$ok = true;

		foreach($this->deferred as $item) {
			$ok = $this->save($item) && $ok;
		}

		$this->deferred = [];

		return $ok;
	}

	/**
	 * Deletes every key under prefix `{prefix}/` (DevTools extension, not PSR-6).
	 */
	public function clearNamespace(string $prefix): bool {
		$prefix = trim($prefix, "/\\");

		if($prefix === '') {
			return $this->clear();
		}

		$this->validateKey($prefix . '/x');

		foreach(array_keys($this->deferred) as $key) {
			if($key === $prefix || str_starts_with($key, $prefix . '/')) {
				unset($this->deferred[$key]);
			}
		}

		$dir = $this->baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $prefix);

		if(is_dir($dir)) {
			$this->wipeDirectoryContents($dir);
			@rmdir($dir);
		}

		return true;
	}

	public function getBaseDir(): string {
		return $this->baseDir;
	}

	/**
	 * PSR-6 reserved characters: `{}()/\@:` — `/` is allowed as a namespace separator.
	 */
	private function validateKey(string $key): void {
		if($key === '' || strlen($key) > 64 * 8) {
			throw new InvalidArgument('Cache key is empty or too long');
		}

		if(preg_match('/[{}()\\\\@:]/', $key) === 1) {
			throw new InvalidArgument('Cache key contains reserved characters');
		}

		if(str_contains($key, '..')) {
			throw new InvalidArgument('Cache key must not contain ..');
		}
	}

	private function pathForKey(string $key): string {
		$relative = str_replace('/', DIRECTORY_SEPARATOR, $key);

		return $this->baseDir . DIRECTORY_SEPARATOR . $relative . '.cache';
	}

	/**
	 * @return array{e: ?int, v: mixed}|null
	 */
	private function readEnvelope(string $path): ?array {
		$raw = @file_get_contents($path);

		if($raw === false || $raw === '') {
			return NULL;
		}

		try {
			$data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
		} catch(Throwable) {
			return NULL;
		}

		if(!is_array($data) || !array_key_exists('v', $data) || !array_key_exists('e', $data)) {
			return NULL;
		}

		$expiry = $data['e'];
		$expiry = $expiry === NULL? NULL : (int) $expiry;
		$format = (string) ($data['f'] ?? 'j');
		$value  = $data['v'];

		if($format === 's' && is_string($value)) {
			$decoded = base64_decode($value, true);

			if($decoded === false) {
				return NULL;
			}

			$value = unserialize($decoded, ['allowed_classes' => true]);
		}

		return ['e' => $expiry, 'v' => $value];
	}

	private function encodeEnvelope(mixed $value, ?int $expiry): string {
		try {
			json_encode($value, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
			$format  = 'j';
			$payload = $value;
		} catch(Throwable) {
			$format  = 's';
			$payload = base64_encode(serialize($value));
		}

		return (string) json_encode(
			['e' => $expiry, 'f' => $format, 'v' => $payload],
			JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES,
		);
	}

	private function wipeDirectoryContents(string $dir): void {
		if(!is_dir($dir)) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach($iterator as $fileInfo) {
			$path = $fileInfo->getPathname();

			if($fileInfo->isFile() || $fileInfo->isLink()) {
				@unlink($path);
			} elseif($fileInfo->isDir()) {
				@rmdir($path);
			}
		}
	}

	private function removeEmptyParents(string $dir): void {
		$dir  = rtrim($dir, "/\\");
		$base = $this->baseDir;

		while($dir !== '' && $dir !== $base && str_starts_with($dir, $base . DIRECTORY_SEPARATOR)) {
			if(!is_dir($dir)) {
				break;
			}

			$files = @scandir($dir);

			if($files === false || count($files) > 2) {
				break;
			}

			@rmdir($dir);
			$dir = dirname($dir);
		}
	}

}
