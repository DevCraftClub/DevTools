<?php

declare(strict_types=1);

namespace Devcraft\DevTools\Tests\Runtime;

use TypeError;
use LogicException;
use ArgumentCountError;
use ReflectionProperty;
use BadMethodCallException;
use Devcraft\Abstracts\AbstractWith;
use Devcraft\Attributes\With;
use PHPUnit\Framework\TestCase;
use Devcraft\Attributes\WithItem;
use Devcraft\Runtime\WithHandler;
use PHPUnit\Framework\Attributes\DataProvider;

final class ScalarFixture extends AbstractWith
{
    #[With]
    private ?int $page = null;

    #[With]
    private string $flag = '';

    #[With]
    private ?string $starting_after = null;

    public function page(): ?int
    {
        return $this->page;
    }

    public function flag(): string
    {
        return $this->flag;
    }

    public function startingAfter(): ?string
    {
        return $this->starting_after;
    }
}

interface ItemContract
{
}

final class ItemObject implements ItemContract
{
}

enum ItemState: string
{
    case READY = 'ready';
}

final class ArrayFixture extends AbstractWith
{
    #[With, WithItem('string')]
    private array $items = [];

    #[WithItem(['int', 'string'], ['string', 'null'])]
    private array $labels = [];

    #[WithItem([ItemContract::class, ItemState::class])]
    private array $objects = [];

    public function items(): array
    {
        return $this->items;
    }

    public function labels(): array
    {
        return $this->labels;
    }

    public function objects(): array
    {
        return $this->objects;
    }
}

final class ConcreteDescriptorFixture extends AbstractWith
{
    #[WithItem(ItemObject::class)]
    private array $items = [];

    public function items(): array
    {
        return $this->items;
    }
}

final class BuiltinItemFixture extends AbstractWith
{
    #[WithItem('float')]
    private array $floats = [];

    #[WithItem('bool')]
    private array $booleans = [];

    #[WithItem('true')]
    private array $trueValues = [];

    #[WithItem('false')]
    private array $falseValues = [];

    #[WithItem('array')]
    private array $arrays = [];

    #[WithItem('object')]
    private array $objects = [];

    #[WithItem('iterable')]
    private array $iterables = [];

    #[WithItem('callable')]
    private array $callables = [];

    #[WithItem('mixed')]
    private array $mixedValues = [];

    public function values(): array
    {
        return [
            'floats' => $this->floats,
            'booleans' => $this->booleans,
            'true' => $this->trueValues,
            'false' => $this->falseValues,
            'arrays' => $this->arrays,
            'objects' => $this->objects,
            'iterables' => $this->iterables,
            'callables' => $this->callables,
            'mixed' => $this->mixedValues,
        ];
    }
}

class ParentFixture extends AbstractWith
{
    #[With]
    private string $parentValue = '';

    #[WithItem('string')]
    protected array $parentItems = [];

    public function parentValue(): string
    {
        return $this->parentValue;
    }

    public function parentItems(): array
    {
        return $this->parentItems;
    }
}

final class ChildFixture extends ParentFixture
{
}

class InverseParentFixture extends AbstractWith
{
    #[With]
    protected string $inverseValue = '';

    #[WithItem('string')]
    private array $inverseItems = [];

    public function inverseValue(): string
    {
        return $this->inverseValue;
    }

    public function inverseItems(): array
    {
        return $this->inverseItems;
    }
}

final class InverseChildFixture extends InverseParentFixture
{
}

final class UninitializedArrayFixture extends AbstractWith
{
    #[WithItem('string')]
    private array $items;
}

final class CollisionFixture extends AbstractWith
{
    #[With]
    private array $fooItem = [];

    #[WithItem('string')]
    private array $foo = [];
}

final class PublicTargetFixture extends AbstractWith
{
    #[With]
    public int $value = 0;
}

final class StaticTargetFixture extends AbstractWith
{
    #[With]
    private static int $value = 0;
}

final class ReadonlyTargetFixture extends AbstractWith
{
    #[With]
    private readonly int $value;

    public function __construct()
    {
        $this->value = 0;
    }
}

final class PublicItemTargetFixture extends AbstractWith
{
    #[WithItem('string')]
    public array $items = [];
}

final class StaticItemTargetFixture extends AbstractWith
{
    #[WithItem('string')]
    private static array $items = [];
}

