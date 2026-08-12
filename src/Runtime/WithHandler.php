<?php

declare(strict_types=1);

namespace Devcraft\Runtime;

use Closure;
use TypeError;
use LogicException;
use ReflectionClass;
use ArgumentCountError;
use ReflectionProperty;
use ReflectionNamedType;
use BadMethodCallException;
use Devcraft\Attributes\With;
use Devcraft\Attributes\WithItem;

final class WithHandler {

	private const SET    = 'set';

	private const APPEND = 'append';

	private const MAP    = 'map';

	/**
	 * @var array<class-string, array<string, array{
	 *     method: string,
	 *     kind: string,
	 *     property: ReflectionProperty,
	 *     writer: Closure,
	 *     types: list<list<string>>
	 * }>>
	 */
	private static array $cache = [];

	public static function handles(object $target, string $methodName): bool {
		return isset(self::metadata($target::class)[strtolower($methodName)]);
	}

	public static function call(object $target, string $methodName, array $arguments): object {
		$operation = self::metadata($target::class)[strtolower($methodName)] ?? NULL;

		if($operation === NULL) {
			throw new BadMethodCallException(sprintf(
				'Unknown virtual method %s::%s().',
				$target::class,
				$methodName,
			));
		}

		$expected = $operation['kind'] === self::MAP? 2 : 1;
		self::assertArgumentCount($target, $operation['method'], $arguments, $expected);

		if($operation['kind'] === self::SET) {
			($operation['writer'])($target, $arguments[0]);

			return $target;
		}

		self::callItemOperation($target, $operation, $arguments);

		return $target;
	}

	/** @return array<string, array{method: string, kind: string, property: ReflectionProperty, writer: Closure, types: list<list<string>>}> */
	private static function metadata(string $className): array {
		return self::$cache[$className] ??= self::buildMetadata($className);
	}

	/** @return array<string, array{method: string, kind: string, property: ReflectionProperty, writer: Closure, types: list<list<string>>}>
	 * @throws \ReflectionException
	 */
	private static function buildMetadata(string $className): array {
		$operations = [];
		$class      = new ReflectionClass($className);

		do {
			foreach($class->getProperties() as $property) {
				if($property->getDeclaringClass()->getName() !== $class->getName()) {
					continue;
				}

				$withAttributes = $property->getAttributes(With::class);
				if($withAttributes !== []) {
					self::assertNotRepeated($withAttributes, $property, With::class);
					self::assertCommonTarget($property, With::class);
					$method = 'with' . self::studly($property->getName());
					self::register($operations,
						$method,
						self::operation(
							$method,
							self::SET,
							$property,
							self::writer($property, self::SET),
						));
				}

				$itemAttributes = $property->getAttributes(WithItem::class);
				if($itemAttributes !== []) {
					self::assertNotRepeated($itemAttributes, $property, WithItem::class);
					self::assertCommonTarget($property, WithItem::class);
					self::assertArrayTarget($property);
					$attribute = $itemAttributes[0]->newInstance();
					$types     = self::normalizePositions($property, $attribute->types());
					$kind      = count($types) === 1? self::APPEND : self::MAP;
					$method    = 'with' . self::studly($property->getName()) . 'Item';
					self::register($operations,
						$method,
						self::operation(
							$method,
							$kind,
							$property,
							self::writer($property, $kind),
							$types,
						));
				}
			}

			$class = $class->getParentClass();
		} while($class !== false);

		return $operations;
	}

	/** @param   list<list<string>>  $types */
	private static function operation(
		string             $method,
		string             $kind,
		ReflectionProperty $property,
		Closure            $writer,
		array              $types = [],
	): array {
		return compact('method', 'kind', 'property', 'writer', 'types');
	}

	private static function register(array &$operations, string $method, array $operation): void {
		$key = strtolower($method);

		if(isset($operations[$key])) {
			throw new LogicException(sprintf('Virtual method collision for %s().', $method));
		}

		$operations[$key] = $operation;
	}

	private static function assertCommonTarget(ReflectionProperty $property, string $attribute): void {
		if($property->isPublic() || $property->isStatic() || $property->isReadOnly()) {
			throw new LogicException(sprintf(
				'%s is invalid on %s::$%s.',
				$attribute,
				$property->getDeclaringClass()->getName(),
				$property->getName(),
			));
		}
	}

	private static function assertNotRepeated(array $attributes, ReflectionProperty $property, string $attribute): void {
		foreach($attributes as $reflectionAttribute) {
			if($reflectionAttribute->isRepeated()) {
				throw new LogicException(sprintf(
					'%s is repeated on %s::$%s.',
					$attribute,
					$property->getDeclaringClass()->getName(),
					$property->getName(),
				));
			}
		}
	}

	private static function assertArrayTarget(ReflectionProperty $property): void {
		$type = $property->getType();

		if(!$type instanceof ReflectionNamedType
		   || $type->getName() !== 'array'
		   || $type->allowsNull()
		) {
			throw new LogicException(sprintf(
				'WithItem requires non-nullable array property %s::$%s.',
				$property->getDeclaringClass()->getName(),
				$property->getName(),
			));
		}
	}

