<?php

declare(strict_types=1);

namespace Devcraft\DevTools\Tests\Mapper;

use Devcraft\Abstracts\AbstractReflection;
use Devcraft\Attributes\ArrayOf;
use Devcraft\Attributes\Range;
use Devcraft\Exceptions\ValidationException;
use Devcraft\Mapper\ReflectionMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class ReflectionMapperScalarFixture extends AbstractReflection
{
	public int $count;
	public float $ratio;
	public bool $active;
}

final class ReflectionMapperChildFixture extends AbstractReflection
{
	public string $label;
}

final class ReflectionMapperCompositeFixture extends AbstractReflection
{
	public ReflectionMapperChildFixture $child;

	#[ArrayOf('int')]
	public array $ids = [];

	#[ArrayOf(ReflectionMapperChildFixture::class)]
	public array $children = [];
}

final class ReflectionMapperNullableFixture extends AbstractReflection
{
	public ?int $optional;

	#[Range(min: 10)]
	public ?int $score = 20;
}

final class ReflectionMapperInvalidFixture extends AbstractReflection
{
	public int $required;

	#[Range(min: 5)]
	#[Range(max: 10)]
	public int $score;

	public ReflectionMapperChildFixture $child;

	#[ArrayOf('int')]
	public array $ids = [];
}

final class ReflectionMapperLogger extends AbstractLogger
{
	/** @var list<array{level: string, message: string, context: array<string, mixed>}> */
	public array $records = [];

	public function log($level, string|\Stringable $message, array $context = []): void
	{
		$this->records[] = [
			'level' => (string) $level,
			'message' => (string) $message,
			'context' => $context,
		];
	}
}

final class ReflectionMapperTest extends TestCase
{
	public function testHydrateConvertsSupportedScalarValues(): void
	{
		$fixture = new ReflectionMapperScalarFixture();
		$mapper = new ReflectionMapper(new ReflectionMapperLogger());

		$mapper->hydrate($fixture, [
			'count' => '12',
			'ratio' => '2.5',
			'active' => 'false',
		]);

		self::assertSame(12, $fixture->count);
		self::assertSame(2.5, $fixture->ratio);
		self::assertFalse($fixture->active);
	}

	public function testHydrateBuildsNestedDtosAndArrayOfValues(): void
	{
		$fixture = new ReflectionMapperCompositeFixture();
		$mapper = new ReflectionMapper(new ReflectionMapperLogger());

		$mapper->hydrate($fixture, [
			'child' => ['label' => 'primary'],
			'ids' => ['1', 2, '3'],
			'children' => [
				['label' => 'first'],
				['label' => 'second'],
			],
		]);

		self::assertInstanceOf(ReflectionMapperChildFixture::class, $fixture->child);
		self::assertSame('primary', $fixture->child->label);
		self::assertSame([1, 2, 3], $fixture->ids);
		self::assertContainsOnlyInstancesOf(ReflectionMapperChildFixture::class, $fixture->children);
		self::assertSame(['first', 'second'], array_map(
			static fn(ReflectionMapperChildFixture $child): string => $child->label,
			$fixture->children,
		));
	}

	public function testHydrateUsesNullForNullablePropertiesWhenMissingOrInvalid(): void
	{
		$fixture = new ReflectionMapperNullableFixture();
		$mapper = new ReflectionMapper(new ReflectionMapperLogger());

		$mapper->hydrate($fixture, ['score' => 9]);

		self::assertNull($fixture->optional);
		self::assertNull($fixture->score);
	}

	public function testHydrateAggregatesValidationErrors(): void
	{
		$logger = new ReflectionMapperLogger();
		$mapper = new ReflectionMapper($logger);

		try {
			$mapper->hydrate(new ReflectionMapperInvalidFixture(), [
				'score' => 100,
				'child' => ['label' => 123],
				'ids' => ['1', 'bad'],
			]);
			self::fail('Invalid payload must raise a validation exception.');
		} catch (ValidationException $exception) {
			self::assertSame([
				'required' => ['is required'],
				'score' => ['must be less than or equal to 10'],
				'child.label' => ['must be string'],
				'ids.1' => ['must be int'],
			], $exception->getErrors());
		}

		self::assertSame([
			'required',
			'score',
			'child.label',
			'ids.1',
		], array_map(
			static fn(array $record): string => $record['context']['property'],
			$logger->records,
		));
	}
}
