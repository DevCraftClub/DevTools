<?php

declare(strict_types=1);

namespace Devcraft\DevTools\Tests\Validation;

use ReflectionProperty;
use Devcraft\Attributes\Regex;
use Devcraft\Attributes\Range;
use PHPUnit\Framework\TestCase;
use Devcraft\Attributes\Filter;
use Devcraft\Validation\PropertyValidator;

final class PropertyValidatorObjectFixture {

	#[Filter(FILTER_VALIDATE_INT)]
	#[Regex('/^\d+$/')]
	public mixed $code = NULL;

	#[Range(min: 1)]
	public ?int $optional = NULL;

	#[Range(min: 1)]
	private int $ignored = 0;

}

final class PropertyValidatorTest extends TestCase {

	public function testValidateValueCollectsErrorsFromMultipleRules(): void {
		$validator = new PropertyValidator();
		$property  = new ReflectionProperty(PropertyValidatorValueFixture::class, 'value');

		self::assertSame([
			'must be numeric',
			'must match /^\d+$/',
		], $validator->validateValue($property, 'abc'));
	}

	public function testValidateValueAllowsNull(): void {
		$validator = new PropertyValidator();
		$property  = new ReflectionProperty(PropertyValidatorValueFixture::class, 'value');

		self::assertSame([], $validator->validateValue($property, NULL));
	}

	public function testValidateObjectReturnsOnlyPublicInitializedPropertyErrors(): void {
		$fixture       = new PropertyValidatorObjectFixture();
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

final class PropertyValidatorValueFixture {

	#[Range(min: 10)]
	#[Regex('/^\d+$/')]
	public mixed $value = NULL;

}
