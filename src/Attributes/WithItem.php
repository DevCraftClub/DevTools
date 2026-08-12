<?php

declare(strict_types=1);

namespace Devcraft\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class WithItem {

	/** @var list<string|array<array-key, mixed>> */
	private array $types;

	public function __construct(string|array ...$types) {
		$this->types = $types;
	}

	/** @return list<string|array<array-key, mixed>> */
	public function types(): array {
		return $this->types;
	}

}
