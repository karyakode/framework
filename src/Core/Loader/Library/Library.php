<?php

namespace Flame\Core\Loader\Library;

use Flame\Core\Module\Module;
use Flame\Core\Path\Paths;
use Flame\Core\Dependency\DependencyResolver;
class Library
{
    private array $flameClasses = [];
    private array $libraryPaths;
    private array $varMap = [
        'unit_test' => 'unit',
        'user_agent' => 'agent',
    ];

    protected $resolve;
    public function __construct(Paths $paths, DependencyResolver $resolve)
    {
        $this->libraryPaths = $paths::$libraryPaths;
        $this->resolve = $resolve;
    }

    public function make($library, ?array $params = null, ?string $objectName = null): self
    {
        if (is_array($library)) {
            return $this->loadLibraries($library);
        }

        if (strrpos($library, '\\')) {
            return $this->makeByNamespace($library, $params, $objectName);
        }

        //$objectName = strtolower($objectName ?? $class);
        $class = strtolower(basename($library));
        $objectName = ($objectName ?? $class);


        if (flame()->has($objectName)) {
            return $this;
        }


        [$path, $_library] = Module::find($library, 'libraries/');
        $params = $this->loadLibraryConfig($params, $objectName);

        if ($path === false) {
            $this->loadLibrary($library, $params, $objectName);
        } else {
            $this->initializeLibrary($path, $_library, $class, $objectName, $params);
        }

        return $this;
    }

    private function makeByNamespace(string $library, ?array $params = null, ?string $objectName = null): self
    {
        $alias = $objectName ?? strtolower(basename(str_replace('\\', '/', $library)));
        if (flame()->has($alias)) {
            return $this;
        }

        //$instance = $params ? new $library($params) : new $library;
        //flame()->set($alias, $instance);
        $this->initializeInstance($library, $params, $alias);

        return $this;
    }

    private function loadLibraries(array $libraries): self
    {
        foreach ($libraries as $library => $alias) {
            is_int($library) ? $this->make($alias) : $this->make($library, null, $alias);
        }

        return $this;
    }

    private function loadLibraryConfig(?array $params, string $alias): ?array
    {
        if ($params !== null) {
            return $params;
        }

        [$path, $file] = Module::find($alias, 'config/', Module::$getModule);
        return $path ? Module::load_file($file, $path, 'config') : null;
    }

    private function initializeLibrary(
        string $path,
        string $_library,
        string $class,
        string $objectName,
        ?array $params
    ): void {
        Module::load_file($_library, $path);

        $className = $this->buildClassName($_library);
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

    private function loadLibrary(string $class, ?array $params = null, ?string $objectName = null): void
    {
        $className = $this->buildClassName($class);
        $subdir = $this->extractSubdir($class);

        if (!$this->loadFromBasePath($className, $subdir, $params, $objectName) &&
            !$this->loadFromLibraryPaths($className, $subdir, $params, $objectName) &&
            empty($subdir)
        ) {
            $this->loadLibrary("$class/$class", $params, $objectName);
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

        $className = $this->resolveClassName('Libraries', $class);

        if (class_exists($className)) {
            return $this->initializeInstance($className, $params, $objectName);
        }

        return false;
    }

    private function loadFromLibraryPaths(
        string $class,
        string $subdir,
        ?array $params,
        ?string $objectName
    ): bool {
        foreach ($this->libraryPaths as $path) {
            if ($path === BASEPATH) {
                continue;
            }

            $class = basename(str_replace('\\','/',$class));
            $filePath = resolve_path(resolve_path($path, 'libraries'), $subdir) . "/$class.php";
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


        //$instance = $params ? new $class($params) : new $class;
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
