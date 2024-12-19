<?php

namespace Flame\Core\Loader\Model;

use Flame\Core\Module\Module;
use Flame\Core\Path\Paths;
use Flame\Core\Dependency\DependencyResolver;

class Model
{
    private array $flameClasses = [];
    private array $modelPaths;
    private array $varMap = [];
    protected $resolve;

    public function __construct(Paths $paths, DependencyResolver $resolve)
    {
        $this->modelPaths = $paths::$modelPaths;
        $this->resolve = $resolve;
    }

    public function make($model, ?array $params = null, ?string $objectName = null): self
    {
        if (is_array($model)) {
            return $this->loadModels($model);
        }


        if (strrpos($model, '\\')) {
            return $this->makeByNamespace($model, $params, $objectName);
        }


        //$objectName = strtolower($objectName ?? $class);
        $class = strtolower(basename($model));
        $objectName = ($objectName ? $objectName : $class);

        if (flame()->has($objectName)) {
            return $this;
        }

        [$path, $_model] = Module::find($model, 'models/');
        $params = $this->loadModelConfig($params, $objectName);

        if ($path === false) {
            $this->loadModel($model, $params, $objectName);
        } else {
            $this->initializeModel($path, $_model, $class, $objectName, $params);
        }

        return $this;
    }

    private function makeByNamespace(string $model, ?array $params = null, ?string $objectName = null): self
    {
        $alias = $objectName ?? strtolower(basename(str_replace('\\', '/', $model)));

        if (flame()->has($alias)) {
            return $this;
        }

        //$instance = $params ? new $model($params) : new $model;
        //flame()->set($alias, $instance);

        $this->initializeInstance($model, $params, $alias);

        return $this;
    }

    private function loadModels(array $libraries): self
    {
        foreach ($libraries as $model => $alias) {
            is_int($model) ? $this->make($alias) : $this->make($model, null, $alias);
        }

        return $this;
    }

    private function loadModelConfig(?array $params, string $alias): ?array
    {
        if ($params !== null) {
            return $params;
        }

        [$path, $file] = Module::find($alias, 'config/', Module::$getModule);
        return $path ? Module::load_file($file, $path, 'config') : null;
    }

    private function initializeModel(
        string $path,
        string $_model,
        string $class,
        string $objectName,
        ?array $params
    ): void {
        Module::load_file($_model, $path);

        $className = $this->buildClassName($_model);
        $namespace = flame('setup')->get('App:namespace') . '\\' . str_replace(
            ['.', '/'],
            ['', '\\'],
            str_replace(APPPATH, '', $path)
        );

        if (class_exists($namespace . $className)) {
            $className = $namespace . $className;
        }

        $this->flameClasses[$class] = $objectName;

        $this->initializeInstance($className, $params, $objectName);

        /*$instance = $params ? new $className($params) : new $className;
        if (!flame()->has($objectName)) {
            flame()->set($objectName, $instance);
        }*/
    }

    private function loadModel(string $class, ?array $params = null, ?string $objectName = null): void
    {
        $className = $this->buildClassName($class);
        $subdir = $this->extractSubdir($class);

        if (!$this->loadFromBasePath($className, $subdir, $params, $objectName) &&
            !$this->loadFromModelPaths($className, $subdir, $params, $objectName) &&
            empty($subdir)
        ) {
            $this->loadModel("$class/$class", $params, $objectName);
        }

    }

    private function extractSubdir(string &$class): string
    {
        $lastSlash = strrpos($class, '/');
        if ($lastSlash !== false) {
            $subdir = substr($class, 0, $lastSlash + 1);
            $class = substr($class, $lastSlash + 1);
            return $subdir;
        }

        return '';
    }

    private function loadFromBasePath(
        string $class,
        string $subdir,
        ?array $params,
        ?string $objectName
    ): bool {
        if (flame()->has($objectName)) {
            return true;
        }

        $className = $this->resolveClassName('Models', $class);
        if (class_exists($className)) {
            return $this->initializeInstance($className, $params, $objectName);
        }

        return false;
    }

    private function loadFromModelPaths(
        string $class,
        string $subdir,
        ?array $params,
        ?string $objectName
    ): bool {
        foreach ($this->modelPaths as $path) {
            if ($path === BASEPATH) {
                continue;
            }

            $class = basename(str_replace('\\','/',$class));
            $filePath = resolve_path(resolve_path($path, 'models'), str_replace('\\','/',$subdir)) . "$class.php";
            if (file_exists($filePath)) {
                include_once $filePath;
                return $this->initializeInstance($class, $params, $objectName);
            }
        }

        return false;
    }

    private function initializeInstance(string $class, ?array $params, ?string $objectName): bool
    {
        if (flame()->has($objectName)) {
            return true;
        }

        if($params) {
          $instance = $this->resolve->resolve($class, $params, false);
        } else {
          $instance = $this->resolve->resolve($class, [], false);
        }
        flame()->set($objectName, $instance);

        return true;
    }

    private function resolveClassName(string $subNamespace, string $class): string
    {
        foreach (flame('App')->getNamespaces() as $namespace) {
            $fullName = $namespace . '\\' . $subNamespace . '\\' . $class;

            if (class_exists($fullName)) {
                return $fullName;
            }
        }

        return $class;
    }

    private function buildClassName(string $name): string
    {
        $parts = array_map('ucfirst', explode('_', str_replace(['-', '.php'], ['_', ''], $name)));
        $parts = array_map('ucfirst', explode('/', implode('', $parts)));
        return implode('\\', $parts);
    }
}
