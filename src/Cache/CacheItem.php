<?php

declare(strict_types=1);

namespace Devcraft\Cache;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;

/**
 * PSR-6 cache item for {@see FileCachePool}.
 */
final class CacheItem implements CacheItemInterface {

	private mixed $value = NULL;

	private bool $hit = false;

	private ?int $expiry = NULL;

	private bool $isDefaultExpiry = true;

	public function __construct(
		private readonly string $key,
	) {}

	public function getKey(): string {
		return $this->key;
	}

	public function get(): mixed {
		return $this->isHit()? $this->value : NULL;
	}

	public function isHit(): bool {
		return $this->hit;
	}

	public function set(mixed $value): static {
		$this->value = $value;

		return $this;
	}

	public function expiresAt(?DateTimeInterface $expiration): static {
		$this->isDefaultExpiry = false;

		if($expiration === NULL) {
			$this->expiry = NULL;

			return $this;
		}

		$this->expiry = $expiration->getTimestamp();

		return $this;
	}

	public function expiresAfter(int|DateInterval|null $time): static {
		$this->isDefaultExpiry = false;

		if($time === NULL) {
			$this->expiry = NULL;

			return $this;
		}

		if($time instanceof DateInterval) {
			$this->expiry = (new DateTimeImmutable())->add($time)->getTimestamp();

			return $this;
		}

		if($time <= 0) {
			$this->expiry = NULL;

			return $this;
		}

		$this->expiry = time() + $time;

		return $this;
	}

	/**
	 * @internal
	 */
	public function markHit(mixed $value, ?int $expiry): void {
		$this->hit             = true;
		$this->value           = $value;
		$this->expiry          = $expiry;
		$this->isDefaultExpiry = false;
	}

	/**
	 * @internal
	 */
	public function markMiss(): void {
		$this->hit   = false;
		$this->value = NULL;
	}

	/**
	 * @internal
	 */
	public function rawValue(): mixed {
		return $this->value;
	}

	/**
	 * @internal
	 */
	public function expiryUnix(): ?int {
		return $this->expiry;
	}

	/**
	 * @internal
	 */
	public function usesDefaultExpiry(): bool {
		return $this->isDefaultExpiry;
	}

	/**
	 * @internal
	 */
	public function isExpired(): bool {
		return $this->expiry !== NULL && $this->expiry <= time();
	}

}
