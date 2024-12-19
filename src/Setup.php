<?php
use \Flame\Core\Engine\Provider;
use \Flame\Core\Loader\Library;
use \Flame\Core\Loader\Helpers;
use \Flame\Core\Loader\Models;
use \Flame\Core\Config\Config;
use \Flame\Core\Loader\Loader;
use \Flame\Core\Path\Paths;

return [
	'namespace' => 'Flame',
	'author' => 'Karya Kode',
	'name' => 'Flame Framework',
	'version' => '0.3.2.2.0',
	'link' => 'https://karyakode.id',
	'date' => date('Y-m-d'),
	'description' => "The page you are looking at is being generated dynamically by Karya Kode.",
	'services'=> [
		'load' => function($flame)
		{
			return new Loader($flame);
		},
	],
	'services.singletons' => [
		'Cookie' => function($flame)
		{

		},

		'CookieRegistry' => function($flame)
		{

		},
		'Library' => function($flame)
		{
			return new Library(new Paths);
		},
		'Helper' => function($flame)
		{
			return new Helpers(new Paths);
		},
		'Model' => function($flame)
		{
			return new Models(new Paths);
		},
		'View' => function($flame)
		{
			class ServiceView
			{
				protected $provider;

				function __construct(Provider $provider) {
					$this->provider = $provider;
				}
				public function make($view, $vars = [], $return = false){

					return $this->provider->make('load')->view($view, $vars, $return);
				}
			}

			return new ServiceView($flame);
		},
		'Config' => function($flame)
		{
			return new Config($flame);
		},
		'Lang' => function($flame)
		{
			class ServiceLanguage
			{
				protected $provider;

				function __construct(Provider $provider) {
					$this->provider = $provider;
				}
				public function make($langfile, $idiom = '', $return = false, $add_suffix = true, $alt_path = ''){

					return flame()->load->language($langfile, $idiom, $return, $add_suffix, $alt_path);
				}
			}

			return new ServiceLanguage($flame);
		},
		'Driver' => function($flame)
		{
			class ServiceDriver
			{
				protected $provider;

				function __construct(Provider $provider) {
					$this->provider = $provider;
				}
				public function make($library, $params = NULL, $object_name = NULL){

					return flame()->load->driver($library, $params, $object_name);
				}
			}

			return new ServiceDriver($flame);
		},

		'setup' => function($flame)
		{
			class ServiceSetup
			{
				protected $provider;

				function __construct(Provider $provider)
				{
					$this->provider = $provider;
				}

				public function get($name)
				{
					$provider = $this->provider;
					if (strpos($name, ':'))
					{
						list($prefix, $name) = explode(':', $name, 2);
						$provider = $provider->make('App')->get($prefix)->get($name);
					} else {
						$provider = $provider->make('App')->get('flame')->get($name);
					}

					return $provider;
				}
			}

			return new ServiceSetup($flame);
		},
		'Request' => function($flame)
		{
			return $flame->make('App')->getRequest();
		},

		'Response' => function($flame)
		{
			return $flame->make('App')->getResponse();
		},
		'Model/Datastore'=> function($provider){
      $app = $provider->make('App');
      $config = new Flame\Core\Model\Configuration();
			$config->setDefaultPrefix($provider->getPrefix());
			$config->setModelAliases($app->getModels());
			//$config->setEnabledPrefixes($installed_prefixes);
			$config->setModelDependencies($app->forward('getModelDependencies'));

      $DataStore =  new Flame\Core\Model\DataStore(new Flame\Database\Database(), $config);

      return $DataStore;
    },

    'Model' => function($ee)
		{
      $facade = new Flame\Core\Model\Facade($ee->make('Model/Datastore'));

			return $facade;
		},

	]

];
// EOF
