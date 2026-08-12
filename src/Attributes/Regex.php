<?php

namespace Devcraft\Attributes;

use Attribute;
use InvalidArgumentException;
use Devcraft\Interfaces\ValidationRule;

#[Attribute(Attribute::TARGET_PROPERTY|Attribute::IS_REPEATABLE)]
final readonly class Regex implements ValidationRule {

	public function __construct(public string $pattern) {
		if(@preg_match($pattern, '') === false) {
			throw new InvalidArgumentException('Invalid regular expression.');
		}
	}

	public function validate(mixed $value): ?string {
		if($value === NULL) {
			return NULL;
		}

		if(!is_string($value)) {
			return 'must be a string';
		}

		return preg_match($this->pattern, $value) === 1
			? NULL
			: sprintf('must match %s', $this->pattern);
	}

}