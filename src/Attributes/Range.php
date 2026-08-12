<?php

namespace Devcraft\Attributes;

use Attribute;
use Devcraft\Interfaces\ValidationRule;

#[Attribute(Attribute::TARGET_PROPERTY|Attribute::IS_REPEATABLE)]
final readonly class Range implements ValidationRule {

	public function __construct(
		public int|float|null $min = NULL,
		public int|float|null $max = NULL,
	) {}

	public function validate(mixed $value): ?string {
		if($value === NULL) {
			return NULL;
		}

		if(!is_int($value) && !is_float($value)) {
			return 'must be numeric';
		}

		if($this->min !== NULL && $value < $this->min) {
			return sprintf('must be greater than or equal to %s', $this->min);
		}

		if($this->max !== NULL && $value > $this->max) {
			return sprintf('must be less than or equal to %s', $this->max);
		}

		return NULL;
	}

}