<?php

namespace Devcraft\Exceptions;

class ValidationException extends \InvalidArgumentException {

	/**
	 * @param   array<string, list<string>>  $errors
	 */
	public function __construct(private readonly array $errors) {
		parent::__construct(sprintf(
			'Validation failed for: %s',
			implode(', ', array_keys($errors)),
		));
	}

	/**
	 * @return array<string, list<string>>
	 */
	public function getErrors(): array {
		return $this->errors;
	}

}