<?php
namespace Kodhe\Pulen\Framework\Application;

use Kodhe\Pulen\Framework\Container\InjectionContainer;
use Kodhe\Pulen\Error\FileNotFound;
use Kodhe\Pulen\Legacy\Legacy;
use Kodhe\Pulen\Framework\Application\Http\Request;
use Kodhe\Pulen\Framework\Application\Http\Response;
use Kodhe\Pulen\Framework\Contracts\DependencyResolverInterface;
/**
 * Core Abstract
 */
class Framework {

	/**
	 * @var bool Application done booting?
	 */
	protected $booted = FALSE;

	/**
	 * @var bool Application started?
	 */
	protected $running = FALSE;

	protected Legacy $legacy;

	protected DependencyResolverInterface $resolve;

	function __construct(DependencyResolverInterface $resolve){

		$this->resolve = $resolve;
	}
	/**
	 * Boot the application
	 */
	public function boot()
	{
		// Memanggil hook pre_system
		$this->callHook('pre_system');
		$this->setTimeLimit(300);
		$this->bootLegacyApplicationCore();
		$this->booted = TRUE;

		register_shutdown_function([$this,'shutdown']);

	}

	/**
	 * We have a separate object for the old CI way of doing things.
	 * Currently this class mostly delegates to that.
	 */
	public function getLegacyApp()
	{
		if ( ! $this->booted)
		{
			show_error('Cannot retrieve legacy app before booting.');
		}

		return $this->legacy;
	}

	/**
	 * Override config before running
	 */
	public function overrideConfig(array $config)
	{
		if ( ! $this->booted || $this->running)
		{
			show_error('Config overrides must happen after booting and before running the application.');
		}

		$this->legacy->overrideConfig($config);
	}

	/**
	 * Override routing before running
	 */
	public function overrideRouting(array $routing)
	{
		if ( ! $this->booted || $this->running)
		{
			show_error('Routing overrides must happen after booting and before running the application.');
		}

		$this->legacy->overrideRouting($routing);
	}

	/**
	 * Run a given request
	 *
	 * Currently mostly delegates to the legacy app
	 */
	public function run(Request $request)
	{
		if ( ! $this->booted)
		{
			show_error('Application must be booted before running.');
		}

		$this->running = TRUE;

		$application = $this->loadApplicationCore();

		if (defined('BOOT_ONLY'))
		{
			return $this->bootOnly($request);
		}

		// Memanggil hook pre_controller
		$this->callHook('pre_controller');

		$routing = $this->getRouting($request);


		$routing = $this->loadController($routing);
		$routing = $this->validateRequest($routing);

		$application->setRequest($request);
		$application->setResponse(new Response());

		// Memanggil hook post_controller_constructor
		$this->callHook('post_controller_constructor');
		$this->enableProfiler();
		$this->runController($routing);

		// Memanggil hook post_controller
		$this->callHook('post_controller');

		// Memanggil hook display_override
		$this->callHook('display_override');

		return $application->getResponse();
	}



	protected function enableProfiler(){
		$CI = &get_instance();
		//$CI->output->enable_profiler();
	}

	/**
	 * Loads ExpressionEngine without running a controller method
	 */
	protected function bootOnly(Request $request)
	{

		$this->legacy->includeBaseController();
		\CI_Controller::_setFacade($this->legacy->getFacade());
		new \CI_Controller();
	}

	/**
	 * Load a controller given the routing information
	 */
	protected function loadController($routing)
	{
		$this->legacy->includeBaseController();


		$modern_routing = $this->loadNamespacedController($routing);

		if ($modern_routing)
		{
			$routing = $modern_routing;
		}
		elseif ($this->legacy->isLegacyRouted($routing))
		{
			$this->legacy->loadController($routing);
		}

		return $routing;
	}

