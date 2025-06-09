<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Registry\Data;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use Upmind\ProvisionBase\Provider\DataSet\AboutData;
use Upmind\ProvisionBase\Provider\DataSet\DataSet;
use Upmind\ProvisionBase\Registry\Data\Traits\IssuesWarnings;

abstract class ClassRegister implements RegisterInterface
{
    use IssuesWarnings;

    /**
     * Pattern which identifiers must follow.
     *
     * @var string
     */
    public const IDENTIFIER_REGEX = '/^[a-z0-9]+[a-z0-9\-]*[a-z0-9]+$/';

    /**
     * Unique identifier for this category/provider.
     *
     * @var string
     */
    protected $identifier;

    /**
     * Fully-namespaced class name for this category/provider.
     *
     * @var string
     */
    protected $class;

    /**
     * Parent register, if any.
     *
     * @var ClassRegister|null
     */
    protected $parent;

    /**
     * Child registers, if any.
     *
     * @var Collection<ClassRegister>|ClassRegister[]|null
     */
    protected $children;

    /**
     * Reflection of this register's class.
     *
     * @var ReflectionClass|null
     */
    protected $reflection;

    /**
     * @param string $identifier Unique identifier
     * @param string $class Fully-namespaced class name
     *
     * @see `self::IDENTIFIER_REGEX`
     */
    public function __construct(string $identifier, string $class, ?ClassRegister $parent = null)
    {
        $this->identifier = $identifier;
        $this->class = $class;
        $this->parent = $parent;

        $this->checkRegister();
        $this->checkClass();
    }

    /**
     * Return the identifier of this register.
     */
    final public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Return the class of this register.
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Return the parent of this register, if any.
     */
    final public function getParent(): ?ClassRegister
    {
        return $this->parent;
    }

    /**
     * Set the parent of this register.
     */
    final public function setParent(ClassRegister $parent): void
    {
        if (!$this->hasParent($parent)) {
            $this->checkParent($parent);
            $this->parent = $parent;
        }

        if (!$parent->hasChild($this)) {
            $parent->addChild($this);
        }
    }

    /**
     * Determine whether this register has a parent.
     *
     * @param ClassRegister|null $parent Optionally check whether register has this specific parent
     */
    final public function hasParent(?ClassRegister $parent = null): bool
    {
        return isset($this->parent) && (!$parent || $this->parent === $parent);
    }

    /**
     * @return Collection<ClassRegister>|ClassRegister[]
     */
    final public function getChildren(): Collection
    {
        if (!isset($this->children)) {
            $this->children = new Collection();
        }

        return $this->children;
    }

    /**
     * Get the given child register.
     *
     * @param string|ClassRegister $child Register instance, identifier or class
     */
    final public function getChild($child): ?ClassRegister
    {
        return $this->getChildren()
            ->first(function (ClassRegister $register) use ($child) {
                return $register->is($child);
            });
    }

    /**
     * Add a child register.
     */
    final public function addChild(ClassRegister $child): void
    {
        $this->checkChild($child);

        $this->getChildren()->push($child);

        if (!$child->hasParent($this)) {
            $child->setParent($this);
        }
    }

    /**
     * Check whether this register contains the given child.
     *
     * @param string|ClassRegister $child Register instance, identifier or class
     */
    final public function hasChild($child): bool
    {
        return $this->getChildren()
            ->contains(function (ClassRegister $register) use ($child) {
                return $register->is($child);
            });
    }

    /**
     * Return the about data of this register.
     */
    abstract public function getAbout(): AboutData;

    /**
     * Determine whether the given register, identifier or class identifies this register.
     *
     * @param ClassRegister|string $register Register instance, identifier or class
     */
    public function is($register): bool
    {
        if (is_string($register)) {
            return $register === $this->getIdentifier()
                || $register === $this->getClass();
        }

        return $register === $this;
    }

    /**
     * Thoroughly check the validity of this register's class.
     */
    abstract public function checkClass(): void;

