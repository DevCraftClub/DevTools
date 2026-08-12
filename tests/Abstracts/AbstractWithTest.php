<?php

declare(strict_types=1);

namespace Devcraft\DevTools\Tests\Abstracts;

use BadMethodCallException;
use Devcraft\Abstracts\AbstractWith;
use Devcraft\Attributes\With;
use PHPUnit\Framework\TestCase;

final class AbstractWithFixture extends AbstractWith
{
	#[With]
	private ?int $page = null;

	#[With]
	private string $cursor = '';

	public function page(): ?int
	{
		return $this->page;
	}

	public function cursor(): string
	{
		return $this->cursor;
	}
}

final class AbstractWithTest extends TestCase
{
	public function testRoutesKnownVirtualMethodsAndSupportsFluentChaining(): void
	{
		$fixture = new AbstractWithFixture();

		$returned = $fixture
			->withPage(7)
			->withCursor('next-page');

		self::assertSame($fixture, $returned);
		self::assertSame(7, $fixture->page());
		self::assertSame('next-page', $fixture->cursor());
	}

	public function testUnknownMethodThrowsBadMethodCallException(): void
	{
		$fixture = new AbstractWithFixture();

		$this->expectException(BadMethodCallException::class);
		$this->expectExceptionMessage(sprintf(
			'Call to undefined method %s::%s()',
			AbstractWithFixture::class,
			'missing',
		));

		$fixture->missing();
	}
}