	protected function loadNamespacedController($routing)
	{
		$RTR = $GLOBALS['RTR'];
		$class  = $RTR->fetch_class(TRUE);
		$method = $RTR->fetch_method();
		$directory = $RTR->directory;



		$namespace = is_string($directory) && $directory !== ''
		    ? str_replace(['.', '/'], ['', '\\'], trim($directory, '/'))
		    : 'Controllers';

		if (is_string($directory) && !in_array('Modules', explode('/', $directory))) {
		    $namespace = $directory
		        ? 'Controllers\\' . str_replace(['.', '/'], ['', '\\'], trim($directory, '/'))
		        : 'Controllers';
		}

		$namespace = trim($namespace,'\\');

		//echo $namespace_module;
		$resolve_namespace = resolve_namespace($namespace, ucwords($class), false);
		$namespace_module_system = resolve_namespace($namespace, ucwords($class), true);

		if(class_exists($resolve_namespace)) {
			$class = $resolve_namespace;
		}elseif(class_exists($namespace_module_system)) {
			$class = $namespace_module_system;
		}

		// First try a fully namespaced class, with fallback
		if ( ! class_exists($class))
		{

			// If that didn't work try a fallback class matching the directory name
			$old_class = $RTR->fetch_class();
			$old_method = $method;

			$RTR->set_method($RTR->fetch_class());

			$directories = explode('/', rtrim($RTR->fetch_directory(), '/'));
			$RTR->set_class(array_pop($directories));

			$class  = $RTR->fetch_class(TRUE);
			$method = $RTR->fetch_method();
		}

		if ( ! class_exists($class))
		{
			$RTR->set_class($old_class);
			$RTR->set_method($old_method);

			return FALSE;
		}

		$controller_methods = array_map(
			'strtolower', get_class_methods($class)
		);

		// This allows for routes of 'cp/channels/layout/1' to end up calling
		// \Kodhe\Pulen\Controller\Channels\Layout::layout(1)
		if ( ! in_array($method, $controller_methods)
			&& in_array($RTR->fetch_class(), $controller_methods))
		{
			array_unshift($routing['segments'], $method);
			$method = $RTR->fetch_class();
		}


		$routing['class'] = $class;
		$routing['method'] = $method;

		return $routing;
	}

	/**
	 * Run a controller given the routing information
	 */
	protected function runController($routing)
	{
		$class  = $routing['class'];
		$method = $routing['method'];
		$params = $routing['segments'];

		// set the legacy facade before instantiating
		$class::_setFacade($this->legacy->getFacade());

		$controller_name = substr($class, strpos($class, 'Controller\\') + 11);
		// here we go!
		// Catch anything that might bubble up from inside our app

		try
		{
			//$resolve =  new \Kodhe\Pulen\Framework\Container\DependencyResolver();
			//$controller = new $class;
			$controller = $this->resolve->resolve($class, $params, false);

			foreach ($this->resolve->classLoaded as $var => $object)
			{
				if(strpos(strtolower($class), $var) === false) {
					$var = ($var == 'loader') ? 'load' : $var;
					kodhe()->set($var, $object);
				}
			}

			// we can only ascertain method signatures for real methods, not magic __call()s
			if (method_exists($controller, $method))
			{
				$reflection = new \ReflectionMethod($controller, $method);
				$parameters = $reflection->getParameters();
				/*
				$dependencies = array_map(
						fn($param) => $this->resolve->resolve($param),
						$parameters
				);*/

				if (count($params) < $reflection->getNumberOfRequiredParameters())
				{
					show_404();
				}



			}

			$result = call_user_func_array(array($controller, $method), $params);
			//$result = call_user_func_array([$controller, $method], array_merge($dependencies, $params));

		}
		catch (FileNotFound $ex)
		{

			$error_routing = $this->getErrorRouting();

			if ($routing['class'] == $error_routing['class'])
			{
				show_error('Fatal: Error handler could not be found.');
			}

			return $this->runController($error_routing);
		}
		catch (\Exception $ex)
		{
			show_exception($ex);
		}

		// Memanggil hook cache_override (jika digunakan untuk caching)
		$this->callHook('cache_override');

		if (isset($result))
		{
			kodhe('Response')->setBody($result);
		}
	}



