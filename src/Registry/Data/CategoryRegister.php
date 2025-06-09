<?php

declare(strict_types=1);

namespace Upmind\ProvisionBase\Registry\Data;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use ReflectionMethod;
use Upmind\ProvisionBase\Provider\Contract\CategoryInterface;
use Upmind\ProvisionBase\Provider\DataSet\AboutData;

/**
 * Register for a provision category.
 */
class CategoryRegister extends ClassRegister
{
    /**
     * About data.
     *
     * @var AboutData|null
     */
    protected $about;

    /**
     * @var Collection<FunctionRegister>|FunctionRegister[]
     */
    protected $functions;

    /**
     * @inheritDoc
     */
    public function checkClass(): void
    {
        if (!$this->getReflection()->isAbstract()) {
            throw new InvalidArgumentException(
                sprintf('%s must be an abstract class', $this->getClass())
            );
        }
    }

    /***
     * Enumerate category about data, functions, providers and their respective
     * data.
     */
    public function enumerateData(): void
    {
        $this->getAbout()->validateIfNotYetValidated();

        $this->getFunctions()->each(function (FunctionRegister $register) {
            $register->enumerateData();
        });

        $this->getProviders()->each(function (ProviderRegister $register) {
            $register->enumerateData();
        });
    }

    public function getAbout(): AboutData
    {
        if (!isset($this->about)) {
            $this->about = $this->class::aboutCategory();
        }

        return $this->about;
    }

    /**
     * Return the child provider registers.
     *
     * @return Collection<ProviderRegister>|ProviderRegister[]
     */
    public function getProviders(): Collection
    {
        return $this->getChildren();
    }

    /**
     * Return a provider by identifier, class or register instance.
     *
     * @param string|ProviderRegister $provider Provider identifier, class or register instance
     */
    public function getProvider($provider): ?ProviderRegister
    {
        return $this->getChild($provider);
    }

    /**
     * Add a provider of this category.
     *
     * @param string $identifier Provider unique identifier
     * @param string $class Provider fully-namespaced class name
     *
     * @return self $this
     */
    public function addProvider(string $identifier, string $class): ProviderRegister
    {
        $this->addChild($provider = new ProviderRegister($identifier, $class, $this));

        return $provider;
    }

    /**
     * Determine whether this category has the given provider.
     *
     * @param string|ProviderRegister $provider Provider identifier, class or register instance
     */
    public function hasProvider($provider): bool
    {
        return $this->hasChild($provider);
    }

    /**
     * Get this category's provision functions.
     *
     * @return Collection<FunctionRegister>|FunctionRegister[]
     */
    public function getFunctions(): Collection
    {
        if (!isset($this->functions)) {
            $this->functions = Collection::make($this->getAbstractMethods())
                ->map(function (ReflectionMethod $method) {
                    return new FunctionRegister(
                        $method->getName(),
                        $this->getMethodParameterDataSetClass($method),
                        $this->getMethodReturnDataSetClass($method),
                        $this
                    );
                });
        }

        return $this->functions;
    }

    /**
     * Get the given function.
     *
     * @param string|FunctionRegister $function Function name or register instance
     */
    public function getFunction($function): ?FunctionRegister
    {
        return $this->getFunctions()
            ->first(function (FunctionRegister $register) use ($function) {
                return $register->is($function);
            });
    }

    /**
     * Determine whether this category has the given function.
     *
     * @param string|FunctionRegister $function Function name or register instance
     */
    public function hasFunction($function): bool
    {
        return $this->getFunctions()
            ->contains(function (FunctionRegister $register) use ($function) {
                return $register->is($function);
            });
    }

    /**
     * @inheritDoc
     */
    protected function getInterface(): string
    {
        return CategoryInterface::class;
    }

    /**
     * @inheritDoc
     */
    protected function checkParent(ClassRegister $parent): void
    {
        throw new InvalidArgumentException(
            sprintf('%s %s cannot have a parent register', class_basename($this), $this->getIdentifier())
        );
    }

    /**
     * @inheritDoc
     */
    protected function checkChild(ClassRegister $child): void
    {
        if (!$child instanceof ProviderRegister) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s %s children must be of type %s',
                    class_basename($this),
                    $this->getIdentifier(),
                    class_basename(ProviderRegister::class)
                )
            );
        }

        parent::checkChild($child);
    }

    /**
     * @return ReflectionMethod[]
     */
    protected function getAbstractMethods(): array
    {
        return $this->getReflection()->getMethods(ReflectionMethod::IS_ABSTRACT);
    }

    public function __debugInfo()
    {
        return array_merge(parent::__debugInfo(), [
            'providers' => $this->children ? $this->children->all() : null,
            'functions' => $this->functions ? $this->functions->all() : null,
        ]);
    }
}