	private static function studly(string $propertyName): string {
		return str_replace(' ', '', ucwords(str_replace('_', ' ', $propertyName)));
	}

	private static function writer(ReflectionProperty $property, string $kind): Closure {
		$name   = $property->getName();
		$writer = match ($kind) {
			self::SET    => static function(object $target, mixed $value) use ($name): void {
				$target->{$name} = $value;
			},
			self::APPEND => static function(object $target, mixed $value) use ($name): void {
				$target->{$name}[] = $value;
			},
			self::MAP    => static function(object $target, mixed $key, mixed $value) use ($name): void {
				$target->{$name}[$key] = $value;
			},
			default      => throw new LogicException(sprintf('Unknown operation kind %s.', $kind)),
		};

		return Closure::bind(
			$writer,
			NULL,
			$property->getDeclaringClass()->getName(),
		);
	}

	private static function assertArgumentCount(
		object $target,
		string $method,
		array  $arguments,
		int    $expected,
	): void {
		if(count($arguments) !== $expected) {
			throw new ArgumentCountError(sprintf(
				'%s::%s() expects exactly %d argument%s, %d given.',
				$target::class,
				$method,
				$expected,
				$expected === 1? '' : 's',
				count($arguments),
			));
		}
	}

	private static function callItemOperation(object $target, array $operation, array $arguments): void {
		$property = $operation['property'];

		if(!$property->isInitialized($target)) {
			throw new LogicException(sprintf(
				'Array property %s::$%s is not initialized.',
				$property->getDeclaringClass()->getName(),
				$property->getName(),
			));
		}

		foreach($operation['types'] as $index => $allowedTypes) {
			self::assertMatches(
				$arguments[$index],
				$allowedTypes,
				sprintf('%s() argument %d', $operation['method'], $index + 1),
			);
		}

		($operation['writer'])($target, ...$arguments);
	}

	/** @return list<list<string>> */
	private static function normalizePositions(ReflectionProperty $property, array $positions): array {
		$count = count($positions);

		if($count < 1 || $count > 2) {
			throw new LogicException(sprintf(
				'WithItem on %s::$%s requires one or two type descriptors.',
				$property->getDeclaringClass()->getName(),
				$property->getName(),
			));
		}

		$normalized = [];

		foreach($positions as $position) {
			$alternatives = is_string($position)? [$position] : $position;

			if(!array_is_list($alternatives) || $alternatives === []) {
				throw new LogicException('WithItem unions must be non-empty lists.');
			}

			$types = [];

			foreach($alternatives as $type) {
				if(!is_string($type) || trim($type) === '') {
					throw new LogicException('WithItem type names must be non-empty strings.');
				}

				$type                     = self::normalizeTypeName($type);
				$types[strtolower($type)] = $type;
			}

			$types = array_values($types);

			if(in_array('mixed', $types, true) && count($types) !== 1) {
				throw new LogicException('mixed cannot be combined with other WithItem types.');
			}

			$normalized[] = $types;
		}

		if($count === 2
		   && array_diff($normalized[0], ['int', 'string']) !== []
		) {
			throw new LogicException('WithItem map keys may contain only int and string.');
		}

		return $normalized;
	}

	private static function normalizeTypeName(string $type): string {
		$type     = trim($type);
		$builtin  = strtolower($type);
		$builtins = [
			'string',
			'int',
			'float',
			'bool',
			'true',
			'false',
			'null',
			'array',
			'object',
			'iterable',
			'callable',
			'mixed',
		];

		if(in_array($builtin, $builtins, true)) {
			return $builtin;
		}

		if(in_array($builtin, ['void', 'never'], true)
		   || trait_exists($type)
		   || (!class_exists($type) && !interface_exists($type) && !enum_exists($type))
		) {
			throw new LogicException(sprintf('Unknown or unsupported WithItem type %s.', $type));
		}

		return ltrim($type, '\\');
	}

	/** @param   list<string>  $allowedTypes */
	private static function assertMatches(mixed $value, array $allowedTypes, string $subject): void {
		foreach($allowedTypes as $type) {
			if(self::matches($value, $type)) {
				return;
			}
		}

		throw new TypeError(sprintf(
			'%s must be of type %s, %s given.',
			$subject,
			implode('|', $allowedTypes),
			get_debug_type($value),
		));
	}

	private static function matches(mixed $value, string $type): bool {
		return match ($type) {
			'string'   => is_string($value),
			'int'      => is_int($value),
			'float'    => is_float($value),
			'bool'     => is_bool($value),
			'true'     => $value === true,
			'false'    => $value === false,
			'null'     => $value === NULL,
			'array'    => is_array($value),
			'object'   => is_object($value),
			'iterable' => is_iterable($value),
			'callable' => is_callable($value),
			'mixed'    => true,
			default    => $value instanceof $type,
		};
	}

}
