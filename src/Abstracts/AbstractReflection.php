<?php

namespace Devcraft\Abstracts;

use JsonException;
use Analog\Logger;
use Psr\Log\LoggerInterface;
use Devcraft\Mapper\ReflectionMapper;
use Devcraft\Interfaces\ReflectionInterface;

abstract class AbstractReflection implements ReflectionInterface {

	private static ?LoggerInterface $logger = NULL;

	public static function setLogger(LoggerInterface $logger): void {
		self::$logger = $logger;
	}

	public static function resetLogger(): void {
		self::$logger = NULL;
	}

	public function toArray(): array {
		return self::mapper()->toArray($this);
	}

	/**
	 * @throws JsonException
	 */
	public function toJson(): string {
		return json_encode(
			$this->toArray(),
			JSON_THROW_ON_ERROR
			|JSON_UNESCAPED_UNICODE
			|JSON_PRETTY_PRINT
			|JSON_UNESCAPED_SLASHES
			|JSON_INVALID_UTF8_SUBSTITUTE,
		);
	}

	public static function fromArray(array $data): static {
		$response = new static();
		self::mapper()->hydrate($response, $data);

		return $response;
	}

	private static function mapper(): ReflectionMapper {
		return new ReflectionMapper(self::$logger ??= new Logger());
	}

}