	/**
	 * Get the 404 controller
	 */
	protected function getErrorRouting()
	{
		$qs = '';
		$get = $_GET;

		unset($get['D'], $get['C'], $get['M'], $get['S']);

		if ( ! empty($get))
		{
			$qs = '&'.http_build_query($get);
		}

		$class = 'Kodhe\Pulen\Modules\NotFound\Controllers\NotFound';
		$method = 'index';
		$RTR = $GLOBALS['RTR'];
		if (isset($RTR->routes['404_override']) AND $RTR->routes['404_override']) {
			$segments = explode('/', $RTR->routes['404_override']);
			$method = $segments[1];
			$segments[0] = ucfirst($segments[0]);
			if(!class_exists($class = resolve_namespace('Controllers',$segments[0], false))) {
				$class = resolve_namespace('Controllers',$segments[0], false);
			}

		}

		return array(
			'class' => $class,
			'method' => $method,
			'segments' => array(kodhe()->uri->uri_string().$qs)
		);
	}

	/**
	 * Set an execution time limit
	 */
	public function setTimeLimit($t)
	{
		if (function_exists("set_time_limit"))
		{
			@set_time_limit($t);
		}
	}

	/**
	 * Setup the application with the default provider
	 */
	protected function loadApplicationCore()
	{
		$autoloader   = Autoloader::getInstance();
		$dependencies = new InjectionContainer();
		$providers    = new ProviderRegistry($dependencies);
		$application  = new Application($autoloader, $dependencies, $providers);

		// Base Provider
		$baseProvider = $application->addProvider(
			VENDORPATH,
			'Setup.php',
			'kodhe'
		);

		$baseProvider->setConfigPath(VENDORPATH.'Config');
		// Application Provider
		$appProvider = $application->addProvider(
			APPPATH,
			'app.setup.php',
			'App'
		);

		$appProvider->setConfigPath($this->getConfigPath());

		// Modules Provider
    if(is_array(\Kodhe\Pulen\Framework\Modules\Module::$locations)) {
      foreach (\Kodhe\Pulen\Framework\Modules\Module::$locations as $location => $offset) {
        if(is_dir($location)) {

					$folders = new \FilesystemIterator($location, \FilesystemIterator::UNIX_PATHS);

					foreach ($folders as $item)
					{
						if ($item->isDir())
						{
							$path = $item->getPathname();

							// for now only setup those that define an addon.setup file
							if ( ! file_exists($path.'/addon.setup.php'))
							{
								continue;
							}

							$moduleProvider = $application->addProvider($path);

							$moduleProvider->setConfigPath(resolve_path($path,'config'));
						}
					}

        }
      }
    }


		$dependencies->register('App', function($di, $prefix = NULL) use ($application)
		{

			if (!empty($prefix))
			{
				return $application->get($prefix);
			}


			return $application;
		});


		$this->legacy->getFacade()->set('di', $dependencies);

		return $application;
	}


	/**
	 * Retrieve the config path for this core
	 * @return string Config path
	 */
	protected function getConfigPath()
	{
		return resolve_path(APPPATH,'config');
	}

	/**
	 * Boot the legacy application including all of the CI globals
	 */
	protected function bootLegacyApplicationCore()
	{
		$this->legacy = new Legacy();
		$this->legacy->boot($this->booted);

		$this->legacy->overwriteCore();
	}

	/**
	 * Get the routing for a request. Smoke and mirrors.
	 */
	protected function getRouting($request)
	{
		return $this->legacy->getRouting();
	}

	/**
	 * Validate the request
	 */
	protected function validateRequest($routing)
	{
		$routing = $this->legacy->validateRequest($routing);

		if ($routing === FALSE)
		{
			return $this->getErrorRouting();
		}

		return $routing;
	}


	/**
	 * Memanggil hook tertentu
	 */
	protected function callHook($hook)
	{
			if (function_exists('load_class')) {
					$hooks =& load_class('Kodhe\Pulen\Framework\Hooks\Hooks', 'core');
					if ($hooks->call_hook($hook) === false) {
							log_message('debug', "Hook '$hook' tidak ditemukan atau tidak aktif.");
					}
			}
	}

	/**
	 * Shutdown process (post_system)
	 */
	public function shutdown()
	{
			// Memanggil hook post_system
			$this->callHook('post_system');
	}


}

// EOF
