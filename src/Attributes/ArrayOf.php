<?php

namespace Devcraft\Attributes;

use Attribute;
use Devcraft\Interfaces\ValidationRule;

#[Attribute(Attribute::TARGET_PROPERTY|Attribute::IS_REPEATABLE)]
final readonly class ArrayOf implements ValidationRule {

	public function __construct(public string $type) {}

	public function validate(mixed $value): ?string {
		if($value === NULL) {
			return NULL;
		}

		if(!is_array($value) || !array_is_list($value)) {
			return sprintf('must be a list of %s', $this->type);
		}

		foreach($value as $element) {
			if(!$this->matches($element)) {
				return sprintf('must be a list of %s', $this->type);
			}
		}

		return NULL;
	}

	private function matches(mixed $value): bool {
		return match ($this->type) {
			'mixed'           => true,
			'string'          => is_string($value),
			'int', 'integer'  => is_int($value),
			'float', 'double' => is_float($value),
			'bool', 'boolean' => is_bool($value),
			'array'           => is_array($value),
			'object'          => is_object($value),
			default           => $value instanceof $this->type,
		};
	}

}
