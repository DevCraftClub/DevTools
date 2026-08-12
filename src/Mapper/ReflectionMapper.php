<?php

namespace Devcraft\Mapper;

use ReflectionType;
use ReflectionClass;
use ReflectionProperty;
use ReflectionUnionType;
use Psr\Log\LoggerInterface;
use ReflectionIntersectionType;
use Devcraft\Attributes\ArrayOf;
use Devcraft\Validation\PropertyValidator;
use Devcraft\Abstracts\AbstractReflection;
use Devcraft\Exceptions\ValidationException;

final class ReflectionMapper {

	/** @var array<class-string, list<ReflectionProperty>> */
	private static array $propertyCache = [];

	/** @var array<string, list<string>> */
	private array $errors = [];

	public function __construct(
		private readonly LoggerInterface   $logger,
		private readonly PropertyValidator $validator = new PropertyValidator(),
	) {}

	public function hydrate(object $target, array $data): object {
		$this->errors = [];
		$this->hydrateInto($target, $data, '');

		if($this->errors !== []) {
			throw new ValidationException($this->errors);
		}

		return $target;
	}

	public function toArray(object $source): array {
		$result = [];

		foreach($this->properties($source::class) as $property) {
			if(!$property->isInitialized($source)) {
				continue;
			}

			$result[$property->getName()] = $this->normalize($property->getValue($source));
		}

		return $result;
	}

	private function hydrateInto(object $target, array $data, string $parentPath): void {
		foreach($this->properties($target::class) as $property) {
			$name  = $property->getName();
			$path  = $parentPath === ''? $name : $parentPath . '.' . $name;
			$value = $data[$name] ?? NULL;

			if(!array_key_exists($name, $data) || $value === NULL) {
				$this->applyNullFallback($target, $property, $path);
				continue;
			}

			$conversion = $this->convertProperty($property, $value, $path);

			if(!$conversion['success']) {
				$this->recordFailures($target, $property, $conversion['failures']);
				continue;
			}

			$validationErrors = $this->validator->validateValue($property, $conversion['value']);

			if($validationErrors !== []) {
				$failures = array_map(
					static fn(string $message): array => [
						'path'        => $path,
						'message'     => $message,
						'actual_type' => get_debug_type($conversion['value']),
					],
					$validationErrors,
				);
				$this->recordFailures($target, $property, $failures);
				continue;
			}

			$property->setValue($target, $conversion['value']);
		}
	}

	private function applyNullFallback(object $target, ReflectionProperty $property, string $path): void {
		if($property->hasDefaultValue()) {
			return;
		}

		if($this->allowsNull($property)) {
			$property->setValue($target, NULL);

			return;
		}

		$this->recordFailures($target, $property, [
			[
				'path'        => $path,
				'message'     => 'is required',
				'actual_type' => 'null',
			],
		]);
	}

	/**
	 * @return array{
	 *     success: bool,
	 *     value: mixed,
	 *     failures: list<array{path: string, message: string, actual_type: string}>
	 * }
	 */
	private function convertProperty(ReflectionProperty $property, mixed $value, string $path): array {
		$conversion = $this->convertByType($property->getType(), $value, $path);

		if(!$conversion['success']) {
			return $conversion;
		}

		$attributes = $property->getAttributes(ArrayOf::class);

		if($attributes === []) {
			return $conversion;
		}

		if(!is_array($conversion['value']) || !array_is_list($conversion['value'])) {
			return $this->failure($path, 'must be a list', $conversion['value']);
		}

		/** @var ArrayOf $arrayOf */
		$arrayOf  = $attributes[0]->newInstance();
		$mapped   = [];
		$failures = [];

		foreach($conversion['value'] as $index => $element) {
			$elementPath       = $path . '.' . $index;
			$elementConversion = $this->convertNamed($arrayOf->type, $element, $elementPath);

			if(!$elementConversion['success']) {
				array_push($failures, ...$elementConversion['failures']);
				continue;
			}

			$mapped[] = $elementConversion['value'];
		}

		return $failures === []
			? $this->success($mapped)
			: ['success' => false, 'value' => NULL, 'failures' => $failures];
	}

	/**
	 * @return array{
	 *     success: bool,
	 *     value: mixed,
	 *     failures: list<array{path: string, message: string, actual_type: string}>
	 * }
	 */
	private function convertByType(?ReflectionType $type, mixed $value, string $path): array {
		if($type === NULL) {
			return $this->success($value);
		}

		if($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
			return $this->failure($path, 'uses an unsupported composite type', $value);
		}

		return $this->convertNamed($type->getName(), $value, $path);
	}

