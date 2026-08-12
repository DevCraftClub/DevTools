<?php

namespace Devcraft\Interfaces;

interface ValidationRule {

	/**
	 * Returns null when valid, otherwise a value-free error message.
	 */
	public function validate(mixed $value): ?string;

}