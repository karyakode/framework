<?php
use Kodhe\Pulen\Framework\Application\Provider;
use Kodhe\Pulen\Framework\Loader\Library;
use Kodhe\Pulen\Framework\Loader\Helpers;
use Kodhe\Pulen\Framework\Loader\Models;
use Kodhe\Pulen\Framework\Config\Config;
use Kodhe\Pulen\Framework\Loader\Loader;
use Kodhe\Pulen\Framework\Support\Path\Paths;

return [
	'namespace' => 'Kodhe\Pulen',
	'author' => 'Karya Kode',
	'name' => 'Kodhe Framework',
	'version' => '0.3.3.2.0',
	'link' => 'https://karyakode.id',
	'date' => date('Y-m-d'),
	'description' => "The page you are looking at is being generated dynamically by Karya Kode.",
	'services'=> [
		'load' => function($kodhe)
		{
			return new Loader($kodhe);
		},
	],
	'services.singletons' => [
		'Cookie' => function($kodhe)
		{

		},

		'CookieRegistry' => function($kodhe)
		{

		},
		'Library' => function($kodhe)
		{
			return new Library(new Paths);
		},
		'Helper' => function($kodhe)
		{
			return new Helpers(new Paths);
		},
		'Model' => function($kodhe)
		{
			return new Models(new Paths);
		},
		'View' => function($kodhe)
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

			return new ServiceView($kodhe);
		},
		'Config' => function($kodhe)
		{
			return new Config($kodhe);
		},
		'Lang' => function($kodhe)
		{
			class ServiceLanguage
			{
				protected $provider;

				function __construct(Provider $provider) {
					$this->provider = $provider;
				}
				public function make($langfile, $idiom = '', $return = false, $add_suffix = true, $alt_path = ''){

					return kodhe()->load->language($langfile, $idiom, $return, $add_suffix, $alt_path);
				}
			}

			return new ServiceLanguage($kodhe);
		},
		'Driver' => function($kodhe)
		{
			class ServiceDriver
			{
				protected $provider;

				function __construct(Provider $provider) {
					$this->provider = $provider;
				}
				public function make($library, $params = NULL, $object_name = NULL){

					return kodhe()->load->driver($library, $params, $object_name);
				}
			}

			return new ServiceDriver($kodhe);
		},

		'setup' => function($kodhe)
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
						$provider = $provider->make('App')->get('kodhe')->get($name);
					}

					return $provider;
				}
			}

			return new ServiceSetup($kodhe);
		},
		'Request' => function($kodhe)
		{
			return $kodhe->make('App')->getRequest();
		},

		'Response' => function($kodhe)
		{
			return $kodhe->make('App')->getResponse();
		},
		'Model/Datastore'=> function($provider){
      $app = $provider->make('App');
      $config = new Kodhe\Pulen\Framework\Model\Configuration();
			$config->setDefaultPrefix($provider->getPrefix());
			$config->setModelAliases($app->getModels());
			//$config->setEnabledPrefixes($installed_prefixes);
			$config->setModelDependencies($app->forward('getModelDependencies'));

      $DataStore =  new Kodhe\Pulen\Framework\Model\DataStore(new Kodhe\Pulen\Database\Database(), $config);

      return $DataStore;
    },

    'Model' => function($ee)
		{
      $facade = new Kodhe\Pulen\Framework\Model\Facade($ee->make('Model/Datastore'));

			return $facade;
		},

	]

];
// EOF