	/**
	 * @return array{
	 *     success: bool,
	 *     value: mixed,
	 *     failures: list<array{path: string, message: string, actual_type: string}>
	 * }
	 */
	private function convertNamed(string $type, mixed $value, string $path): array {
		$converted = match ($type) {
			'mixed'           => [true, $value],
			'string'          => [is_string($value), $value],
			'int', 'integer'  => $this->toInt($value),
			'float', 'double' => $this->toFloat($value),
			'bool', 'boolean' => $this->toBool($value),
			'array'           => [is_array($value), $value],
			'object'          => [is_object($value), $value],
			default           => $this->toObject($type, $value, $path),
		};

		if(count($converted) === 3) {
			return $converted;
		}

		return $converted[0]
			? $this->success($converted[1])
			: $this->failure($path, sprintf('must be %s', $type), $value);
	}

	/** @return array{bool, mixed} */
	private function toInt(mixed $value): array {
		if(is_int($value)) {
			return [true, $value];
		}

		if(!is_string($value)) {
			return [false, NULL];
		}

		$converted = filter_var($value, FILTER_VALIDATE_INT);

		return $converted === false? [false, NULL] : [true, $converted];
	}

	/** @return array{bool, mixed} */
	private function toFloat(mixed $value): array {
		if(is_float($value) || is_int($value)) {
			return [true, (float) $value];
		}

		if(!is_string($value)) {
			return [false, NULL];
		}

		$converted = filter_var($value, FILTER_VALIDATE_FLOAT);

		return $converted === false? [false, NULL] : [true, $converted];
	}

	/** @return array{bool, mixed} */
	private function toBool(mixed $value): array {
		if(is_bool($value)) {
			return [true, $value];
		}

		if(is_int($value) && ($value === 0 || $value === 1)) {
			return [true, (bool) $value];
		}

		if(!is_string($value)) {
			return [false, NULL];
		}

		$converted = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

		return $converted === NULL? [false, NULL] : [true, $converted];
	}

	/**
	 * @return array{
	 *     success: bool,
	 *     value: mixed,
	 *     failures: list<array{path: string, message: string, actual_type: string}>
	 * }
	 */
	private function toObject(string $type, mixed $value, string $path): array {
		if((class_exists($type) || interface_exists($type)) && $value instanceof $type) {
			return $this->success($value);
		}

		if(is_subclass_of($type, AbstractReflection::class) && is_array($value)) {
			$nested = new $type();
			$this->hydrateInto($nested, $value, $path);

			return $this->success($nested);
		}

		return $this->failure($path, sprintf('must be %s', $type), $value);
	}

	/**
	 * @param   list<array{path: string, message: string, actual_type: string}>  $failures
	 */
	private function recordFailures(
		object             $target,
		ReflectionProperty $property,
		array              $failures,
	): void {
		foreach($failures as $failure) {
			$this->logger->error('Response validation failed.', [
				'class'       => $property->getDeclaringClass()->getName(),
				'property'    => $failure['path'],
				'expected'    => $failure['message'],
				'actual_type' => $failure['actual_type'],
			]);
		}

		if($this->allowsNull($property)) {
			$property->setValue($target, NULL);

			return;
		}

		foreach($failures as $failure) {
			$this->errors[$failure['path']][] = $failure['message'];
		}
	}

	private function allowsNull(ReflectionProperty $property): bool {
		return $property->getType()?->allowsNull() ?? true;
	}

	/**
	 * @return array{
	 *     success: true,
	 *     value: mixed,
	 *     failures: list<array{path: string, message: string, actual_type: string}>
	 * }
	 */
	private function success(mixed $value): array {
		return ['success' => true, 'value' => $value, 'failures' => []];
	}

	/**
	 * @return array{
	 *     success: false,
	 *     value: null,
	 *     failures: list<array{path: string, message: string, actual_type: string}>
	 * }
	 */
	private function failure(string $path, string $message, mixed $actual): array {
		return [
			'success'  => false,
			'value'    => NULL,
			'failures' => [
				[
					'path'        => $path,
					'message'     => $message,
					'actual_type' => get_debug_type($actual),
				],
			],
		];
	}

	private function normalize(mixed $value): mixed {
		if($value instanceof AbstractReflection) {
			return $this->toArray($value);
		}

		if(!is_array($value)) {
			return $value;
		}

		return array_map(fn(mixed $element): mixed => $this->normalize($element), $value);
	}

	/**
	 * @param   class-string  $class
	 *
	 * @return list<ReflectionProperty>
	 * @throws \ReflectionException
	 */
	private function properties(string $class): array {
		return self::$propertyCache[$class] ??= array_values(array_filter(
			(new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC),
			static fn(ReflectionProperty $property): bool => !$property->isStatic(),
		));
	}

}