final class ReadonlyItemTargetFixture extends AbstractWith
{
    #[WithItem('string')]
    private readonly array $items;

    public function __construct()
    {
        $this->items = [];
    }
}

final class NullableArrayTargetFixture extends AbstractWith
{
    #[WithItem('string')]
    private ?array $items = null;
}

final class EmptyDescriptorFixture extends AbstractWith
{
    #[WithItem]
    private array $items = [];
}

final class EmptyUnionFixture extends AbstractWith
{
    #[WithItem([])]
    private array $items = [];
}

final class MixedUnionFixture extends AbstractWith
{
    #[WithItem(['mixed', 'null'])]
    private array $items = [];
}

final class InvalidKeyFixture extends AbstractWith
{
    #[WithItem(['string', 'bool'], 'string')]
    private array $items = [];
}

final class TooManyDescriptorsFixture extends AbstractWith
{
    #[WithItem('string', 'string', 'string')]
    private array $items = [];
}

final class UnknownTypeFixture extends AbstractWith
{
    #[WithItem('Missing\\UnknownType')]
    private array $items = [];
}

final class NonArrayTargetFixture extends AbstractWith
{
    #[WithItem('string')]
    private string $items = '';
}

class ParentCollisionFixture extends AbstractWith
{
    #[With]
    private string $duplicate = '';
}

final class ChildCollisionFixture extends ParentCollisionFixture
{
    #[With]
    private string $duplicate = '';
}

final class CaseCollisionFixture extends AbstractWith
{
    #[With]
    private string $name = '';

    #[With]
    private string $NAME = '';
}

final class RepeatedWithFixture extends AbstractWith
{
    #[With, With]
    private string $value = '';
}

final class RepeatedWithItemFixture extends AbstractWith
{
    #[WithItem('string'), WithItem('string')]
    private array $items = [];
}

final class WithHandlerTest extends TestCase
{
    public function testWithMutatesAndReturnsSameInstance(): void
    {
        $fixture = new ScalarFixture();

        $returned = $fixture->withPage(3)->withFlag('false');

        self::assertSame($fixture, $returned);
        self::assertSame(3, $fixture->page());
        self::assertSame('false', $fixture->flag());
    }

    public function testWithRejectsScalarCoercion(): void
    {
        $fixture = new ScalarFixture();

        $this->expectException(TypeError::class);
        $fixture->withPage('3');
    }

    public function testWithAcceptsNullForNullableProperty(): void
    {
        $fixture = new ScalarFixture();

        self::assertSame($fixture, $fixture->withPage(null));
        self::assertNull($fixture->page());
    }

    public function testSnakeCasePropertyUsesStudlyMethodName(): void
    {
        $fixture = new ScalarFixture();

        $fixture->withStartingAfter('cursor');

        self::assertSame('cursor', $fixture->startingAfter());
    }

    public function testWithRequiresExactlyOneArgument(): void
    {
        $fixture = new ScalarFixture();

        try {
            $fixture->withPage();
            self::fail('Zero arguments must fail.');
        } catch (ArgumentCountError) {
            self::assertNull($fixture->page());
        }

        $this->expectException(ArgumentCountError::class);
        $fixture->withPage(1, 2);
    }

    public function testLookupIsCaseInsensitive(): void
    {
        $fixture = new ScalarFixture();

        self::assertTrue(WithHandler::handles($fixture, 'WITHPAGE'));
        self::assertSame($fixture, WithHandler::call($fixture, 'wItHpAgE', [8]));
        self::assertSame(8, $fixture->page());
    }

    public function testWithItemAppendsAndSupportsWholeArrayWith(): void
    {
        $fixture = new ArrayFixture();

        $returned = $fixture
            ->withItems(['first'])
            ->withItemsItem('second');

        self::assertSame($fixture, $returned);
        self::assertSame(['first', 'second'], $fixture->items());
    }

    public function testWithItemSetsAndReplacesMapValue(): void
    {
        $fixture = new ArrayFixture();

        $fixture
            ->withLabelsItem('status', 'ready')
            ->withLabelsItem('status', null)
            ->withLabelsItem(10, 'numeric');

        self::assertSame(['status' => null, 10 => 'numeric'], $fixture->labels());
    }

    public function testMapNormalizesDecimalNumericStringKeys(): void
    {
        $fixture = new ArrayFixture();

        $fixture->withLabelsItem('10', 'numeric');

        self::assertSame([10 => 'numeric'], $fixture->labels());
    }

