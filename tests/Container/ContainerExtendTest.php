<?php

namespace Illuminate\Tests\Container;

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use stdClass;

class ContainerExtendTest extends TestCase
{
    public function testExtendedBindings()
    {
        $container = new Container;
        $container['foo'] = 'foo';
        $container->extend('foo', function ($old, $container) {
            return $old.'bar';
        });

        $this->assertSame('foobar', $container->make('foo'));

        $container = new Container;

        $container->singleton('foo', function () {
            return (object) ['name' => 'taylor'];
        });
        $container->extend('foo', function ($old, $container) {
            $old->age = 26;

            return $old;
        });

        $result = $container->make('foo');

        $this->assertSame('taylor', $result->name);
        $this->assertEquals(26, $result->age);
        $this->assertSame($result, $container->make('foo'));
    }

    public function testExtendInstancesArePreserved()
    {
        $container = new Container;
        $container->bind('foo', function () {
            $obj = new stdClass;
            $obj->foo = 'bar';

            return $obj;
        });

        $obj = new stdClass;
        $obj->foo = 'foo';
        $container->instance('foo', $obj);
        $container->extend('foo', function ($obj, $container) {
            $obj->bar = 'baz';

            return $obj;
        });
        $container->extend('foo', function ($obj, $container) {
            $obj->baz = 'foo';

            return $obj;
        });

        $this->assertSame('foo', $container->make('foo')->foo);
        $this->assertSame('baz', $container->make('foo')->bar);
        $this->assertSame('foo', $container->make('foo')->baz);
    }

    public function testExtendIsLazyInitialized()
    {
        ContainerLazyExtendStub::$initialized = false;

        $container = new Container;
        $container->bind(ContainerLazyExtendStub::class);
        $container->extend(ContainerLazyExtendStub::class, function ($obj, $container) {
            $obj->init();

            return $obj;
        });
        $this->assertFalse(ContainerLazyExtendStub::$initialized);
        $container->make(ContainerLazyExtendStub::class);
        $this->assertTrue(ContainerLazyExtendStub::$initialized);
    }

    public function testExtendCanBeCalledBeforeBind()
    {
        $container = new Container;
        $container->extend('foo', function ($old, $container) {
            return $old.'bar';
        });
        $container['foo'] = 'foo';

        $this->assertSame('foobar', $container->make('foo'));
    }

    public function testExtendInstanceRebindingCallback()
    {
        $_SERVER['_test_rebind'] = false;

        $container = new Container;
        $container->rebinding('foo', function () {
            $_SERVER['_test_rebind'] = true;
        });

        $obj = new stdClass;
        $container->instance('foo', $obj);

        $container->extend('foo', function ($obj, $container) {
            return $obj;
        });

        $this->assertTrue($_SERVER['_test_rebind']);
    }

    public function testExtendBindRebindingCallback()
    {
        $_SERVER['_test_rebind'] = false;

        $container = new Container;
        $container->rebinding('foo', function () {
            $_SERVER['_test_rebind'] = true;
        });
        $container->bind('foo', function () {
            return new stdClass;
        });

        $this->assertFalse($_SERVER['_test_rebind']);

        $container->make('foo');

        $container->extend('foo', function ($obj, $container) {
            return $obj;
        });

        $this->assertTrue($_SERVER['_test_rebind']);
    }

    public function testExtensionWorksOnAliasedBindings()
    {
        $container = new Container;
        $container->singleton('something', function () {
            return 'some value';
        });
        $container->alias('something', 'something-alias');
        $container->extend('something-alias', function ($value) {
            return $value.' extended';
        });

        $this->assertSame('some value extended', $container->make('something'));
    }

    public function testMultipleExtends()
    {
        $container = new Container;
        $container['foo'] = 'foo';
        $container->extend('foo', function ($old, $container) {
            return $old.'bar';
        });
        $container->extend('foo', function ($old, $container) {
            return $old.'baz';
        });

        $this->assertSame('foobarbaz', $container->make('foo'));
    }

    public function testUnsetExtend()
    {
        $container = new Container;
        $container->bind('foo', function () {
            $obj = new stdClass;
            $obj->foo = 'bar';

            return $obj;
        });

        $container->extend('foo', function ($obj, $container) {
            $obj->bar = 'baz';

            return $obj;
        });

        unset($container['foo']);
        $container->forgetExtenders('foo');

        $container->bind('foo', function () {
            return 'foo';
        });

        $this->assertSame('foo', $container->make('foo'));
    }

    public function testGloballyExtendedBindings()
    {
        // Given a simple "foo" binding.
        $container = new Container;
        $container['foo'] = 'foo';

        // When we append "bar" to all bindings.
        $container->extend(function ($old, $container) {
            return $old.'bar';
        });

        // Then we resolve "foobar".
        $this->assertSame('foobar', $container->make('foo'));
    }

