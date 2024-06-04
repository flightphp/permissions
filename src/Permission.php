<?php

declare(strict_types=1);

namespace flight;

use Exception;
use Flight;
use flight\Engine;
use Wruczek\PhpFileCache\PhpFileCache;

class Permission
{
    /** @var array */
    protected $rules = [];

    /** @var Engine */
    protected $app;

    /** @var string */
    protected $currentRole;

    /** @var object */
    protected $Cache;

    /** @var array<int, object> */
    protected $localClassCache = [];

    /**
     * Constructor
     *
     * @param string $currentRole
     * @param Engine $f3
     */
    public function __construct(string $currentRole = '', Engine $app = null, $Cache = null)
    {
        $this->currentRole = $currentRole;
        $this->app = $app === null ? Flight::app() : $app;
        $this->Cache = $Cache;
    }

    /**
     * Sets the current user role
     *
     * @param string $currentRole the current role of the logged in user
     * @return void
     */
    public function setCurrentRole(string $currentRole)
    {
        $this->currentRole = $currentRole;
    }

    /**
     * Gets the current user role
     *
     * @return string
     */
    public function getCurrentRole(): string
    {
        return $this->currentRole;
    }

    /**
     * Gets the defined rules
     *
     * @return array
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Defines a new rule for a permission
     *
     * @param string          $rule     the generic name of the rule
     * @param callable|string $callable either a callable, or the name of the Class->method to call that
     *      will return the allowed permissions.
     *      This will return an array of strings, each string being a permission
     *      e.g. return true; would allow access to this permission
     *           return [ 'create', 'read', 'update', 'delete' ] would allow all permissions
     *           return [ 'read' ] would only allow the read permission.
     *           You define what the callable or method returns for allowed permissions
     *      The callable or method will be passed the following parameters:
     *          - $f3: the \Base instance
     *          - $currentRole: the current role of the logged in user
     * @param bool            $overwrite if true, will overwrite any existing rule with the same name
     * @return void
     */
    public function defineRule(string $rule, $callableOrClassString, bool $overwrite = false)
    {
        if ($overwrite === false && isset($this->rules[$rule]) === true) {
            throw new \Exception('Rule already defined: ' . $rule);
        }
        $this->rules[$rule] = $callableOrClassString;
    }

    /**
     * Defines rules based on the public methods of a class
     *
     * @param string $className the name of the class to define rules from
     * @return void
     */
    public function defineRulesFromClassMethods(string $className, int $ttl = 0): void
    {

        $useCache = false;
        if ($this->Cache !== null && $ttl > 0) {
            $useCache = true;
            $Cache = $this->Cache;
            $cacheKey = 'flight_permissions_class_methods_' . $className;
            if (is_a($Cache, PhpFileCache::class) === true) {
                /** @var PhpFileCache $Cache */
                $isCached = $Cache->isCached($cacheKey);
                if ($isCached === true) {
                    $this->rules = $Cache->retrieve($cacheKey);
                    return;
                }
            }
        }

        $reflection = new \ReflectionClass($className);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        $classRules = [];
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (strpos($methodName, '__') === 0) {
                continue;
            }
            $classRules[$methodName] = $className . '->' . $methodName;
        }

        if ($useCache === true) {
            if (is_a($Cache, PhpFileCache::class) === true) {
                /** @var PhpFileCache $Cache */
                $Cache->store($cacheKey, $classRules, $ttl);
            }
        }

        $this->rules = array_merge($this->rules, $classRules);
    }

    /**
     * Checks if the current user has permission to perform the action
     *
     * @param string $permission the permission to check. This can be the rule you defined, or a permission.action
     *      e.g. 'video.create' or 'video' depending on how you setup the callback.
     * @param mixed $additionalArgs any additional arguments to pass to the callback or method.
     * @return bool
     */
    public function can(string $permission, ...$additionalArgs): bool
    {
        $allowed = false;
        $action = '';
        if (strpos($permission, '.') !== false) {
            [ $permission, $action ] = explode('.', $permission);
        }

        $permissionsRaw = $this->rules[$permission] ?? null;
        if ($permissionsRaw === null) {
            throw new Exception('Permission not defined: ' . $permission);
        }

        $executedPermissions = null;

        if (is_callable($permissionsRaw) === true) {
            $executedPermissions = $permissionsRaw($this->currentRole, ...$additionalArgs);
        } else {
            if (is_string($permissionsRaw) === true) {
                $permissionsRaw = explode('->', $permissionsRaw);
            }
            [ $className, $methodName ] = $permissionsRaw;
            if (isset($this->localClassCache[$className]) === false) {
                $class = new $className($this->app);
                $this->localClassCache[$className] = $class;
            } else {
                $class = $this->localClassCache[$className];
            }
            $executedPermissions = $class->$methodName($this->currentRole, ...$additionalArgs);
        }

        if (is_array($executedPermissions) === true) {
            $allowed = in_array($action, $executedPermissions, true) === true;
        } elseif (is_bool($executedPermissions) === true) {
            $allowed = $executedPermissions;
        }

        return $allowed;
    }

    /**
     * Alias for can. Sometimes it's nice to say has instead of can
     *
     * @param string $permission Permission to check
     * @param mixed $additionalArgs any additional arguments to pass to the callback or method.
     * @return boolean
     */
    public function has(string $permission, ...$additionalArgs): bool
    {
        return $this->can($permission, ...$additionalArgs);
    }

    /**
     * Checks if the current user has the given role
     *
     * @param string $role [description]
     * @return boolean
     */
    public function is(string $role): bool
    {
        return $this->currentRole === $role;
    }
}