    public function testWithItemSupportsInterfaceAndEnumUnion(): void
    {
        $fixture = new ArrayFixture();
        $object = new ItemObject();

        $fixture->withObjectsItem($object)->withObjectsItem(ItemState::READY);

        self::assertSame([$object, ItemState::READY], $fixture->objects());
    }

    public function testWithItemSupportsConcreteClassDescriptor(): void
    {
        $fixture = new ConcreteDescriptorFixture();
        $item = new ItemObject();

        self::assertSame($fixture, $fixture->withItemsItem($item));
        self::assertSame([$item], $fixture->items());
    }

    public function testWithItemSupportsEveryDeclaredBuiltinDescriptor(): void
    {
        $fixture = new BuiltinItemFixture();
        $object = new \stdClass();
        $iterable = new \ArrayIterator([1]);
        $callable = static fn (): string => 'ok';

        $fixture
            ->withFloatsItem(1.5)
            ->withBooleansItem(true)
            ->withTrueValuesItem(true)
            ->withFalseValuesItem(false)
            ->withArraysItem(['value'])
            ->withObjectsItem($object)
            ->withIterablesItem($iterable)
            ->withCallablesItem($callable)
            ->withMixedValuesItem(null);

        self::assertSame([
            'floats' => [1.5],
            'booleans' => [true],
            'true' => [true],
            'false' => [false],
            'arrays' => [['value']],
            'objects' => [$object],
            'iterables' => [$iterable],
            'callables' => [$callable],
            'mixed' => [null],
        ], $fixture->values());
    }

    public function testFloatDescriptorRejectsIntegerWithoutCoercion(): void
    {
        $fixture = new BuiltinItemFixture();

        $this->expectException(TypeError::class);
        $fixture->withFloatsItem(1);
    }

    public function testInvalidAppendDoesNotMutateArray(): void
    {
        $fixture = new ArrayFixture();
        $fixture->withItemsItem('valid');

        try {
            $fixture->withItemsItem(12);
            self::fail('Invalid item type must fail.');
        } catch (TypeError) {
            self::assertSame(['valid'], $fixture->items());
        }
    }

    public function testInvalidMapValueDoesNotPartiallyMutateArray(): void
    {
        $fixture = new ArrayFixture();
        $fixture->withLabelsItem('status', 'ready');

        try {
            $fixture->withLabelsItem('status', false);
            self::fail('Invalid map value must fail.');
        } catch (TypeError) {
            self::assertSame(['status' => 'ready'], $fixture->labels());
        }
    }

    public function testInvalidMapKeyDoesNotMutateArray(): void
    {
        $fixture = new ArrayFixture();
        $fixture->withLabelsItem('status', 'ready');

        try {
            $fixture->withLabelsItem(false, 'invalid');
            self::fail('Invalid map key must fail.');
        } catch (TypeError) {
            self::assertSame(['status' => 'ready'], $fixture->labels());
        }
    }

    public function testMapRejectsInvalidArityWithoutMutation(): void
    {
        $fixture = new ArrayFixture();

        foreach ([[], ['status'], ['status', 'ready', 'extra']] as $arguments) {
            try {
                WithHandler::call($fixture, 'withLabelsItem', $arguments);
                self::fail('Invalid map arity must fail.');
            } catch (ArgumentCountError) {
                self::assertSame([], $fixture->labels());
            }
        }
    }

    public function testAppendRejectsInvalidArityWithoutMutation(): void
    {
        $fixture = new ArrayFixture();

        foreach ([[], ['first', 'second']] as $arguments) {
            try {
                WithHandler::call($fixture, 'withItemsItem', $arguments);
                self::fail('Invalid append arity must fail.');
            } catch (ArgumentCountError) {
                self::assertSame([], $fixture->items());
            }
        }
    }

    public function testInheritedPrivateWithAndProtectedWithItemPropertiesWork(): void
    {
        $fixture = new ChildFixture();

        $fixture->withParentValue('parent')->withParentItemsItem('item');

        self::assertSame('parent', $fixture->parentValue());
        self::assertSame(['item'], $fixture->parentItems());
    }

    public function testInheritedProtectedWithAndPrivateWithItemPropertiesWork(): void
    {
        $fixture = new InverseChildFixture();

        $fixture->withInverseValue('inverse')->withInverseItemsItem('item');

        self::assertSame('inverse', $fixture->inverseValue());
        self::assertSame(['item'], $fixture->inverseItems());
    }

