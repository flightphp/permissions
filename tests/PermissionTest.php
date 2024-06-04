<?php

namespace flight\tests;

use Exception;
use flight\Permission;
use Wruczek\PhpFileCache\PhpFileCache;

class PermissionTest extends \PHPUnit\Framework\TestCase
{
    public function tearDown(): void
    {
        // Remove any cache files.
        foreach (glob(__DIR__ . '/*.cache.php') as $file) {
            unlink($file);
        }
    }

    public function testSetCurrentRole()
    {
        $permission = new Permission('admin');
        $permission->setCurrentRole('user');
        $this->assertSame('user', $permission->getCurrentRole());
    }

    public function testDefineRule()
    {
        $permission = new Permission('admin');
        $permission->defineRule('createOrder', 'Some_Permissions_Class->createOrder');
        $this->assertSame([ 'createOrder' => 'Some_Permissions_Class->createOrder' ], $permission->getRules());
    }

    public function testDefineDuplicateRule()
    {
        $permission = new Permission('admin');
        $permission->defineRule('createOrder', 'Some_Permissions_Class->createOrder');
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Rule already defined: createOrder');
        $permission->defineRule('createOrder', 'Some_Permissions_Class->createOrder');
    }

    public function testDefineRulesFromClassMethodsNoCacheSimpleBoolean()
    {
        $permission = new Permission('admin');
        $permission->defineRulesFromClassMethods(FakePermissionsClass::class);
        $this->assertTrue($permission->can('createOrder'));
    }

    public function testDefineRulesFromClassMethodsNoCacheArray()
    {
        $permission = new Permission('public');
        $permission->defineRulesFromClassMethods(FakePermissionsClass::class);
        $this->assertTrue($permission->can('order.create', 1, 1));
        $this->assertTrue($permission->can('order.delete', 1, 1));
        $this->assertFalse($permission->can('order.update', 1, 1));
        $this->assertTrue($permission->can('order.update', 1, 11));
        $this->assertFalse($permission->can('order.delete', 0, 11));
        $this->assertFalse($permission->can('order.admin', 0, 1));
        $permission->setCurrentRole('admin');
        $this->assertTrue($permission->has('order.admin', 1, 11));
    }

    public function testDefineRulesFromClassMethodsWithCache()
    {
        $PhpFileCache = new PhpFileCache(__DIR__, 'my_test');
        $permission = new Permission('public', null, $PhpFileCache);
        $permission->defineRulesFromClassMethods(FakePermissionsClass::class, 60);
        $this->assertTrue($permission->can('order.create', 1, 1));
        $this->assertTrue($permission->can('order.delete', 1, 1));
        $this->assertFalse($permission->can('order.update', 1, 1));
        $this->assertTrue($permission->can('order.update', 1, 11));
        $this->assertFalse($permission->can('order.delete', 0, 11));
        $this->assertFalse($permission->can('order.admin', 0, 1));
        $permission->setCurrentRole('admin');
        $this->assertTrue($permission->has('order.admin', 1, 11));
    }

    public function testDefineRulesFromClassMethodsWithCacheTouchCache()
    {
        $PhpFileCache = new PhpFileCache(__DIR__, 'my_test');
        $permission = new Permission('public', null, $PhpFileCache);
        $permission->defineRulesFromClassMethods(FakePermissionsClass::class, 60);
        // Make sure it works
        $this->assertTrue($permission->can('order.create', 1, 1));

        // Then load the classes again like from another request and
        // make sure it pulls from the cache
        $permission->defineRulesFromClassMethods(FakePermissionsClass::class, 60);
        $this->assertFalse($permission->can('order.delete', 0, 11));
    }

    public function testCanNoPermission()
    {
        $permission = new Permission('admin');
        $this->expectExceptionMessage('Permission not defined: smell');
        $permission->can('smell');
    }

    public function testCanWithCallable()
    {
        $permission = new Permission('admin');
        $permission->defineRule('canSmell', function ($currentRole) {
            return $currentRole === 'admin';
        });
        $this->assertTrue($permission->can('canSmell'));
    }

    public function testIs()
    {
        $permission = new Permission('admin');
        $this->assertTrue($permission->is('admin'));
        $this->assertFalse($permission->is('user'));
    }
}
