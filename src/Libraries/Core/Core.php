<?php namespace Flame\Libraries\Core;
use Flame\Service\Module\Module;

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



    define('FLAME_VERSION', flame('setup')->get('version'));

  }


  public function getNamespace($prefix = 'flame'){

    return flame('setup')->get($prefix.':namespace');
  }

}
