<?php

declare(strict_types=1);

namespace Devcraft\DevTools\Tests\Attributes;

use Attribute;
use ReflectionClass;
use Devcraft\Attributes\With;
use PHPUnit\Framework\TestCase;
use Devcraft\Attributes\WithItem;

final class AttributeTest extends TestCase
{
    public function testWithTargetsOnlyPropertiesAndIsNotRepeatable(): void
    {
        $attribute = (new ReflectionClass(With::class))
            ->getAttributes(Attribute::class)[0]
            ->newInstance();

        self::assertSame(Attribute::TARGET_PROPERTY, $attribute->flags);
    }

    public function testWithItemPreservesOrderedTypePositions(): void
    {
        $attribute = new WithItem('string', ['int', 'null']);

        self::assertSame(['string', ['int', 'null']], $attribute->types());
    }

    public function testWithItemTargetsOnlyPropertiesAndIsNotRepeatable(): void
    {
        $attribute = (new ReflectionClass(WithItem::class))
            ->getAttributes(Attribute::class)[0]
            ->newInstance();

        self::assertSame(Attribute::TARGET_PROPERTY, $attribute->flags);
    }
}
