<?php

declare(strict_types=1);

namespace flight\tests;

use flight\Engine;

class FakePermissionsClass
{
    /** @var Engine */
    protected $app;

    public function __construct(Engine $app = null)
    {
        $this->app = $app;
    }

    public function createOrder(): bool
    {
        return true;
    }

    public function order($currentRole, $id, $quantity): array
    {
        $permissions = [];
        if ($this->createOrder()) {
            $permissions[] = 'create';
        }

        if ($quantity > 10) {
            $permissions[] = 'update';
        }

        if ($id > 0) {
            $permissions[] = 'delete';
        }

        if ($currentRole === 'admin') {
            $permissions[] = 'admin';
        }

        return $permissions;
    }
}