    /**
     * Check this register's identifier matches the correct pattern and that the
     * register's class exists and implements to correct interface.
     */
    protected function checkRegister(): void
    {
        if (!preg_match(static::IDENTIFIER_REGEX, $this->getIdentifier())) {
            throw new InvalidArgumentException(
                sprintf('%s identifier must match pattern \'%s\'', class_basename($this), static::IDENTIFIER_REGEX)
            );
        }

        if (!class_exists($this->getClass())) {
            throw new InvalidArgumentException(
                sprintf('%s %s class not found', class_basename($this), $this->getIdentifier())
            );
        }

        if (!in_array($this->getInterface(), class_implements($this->getClass()))) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s %s class must implement %s',
                    class_basename($this),
                    $this->getIdentifier(),
                    $this->getInterface()
                )
            );
        }

        if ($this->hasParent()) {
            $this->checkParent($this->parent);
        }
    }

    /**
     * Check the given parent register.
     */
    protected function checkParent(ClassRegister $parent): void
    {
        if (get_class($parent) === get_class($this)) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s %s must not have a parent register of the same type',
                    class_basename($this),
                    $this->getIdentifier()
                )
            );
        }

        if ($parent->hasParent()) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s %s parent %s %s must not also have a parent',
                    class_basename($this),
                    $this->getIdentifier(),
                    class_basename($parent),
                    $parent->getIdentifier()
                )
            );
        }

        if (!is_subclass_of($this->getClass(), $parent->getClass())) {
            throw new InvalidArgumentException(
                sprintf('%s must be a subclass of %s', $this->getClass(), $parent->getClass())
            );
        }
    }

    /**
     * Check a child register.
     */
    protected function checkChild(ClassRegister $child): void
    {
        if (!is_subclass_of($child->getClass(), $this->getClass())) {
            throw new InvalidArgumentException(
                sprintf('%s must be a subclass of %s', $child->getClass(), $this->getClass())
            );
        }

        foreach ([$child, $child->getIdentifier(), $child->getClass()] as $identifier) {
            if ($this->hasChild($identifier)) {
                throw new InvalidArgumentException(
                    sprintf(
                        '%s %s already has a child %s %s',
                        class_basename($this),
                        $this->getIdentifier(),
                        class_basename($child),
                        $child->getIdentifier()
                    )
                );
            }
        }
    }

    /**
     * Obtain the interface this register's class must implement.
     */
    abstract protected function getInterface(): string;

    /**
     * Return reflection instance of this register's class.
     */
    protected function getReflection(): ReflectionClass
    {
        if (!isset($this->reflection)) {
            $this->reflection = new ReflectionClass($this->getClass());
        }

        return $this->reflection;
    }

    /**
     * Returns the DataSet class name of the type of the first parameter of the
     * given reflection method, if any.
     *
     * @return string|null DataSet class name if any
     */
    protected function getMethodParameterDataSetClass(ReflectionMethod $method): ?string
    {
        if (!$reflectionParam = Arr::first($method->getParameters())) {
            return null;
        }

        /** @var \ReflectionParameter $reflectionParam */
        if (!$reflectionType = $reflectionParam->getType()) {
            $this->warning(sprintf(
                '%s function %s parameter type could not be determined',
                $this->getIdentifier(),
                $method->getName()
            ));

            return null;
        }

        return $this->getReflectionTypeDataSetClass($method, $reflectionType, 'parameter');
    }

    /**
     * Returns the DataSet class name of the return type of the given reflection
     * method, if any.
     *
     * @return string|null DataSet class name if any
     */
    protected function getMethodReturnDataSetClass(ReflectionMethod $method): ?string
    {
        return $method->hasReturnType()
            ? $this->getReflectionTypeDataSetClass($method, $method->getReturnType(), 'return')
            : null;
    }

    /**
     * @param ReflectionMethod $method
     * @param ReflectionType $reflectionType
     * @param string $type One of: parameter or return
     *
     * @return string|null DataSet class or null
     */
    protected function getReflectionTypeDataSetClass(
        ReflectionMethod $method,
        ReflectionType $reflectionType,
        string $type
    ): ?string {
        $class = $reflectionType instanceof ReflectionNamedType
            ? $reflectionType->getName()
            : (string)$reflectionType;

        if (!class_exists($class)) {
            $this->warning(sprintf(
                '%s function %s() %s type %s class not found',
                $this->getIdentifier(),
                $method->getName(),
                $type,
                class_basename($class)
            ));

            return null;
        }

        if (!is_subclass_of($class, DataSet::class)) {
            $this->warning(sprintf(
                '%s function %s() %s type %s must be a subclass of %s',
                $this->getIdentifier(),
                $method->getName(),
                $type,
                class_basename($class),
                DataSet::class
            ));

            return null;
        }

        return $class;
    }

    public function __toString()
    {
        return $this->identifier;
    }

    /**
     * Unset ReflectionClass - these cannot be serialized after PHP 7.3.
     */
    public function __sleep()
    {
        unset($this->reflection);

        return array_keys(get_object_vars($this));
    }

    public function __debugInfo()
    {
        return [
            'identifier' => $this->identifier,
            'class' => $this->class,
        ];
    }
}
