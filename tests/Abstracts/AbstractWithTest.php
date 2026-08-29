<?php

declare(strict_types=1);

namespace Devcraft\DevTools\Tests\Abstracts;

use Lombok\Getter;
use Lombok\Setter;
use BadMethodCallException;
use Devcraft\Attributes\With;
use PHPUnit\Framework\TestCase;
use Devcraft\Abstracts\AbstractWith;

final class AbstractWithTest extends TestCase {

	public function testWithRoutesToWithHandlerAndGetterReadsValue(): void {
		$fixture = new AccessorFixture();

		$returned = $fixture->withPage(4);

		self::assertSame($fixture, $returned);
		self::assertSame(4, $fixture->getPage());
	}

	public function testSetterMutatesAndReturnsSameInstance(): void {
		$fixture = new AccessorFixture();

		$returned = $fixture->setPage(9);

		self::assertSame($fixture, $returned);
		self::assertSame(9, $fixture->getPage());
	}

	public function testBooleanPropertyUsesIsPrefix(): void {
		$fixture = new AccessorFixture();

		self::assertFalse($fixture->isVisible());
		self::assertSame($fixture, $fixture->setVisible(true));
		self::assertTrue($fixture->isVisible());
	}

	public function testUnknownMethodThrowsBadMethodCallException(): void {
		$fixture = new AccessorFixture();

		$this->expectException(BadMethodCallException::class);
		$fixture->missing();
	}

}

#[Getter, Setter]
final class AccessorFixture extends AbstractWith {

	#[With]
	private ?int $page = NULL;

	private bool $visible = false;

}