    #[DataProvider('invalidConfigurationProvider')]
    public function testInvalidConfigurationsThrowLogicException(object $fixture, string $method): void
    {
        $this->expectException(LogicException::class);
        WithHandler::handles($fixture, $method);
    }

    public static function invalidConfigurationProvider(): iterable
    {
        yield 'public' => [new PublicTargetFixture(), 'withValue'];
        yield 'static' => [new StaticTargetFixture(), 'withValue'];
        yield 'readonly' => [new ReadonlyTargetFixture(), 'withValue'];
        yield 'public item' => [new PublicItemTargetFixture(), 'withItemsItem'];
        yield 'static item' => [new StaticItemTargetFixture(), 'withItemsItem'];
        yield 'readonly item' => [new ReadonlyItemTargetFixture(), 'withItemsItem'];
        yield 'nullable array' => [new NullableArrayTargetFixture(), 'withItemsItem'];
        yield 'zero descriptors' => [new EmptyDescriptorFixture(), 'withItemsItem'];
        yield 'empty union' => [new EmptyUnionFixture(), 'withItemsItem'];
        yield 'mixed union' => [new MixedUnionFixture(), 'withItemsItem'];
        yield 'invalid key' => [new InvalidKeyFixture(), 'withItemsItem'];
        yield 'too many descriptors' => [new TooManyDescriptorsFixture(), 'withItemsItem'];
        yield 'unknown type' => [new UnknownTypeFixture(), 'withItemsItem'];
        yield 'non-array target' => [new NonArrayTargetFixture(), 'withItemsItem'];
        yield 'collision' => [new CollisionFixture(), 'withFooItem'];
        yield 'inherited collision' => [new ChildCollisionFixture(), 'withDuplicate'];
        yield 'case-insensitive collision' => [new CaseCollisionFixture(), 'withName'];
    }

    public function testRepeatedWithThrowsLogicExceptionIdentifyingAttributeAndProperty(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            '%s is repeated on %s::$%s.',
            With::class,
            RepeatedWithFixture::class,
            'value',
        ));

        WithHandler::handles(new RepeatedWithFixture(), 'withValue');
    }

    public function testRepeatedWithItemThrowsLogicExceptionIdentifyingAttributeAndProperty(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            '%s is repeated on %s::$%s.',
            WithItem::class,
            RepeatedWithItemFixture::class,
            'items',
        ));

        WithHandler::handles(new RepeatedWithItemFixture(), 'withItemsItem');
    }

    public function testUninitializedArrayThrowsLogicException(): void
    {
        $fixture = new UninitializedArrayFixture();

        $this->expectException(LogicException::class);
        $fixture->withItemsItem('item');
    }

    public function testUnknownHandlerCallThrowsBadMethodCallException(): void
    {
        $this->expectException(BadMethodCallException::class);
        WithHandler::call(new ScalarFixture(), 'missing', []);
    }

    public function testMetadataIsReusedForTheSameRuntimeClass(): void
    {
        WithHandler::handles(new ScalarFixture(), 'withPage');
        $property = new ReflectionProperty(WithHandler::class, 'cache');
        $before = $property->getValue();

        WithHandler::handles(new ScalarFixture(), 'withFlag');
        $after = $property->getValue();

        self::assertSame(
            $before[ScalarFixture::class]['withpage']['property'],
            $after[ScalarFixture::class]['withpage']['property'],
        );
        self::assertSame(
            $before[ScalarFixture::class]['withpage']['writer'],
            $after[ScalarFixture::class]['withpage']['writer'],
        );
        self::assertMetadataDoesNotRetainScalarFixture($after[ScalarFixture::class]);
    }

    private static function assertMetadataDoesNotRetainScalarFixture(mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                self::assertMetadataDoesNotRetainScalarFixture($item);
            }

            return;
        }

        if ($value instanceof \Closure) {
            $closure = new \ReflectionFunction($value);
            self::assertMetadataDoesNotRetainScalarFixture($closure->getClosureThis());

            foreach ($closure->getStaticVariables() as $item) {
                self::assertMetadataDoesNotRetainScalarFixture($item);
            }

            return;
        }

        if (is_object($value)) {
            self::assertNotInstanceOf(ScalarFixture::class, $value);
        }
    }
}
