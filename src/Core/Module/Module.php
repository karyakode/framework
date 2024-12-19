<?php namespace Flame\Core\Module;

use Flame\Core\Router\Router;

class Module implements ModuleInterface
{
    public static $routes;
    public static $registry;
    public static $locations;
    public static $getModule;
    protected static $module_paths =	array(APPPATH);
    protected static $router;

    function __construct()
    {
      self::$router = new Router();
      // set the module name
      self::$getModule = self::$router->fetch_module();
      // add this module path to the loader variables
      self::addPaths(self::$getModule);
    }

    protected static function addPaths($module = ''){

  		if (empty($module)) {
  				return;
  		}

  		foreach (self::getLocations() as $location => $offset) {
  				// only add a module path if it exists
  				if (is_dir($module_path = $location.$module.'/') && ! in_array($module_path, self::$module_paths)) {
  						array_unshift(self::$module_paths, $module_path);
  				}
  		}
  	}

  	public static function module($module, $params = null)
  	{
  			if (is_array($module)) {
  					return self::modules($module);
  			}
  			$_alias = strtolower(basename($module));

  			flame()->set($_alias, self::load([$module => $params]));

  	}

  	public static function modules($modules)
  	{
  			foreach ($modules as $_module) {
  					self::module($_module);
  			}
  			return $modules;
  	}

    public static function run($module)
    {
        $method = 'index';

        if (($pos = strrpos($module, '/')) !== false) {
            $method = substr($module, $pos + 1);
            $module = substr($module, 0, $pos);
        }

        if ($class = self::load($module)) {
            if (method_exists($class, $method)) {
                ob_start();
                $args = func_get_args();
                $output = call_user_func_array([$class, $method], array_slice($args, 1));
                $buffer = ob_get_clean();
                return $output ?? $buffer;
            }
        }

        log_message('error', "Module controller failed to run: {$module}/{$method}");
    }

    public static function load($module = '')
    {
        $module or $module = self::$getModule;

        if (version_compare(phpversion(), '7.1', '<')) {
            // php version isn't high enough
            is_array($module) ? list($module, $params) = each($module) : $params = null;
        } else {
            if (!is_array($module)) {
                $params = null;
            } else {
                $keys = array_keys($module);

                $params = $module[$keys[0]];

                $module = $keys[0];
            }
        }

        // get the requested controller class name
        $alias = strtolower(basename($module));

        // create or return an existing controller from the registry
        if (!isset(self::$registry[$alias])) {
            // Backward function
            // Before PHP 7.1.0, list() only worked on numerical arrays and assumes the numerical indices start at 0.
            if (version_compare(phpversion(), '7.1', '<')) {
                // php version isn't high enough
                // find the controller
                list($class) = self::$router->locate(explode('/', $module));
            } else {
                [$class] = self::$router->locate(explode('/', $module));
            }

            // controller cannot be located
            if (empty($class)) {
                return;
            }

            // set the module directory
            $path = resolve_path(APPPATH,'controllers').'/'.self::$router->directory;
            $path_system = resolve_path(BASEPATH,'controllers').'/'.self::$router->directory;
            if(file_exists($path)) {
              // load the controller class
              $class .= flame()->config->item('controller_suffix');
              self::load_file(ucfirst($class), $path);
              // create and register the new controller
              $controller = ucfirst($class);

              $namespaces = flame('setup')->get('App:namespace').str_replace(['.','/'],['','\\'],self::$router->directory);

                if(class_exists($namespaces.ucfirst($class))) {
                  $controller = $namespaces.ucfirst($class);
                }
            } else {
              self::load_file(ucfirst($class), $path_system);
              // create and register the new controller
              $controller = ucfirst($class);
              $namespaces = flame('setup')->get('namespace').str_replace(['.','/'],['','\\'],self::$router->directory);

                if(class_exists($namespaces.ucfirst($class))) {
                  //$controller = $namespaces.ucfirst($class);
                }
            }


            self::$registry[$alias] = new $controller($params);
        }

        return self::$registry[$alias];
    }

    public static function load_file($file, $path, $type = 'other', $result = true)
    {

      $file = str_replace('.php', '', $file);
      $location = $path.$file.'.php';


        if ($type === 'other') {

            if (class_exists($file, false)) {
                log_message('debug', "File already loaded: {$location}");
                return $result;
            }
            include_once $location;
        } else {

            // load config or language array
            include $location;

            if (! isset($$type) || ! is_array($$type)) {
                show_error("{$location} does not contain a valid {$type} array");
            }

            $result = $$type;
        }
        log_message('debug', "File loaded: {$location}");
        return $result;
    }

    public static function find($file, $base, $module = '')
    {

        $module or $module = self::$getModule;

        $segments = explode('/', $file);

        $file = array_pop($segments);
        $file_ext = pathinfo($file, PATHINFO_EXTENSION) ? $file : $file.'.php';

        $path = ltrim(implode('/', $segments).'/', '/');
        $module ? $modules[$module] = $path : $modules = [];

        if (! empty($segments)) {
            $modules[array_shift($segments)] = ltrim(implode('/', $segments).'/', '/');
        }

        foreach (self::getLocations() as $location => $offset) {
            foreach ($modules as $module => $subpath) {
                $location_module = resolve_path($location, $module);
                $location_base = resolve_path($location_module, $base);
                $fullpath = $location_base.$subpath;

                if (strtolower(trim($base,'/')) === 'libraries' || strtolower(trim($base,'/')) === 'models') {
                    if (is_file($fullpath.ucfirst($file_ext))) {
                        return [$fullpath, ucfirst($file)];
                    }
                } elseif // load non-class files
                (is_file($fullpath.$file_ext)) {
                    return [$fullpath, $file];
                }
            }
        }

        return [false, $file];
    }

    public static function parse_routes($module, $uri)
    {
        $module = trim($module,'/');
        // load the route file
        if (! isset(self::$routes[$module])) {
            if (version_compare(phpversion(), '7.1', '<')) {
                // php version isn't high enough
                if (list($path) = self::find('routes', 'config/', $module)) {
                    $path && self::$routes[$module] = self::load_file('routes', $path, 'route');
                }

            } else {
                if ([$path] = self::find('routes', 'config/', $module)) {
                    $path && self::$routes[$module] = self::load_file('routes', $path, 'route');
                }

            }
        }

        if (! isset(self::$routes[$module])) {
            return;
        }

        // Add http verb support for each module routing
        $http_verb = isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']) : 'cli';

        // parse module routes
        foreach (self::$routes[$module] as $key => $val) {
            // Add http verb support for each module routing
            if (is_array($val)) {
                $val = array_change_key_case($val, CASE_LOWER);

                if (isset($val[$http_verb])) {
                    $val = $val[$http_verb];
                } else {
                    continue;
                }
            }

            $key = str_replace([':any', ':num'], ['.+', '[0-9]+'], $key);

            if (preg_match('#^'.$key.'$#', $uri)) {

                if (strpos($val, '$') !== false && strpos($key, '(') !== false) {
                    $val = preg_replace('#^'.$key.'$#', $val, $uri);
                }

                return explode('/', $module.'/'.$val);
            }
        }
    }

    public static function getLocations(){
      self::$locations = array_merge([BASEPATH.'Modules/' => '../Modules/'],self::$locations);
      return self::$locations;
    }

    public static function setLocations($locations = []){
      self::$locations = $locations;
    }

}


// get module locations from config settings or use the default module location and offset
is_array(Module::setLocations(config_item('modules_locations'))) or Module::setLocations([
  APPPATH.'Modules/' => '../Modules/',
]);
