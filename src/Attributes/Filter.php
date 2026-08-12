<?php

namespace Devcraft\Attributes;

use Attribute;
use Devcraft\Interfaces\ValidationRule;

#[Attribute(Attribute::TARGET_PROPERTY|Attribute::IS_REPEATABLE)]
final readonly class Filter implements ValidationRule {

	/**
	 * @param   array<string, mixed>|int  $options
	 */
	public function __construct(
		public int       $filter,
		public array|int $options = 0,
	) {}

	public function validate(mixed $value): ?string {
		if($value === NULL) {
			return NULL;
		}

		if($this->filter === FILTER_VALIDATE_BOOLEAN) {
			$options = is_array($this->options)
				? array_replace($this->options, [
					'flags' => ($this->options['flags'] ?? 0)|FILTER_NULL_ON_FAILURE,
				])
				: $this->options|FILTER_NULL_ON_FAILURE;

			return filter_var($value, $this->filter, $options) === NULL
				? sprintf('must pass filter %d', $this->filter)
				: NULL;
		}

		return filter_var($value, $this->filter, $this->options) === false
			? sprintf('must pass filter %d', $this->filter)
			: NULL;
	}

}
