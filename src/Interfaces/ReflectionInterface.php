<?php

namespace Devcraft\Interfaces;

interface ReflectionInterface {
	/**
	 * @throws \JsonException
	 */
	function toJson(): string;
	function toArray(): array;
	static function fromArray(array $data): static;
}