    public function testGloballyExtendedSingletons()
    {
        // Given an registered "foo" singleton.
        $container = new Container;
        $container->singleton('foo', function () {
            return (object) ['name' => 'taylor'];
        });

        // When we add the age property to all bindings.
        $container->extend(function ($old, $container) {
            $old->age = 26;

            return $old;
        });

        // Then the "foo" singleton has the "age" property.
        $result = $container->make('foo');
        $this->assertSame('taylor', $result->name);
        $this->assertEquals(26, $result->age);

        // And it stays the same instance no matter how many times we resolve it.
        $this->assertSame($result, $container->make('foo'));
    }

    public function testResolvedInstancesAreNotAffectedByNewGlobalExtenders()
    {
        // Given an already resolved "foo" instance.
        $container = new Container;
        $container->instance('foo', (object) ['foo' => 'original']);

        // When we add a new global extender.
        $container->extend(function ($obj, $container) {
            $obj->foo = 'extended';

            return $obj;
        });

        // Then the "foo" instance was not extended.
        $this->assertSame('original', $container->make('foo')->foo);
    }

    public function testGlobalExtendersAreLazyInitialized()
    {
        // Given an uninitialized class.
        ContainerLazyExtendStub::$initialized = false;

        // And a simple binding of that class.
        $container = new Container;
        $container->bind(ContainerLazyExtendStub::class);

        // When we add a global extender that initializes that class.
        $container->extend(function ($obj, $container) {
            $obj->init();

            return $obj;
        });

        // Then the class is still not initialized.
        $this->assertFalse(ContainerLazyExtendStub::$initialized);

        // but will be initialized when resolved from the container.
        $container->make(ContainerLazyExtendStub::class);
        $this->assertTrue(ContainerLazyExtendStub::$initialized);
    }

    public function testGlobalExtendersCanBeCalledBeforeBind()
    {
        // Given a registered global extenders that appends "bar" to all bindings.
        $container = new Container;
        $container->extend(function ($old, $container) {
            return $old.'bar';
        });

        // When we, later on, bind "foo" to the container.
        $container['foo'] = 'foo';

        // Then it gets resolved to "foobar".
        $this->assertSame('foobar', $container->make('foo'));
    }

    public function testGlobalExtendersDoNotTriggerInstanceRebindingCallbacks()
    {
        // Given an indicator that the instance has no rebound.
        $testRebound = false;

        // And a rebinding callback that toggles that indicator to true.
        $container = new Container;
        $container->rebinding('foo', function () use (&$testRebound) {
            $testRebound = true;
        });

        // And a "foo" instance already bound.
        $obj = new stdClass;
        $container->instance('foo', $obj);

        // When we register a new global extender.
        $container->extend(function ($obj, $container) {
            return $obj;
        });

        // Then the rebinding callback was not called.
        $this->assertFalse($testRebound);
    }

    public function testGlobalExtendersDoNotTriggerBindRebindingCallback()
    {
        // Given an indicator that the instance has no rebound.
        $testRebound = false;

        // And a rebinding callback that toggles that indicator to true.
        $container = new Container;
        $container->rebinding('foo', function () use (&$testRebound) {
            $testRebound = true;
        });

        // And an existing "foo" binding that has been resolved once.
        $container->bind('foo', function () {
            return new stdClass;
        });
        $container->make('foo');
        $this->assertFalse($testRebound);

        // When we register a new global extender.
        $container->extend(function ($obj, $container) {
            return $obj;
        });

        // Then the rebinding callback was not called.
        $this->assertFalse($testRebound);
    }

    public function testGlobalExtensionWorksOnAliasedBindings()
    {
        // Given a singleton with an alias.
        $container = new Container;
        $container->singleton('something', function () {
            return 'some value';
        });
        $container->alias('something', 'something-alias');

        // When we register a global extender.
        $container->extend(function ($value) {
            return $value.' extended';
        });

        // Then both the singleton and its alias appear to be extended.
        $this->assertSame('some value extended', $container->make('something'));
        $this->assertSame('some value extended', $container->make('something-alias'));
    }

    public function testMultipleGlobalExtenders()
    {
        // Given a simple binding.
        $container = new Container;
        $container['foo'] = 'foo';

        // When we register two global extenders.
        $container->extend(function ($old, $container) {
            return $old.'bar';
        });
        $container->extend(function ($old, $container) {
            return $old.'baz';
        });

        // Then the binding has been extended by both global extenders.
        $this->assertSame('foobarbaz', $container->make('foo'));
    }

    public function testForgetGlobalExtenders()
    {
        // Given a simple binding.
        $container = new Container;
        $container->bind('foo', function () {
            return 'foo';
        });

        // And a global extender that appends "bar" to all bindings.
        $container->extend(function ($obj, $container) {
            return $obj.'bar';
        });

        // When we forget all global extenders.
        $container->forgetGlobalExtenders();

        // Then the global extender is not applied when we next resolve that binding.
        $this->assertSame('foo', $container->make('foo'));
    }
}

class ContainerLazyExtendStub
{
    public static $initialized = false;

    public function init()
    {
        static::$initialized = true;
    }
}
