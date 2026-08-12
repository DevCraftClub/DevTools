<?php

declare(strict_types=1);

namespace Devcraft\DevTools\Tests\Abstracts;

use Devcraft\Abstracts\AbstractReflection;
use Devcraft\Attributes\Range;
use Devcraft\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class AbstractReflectionAddressFixture extends AbstractReflection
{
	public string $city;
}

final class AbstractReflectionProfileFixture extends AbstractReflection
{
	public string $name;
	public AbstractReflectionAddressFixture $address;
}

final class AbstractReflectionListFixture extends AbstractReflection
{
	public string $name;

	/** @var list<AbstractReflectionAddressFixture> */
	public array $addresses = [];
}

final class AbstractReflectionLoggedFixture extends AbstractReflection
{
	#[Range(min: 1)]
	public int $count;
}

final class CollectingLogger extends AbstractLogger
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

final class AbstractReflectionTest extends TestCase
{
	protected function tearDown(): void
	{
		AbstractReflection::resetLogger();
	}

	public function testToArrayNormalizesNestedDtosAndLists(): void
	{
		$fixture = new AbstractReflectionListFixture();
		$fixture->name = 'Ada';

		$first = new AbstractReflectionAddressFixture();
		$first->city = 'Berlin';

		$second = new AbstractReflectionAddressFixture();
		$second->city = 'Paris';

		$fixture->addresses = [$first, $second];

		self::assertSame([
			'name' => 'Ada',
			'addresses' => [
				['city' => 'Berlin'],
				['city' => 'Paris'],
			],
		], $fixture->toArray());
	}

	public function testFromArrayHydratesNestedDto(): void
	{
		$fixture = AbstractReflectionProfileFixture::fromArray([
			'name' => 'Ada',
			'address' => ['city' => 'Berlin'],
		]);

		self::assertSame('Ada', $fixture->name);
		self::assertInstanceOf(AbstractReflectionAddressFixture::class, $fixture->address);
		self::assertSame('Berlin', $fixture->address->city);
	}

	public function testToJsonUsesExpectedEncodingOptions(): void
	{
		$fixture = AbstractReflectionProfileFixture::fromArray([
			'name' => 'Ada',
			'address' => ['city' => 'Berlin'],
		]);

		self::assertSame(<<<'JSON'
{
    "name": "Ada",
    "address": {
        "city": "Berlin"
    }
}
JSON, $fixture->toJson());
	}

	public function testCustomLoggerReceivesValidationFailures(): void
	{
		$logger = new CollectingLogger();
		AbstractReflection::setLogger($logger);

		try {
			AbstractReflectionLoggedFixture::fromArray(['count' => 0]);
			self::fail('Invalid data must raise a validation exception.');
		} catch (ValidationException $exception) {
			self::assertSame(['count' => ['must be greater than or equal to 1']], $exception->getErrors());
		}

		self::assertCount(1, $logger->records);
		self::assertSame('error', $logger->records[0]['level']);
		self::assertSame('Response validation failed.', $logger->records[0]['message']);
		self::assertSame([
			'class' => AbstractReflectionLoggedFixture::class,
			'property' => 'count',
			'expected' => 'must be greater than or equal to 1',
			'actual_type' => 'int',
		], $logger->records[0]['context']);
	}
}
