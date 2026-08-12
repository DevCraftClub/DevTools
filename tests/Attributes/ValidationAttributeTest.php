<?php

declare(strict_types=1);

namespace Devcraft\DevTools\Tests\Attributes;

use Devcraft\Attributes\ArrayOf;
use Devcraft\Attributes\Filter;
use Devcraft\Attributes\Range;
use Devcraft\Attributes\Regex;
use Devcraft\Interfaces\ValidationRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidationAttributeTest extends TestCase
{
	#[DataProvider('validationRuleProvider')]
	public function testValidationRulesAcceptValidValues(
		ValidationRule $rule,
		mixed $validValue,
		mixed $invalidValue,
		string $expectedMessage,
	): void {
		self::assertNull($rule->validate($validValue));
	}

	#[DataProvider('validationRuleProvider')]
	public function testValidationRulesRejectInvalidValues(
		ValidationRule $rule,
		mixed $validValue,
		mixed $invalidValue,
		string $expectedMessage,
	): void {
		self::assertSame($expectedMessage, $rule->validate($invalidValue));
	}

	#[DataProvider('validationRuleProvider')]
	public function testValidationRulesAllowNull(
		ValidationRule $rule,
		mixed $validValue,
		mixed $invalidValue,
		string $expectedMessage,
	): void {
		self::assertNull($rule->validate(null));
	}

	public static function validationRuleProvider(): iterable
	{
		yield 'filter' => [
			new Filter(FILTER_VALIDATE_EMAIL),
			'user@example.com',
			'invalid-email',
			sprintf('must pass filter %d', FILTER_VALIDATE_EMAIL),
		];

		yield 'range' => [
			new Range(min: 1, max: 5),
			3,
			0,
			'must be greater than or equal to 1',
		];

		yield 'regex' => [
			new Regex('/^[a-z]{3}\d{2}$/'),
			'abc12',
			'abc',
			'must match /^[a-z]{3}\d{2}$/',
		];

		yield 'array of' => [
			new ArrayOf('string'),
			['a', 'b'],
			['a', 2],
			'must be a list of string',
		];
	}
}
