<?php

namespace Devcraft\Validation;

use ReflectionObject;
use ReflectionProperty;
use Devcraft\Interfaces\ValidationRule;

final class PropertyValidator {

	/**
	 * @return list<string>
	 */
	public function validateValue(ReflectionProperty $property, mixed $value): array {
		$errors = [];

		foreach($property->getAttributes() as $attribute) {
			$rule = $attribute->newInstance();

			if(!$rule instanceof ValidationRule) {
				continue;
			}

			$error = $rule->validate($value);

			if($error !== NULL) {
				$errors[] = $error;
			}
		}

		return $errors;
	}

	/**
	 * @return array<string, list<string>>
	 */
	public function validateObject(object $object): array {
		$errors = [];

		foreach((new ReflectionObject($object))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
			if($property->isStatic() || !$property->isInitialized($object)) {
				continue;
			}

			$propertyErrors = $this->validateValue($property, $property->getValue($object));

			if($propertyErrors !== []) {
				$errors[$property->getName()] = $propertyErrors;
			}
		}

		return $errors;
	}

}
