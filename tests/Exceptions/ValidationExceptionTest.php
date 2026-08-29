<?php

declare(strict_types=1);

namespace Devcraft\DevTools\Tests\Exceptions;

use PHPUnit\Framework\TestCase;
use Devcraft\Exceptions\ValidationException;

final class ValidationExceptionTest extends TestCase {

	public function testMessageListsInvalidProperties(): void {
		$exception = new ValidationException([
			'count'  => ['must be int'],
			'status' => ['must be string'],
		]);

		self::assertSame('Validation failed for: count, status', $exception->getMessage());
	}

	public function testGetErrorsReturnsOriginalErrorMap(): void {
		$errors    = [
			'count'  => ['must be int'],
			'status' => ['must be string'],
		];
		$exception = new ValidationException($errors);

		self::assertSame($errors, $exception->getErrors());
	}

}
