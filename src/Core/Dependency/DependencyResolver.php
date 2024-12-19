<?php
namespace Flame\Core\Dependency;

use Flame\Core\Contracts\DependencyResolverInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Exception;

class DependencyResolver implements DependencyResolverInterface
{
    /**
     * Array of loaded classes.
     *
     * @var array
     */
    public $classLoaded = [];

    /**
     * Facade instance.
     *
     * @var mixed|null
     */
    protected $facade = null;

    /**
     * Resolves a class with its dependencies.
     *
     * @param string $class
     * @param array $params
     * @param bool $isFacade
     * @return object
     * @throws Exception
     */
    public function resolve(string $class, array $params = [], bool $isFacade = true): object
    {
        if (!class_exists($class)) {
            throw new Exception("Class $class does not exist");
        }

        $reflection = new ReflectionClass($class);

        // If no constructor, create an instance directly
        if (!$reflection->getConstructor()) {
            $instance = empty($params) ? $reflection->newInstance() : $reflection->newInstanceArgs($params);
            $this->setProperty($class, $instance);
            return $instance;
        }

        $dependencies = array_map(
            fn(ReflectionParameter $parameter) => $this->resolveDependency($parameter),
            $reflection->getConstructor()->getParameters()
        );

        $mergedParams = $this->mergeDependenciesWithParams($reflection->getConstructor()->getParameters(), $dependencies, $params);

        $object = $reflection->newInstanceArgs($mergedParams);
        $this->setProperty($class, $object);

        return $object;
    }

    /**
     * Sets the facade instance.
     *
     * @param mixed $facade
     * @return mixed
     */
    public function setFacade($facade)
    {
        if ($this->facade === null) {
            $this->facade = $facade;
        }
        return $this->facade;
    }

    /**
     * Resolves a single dependency.
     *
     * @param ReflectionParameter $parameter
     * @return mixed
     * @throws Exception
     */
    protected function resolveDependency(ReflectionParameter $parameter)
    {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $resolvedDependency = $this->resolve($type->getName());
            $objectName = $this->getObjectName($type->getName());
            unset($this->classLoaded[$objectName]);
            $this->setProperty($type->getName(), $resolvedDependency, $parameter->getName());
            return $resolvedDependency;
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new Exception("Cannot resolve dependency for parameter '{$parameter->getName()}'");
    }

    /**
     * Registers the loaded class and associates it with the facade if applicable.
     *
     * @param string $class
     * @param object $object
     * @param string|null $alias
     * @return void
     */
    protected function setProperty(string $class, object $object, ?string $alias = null): void
    {
        $objectName = $this->getObjectName($class,$alias);
        $this->classLoaded[$objectName] = $object;

        if ($this->facade !== null) {
            $objectName = $objectName === 'loader' ? 'load' : $objectName;
            if (!$this->facade->has($objectName) && !$this->facade->has($alias)) {
                $this->facade->set($objectName, $object);
            }
        }
    }

    protected function getObjectName(string $class, ?string $alias = null){

      $objectName = $alias ?? strtolower(basename(str_replace('\\', '/', $class)));

      return $objectName;
    }

    /**
     * Merges constructor dependencies with user-provided parameters.
     *
     * @param ReflectionParameter[] $parameters
     * @param array $dependencies
     * @param array $params
     * @return array
     */
    protected function mergeDependenciesWithParams(array $parameters, array $dependencies, array $params): array
    {
        $result = [];
        foreach ($parameters as $index => $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $params)) {
                $result[] = $params[$name];
            } elseif (isset($dependencies[$index])) {
                $result[] = $dependencies[$index];
            } else {
                //throw new Exception("Missing parameter: $name");
            }
        }

        return $result;
    }
}
