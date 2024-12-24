<?php namespace Kodhe\Pulen\Libraries\Core;
use Kodhe\Pulen\Service\Module\Module;

class Core
{
  private $bootstrapped = FALSE;

  function __construct()
  {
    // code...
  }

  public function bootstrap()
	{
		if ($this->bootstrapped)
		{
			return;
		}

		$this->bootstrapped = TRUE;

    define('KODHE_VERSION', kodhe('setup')->get('version'));
    kodhe()->load->initialize();

  }


  public function getNamespace($prefix = 'kodhe'){

    return kodhe('setup')->get($prefix.':namespace');
  }

}
