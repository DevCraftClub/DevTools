<?php

declare(strict_types=1);

namespace Devcraft\DevTools\Tests\Validation;

use Devcraft\Attributes\Filter;
use Devcraft\Attributes\Range;
use Devcraft\Attributes\Regex;
use Devcraft\Validation\PropertyValidator;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class PropertyValidatorValueFixture
{
	#[Range(min: 10)]
	#[Regex('/^\d+$/')]
	public mixed $value = null;
}

final class PropertyValidatorObjectFixture
{
	#[Filter(FILTER_VALIDATE_INT)]
	#[Regex('/^\d+$/')]
	public mixed $code = null;

	#[Range(min: 1)]
	public ?int $optional = null;

	#[Range(min: 1)]
	private int $ignored = 0;
}

final class PropertyValidatorTest extends TestCase
{
	public function testValidateValueCollectsErrorsFromMultipleRules(): void
	{
		$validator = new PropertyValidator();
		$property = new ReflectionProperty(PropertyValidatorValueFixture::class, 'value');

		self::assertSame([
			'must be numeric',
			'must match /^\d+$/',
		], $validator->validateValue($property, 'abc'));
	}

	public function testValidateValueAllowsNull(): void
	{
		$validator = new PropertyValidator();
		$property = new ReflectionProperty(PropertyValidatorValueFixture::class, 'value');

		self::assertSame([], $validator->validateValue($property, null));
	}

	public function testValidateObjectReturnsOnlyPublicInitializedPropertyErrors(): void
	{
		$fixture = new PropertyValidatorObjectFixture();
		$fixture->code = 'abc';

		$validator = new PropertyValidator();

		self::assertSame([
			'code' => [
				sprintf('must pass filter %d', FILTER_VALIDATE_INT),
				'must match /^\d+$/',
			],
		], $validator->validateObject($fixture));
	}
}
