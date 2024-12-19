<?php namespace Kodhe\Libraries\Core;
use Kodhe\Service\Module\Module;

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

  }


  public function getNamespace($prefix = 'kodhe'){

    return kodhe('setup')->get($prefix.':namespace');
  }

}
