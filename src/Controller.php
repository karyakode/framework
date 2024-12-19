<?php namespace Kodhe;
use Kodhe\Core\Engine\BaseController;
class Controller extends BaseController
{

  function __construct()
  {
    parent::__construct();

    $this->load->library('core');
    //$this->core->bootstrap();

    $this->load->initialize();

  }

  public function __call($method, $arguments)
  {


      if (method_exists(self::$facade, $method)) {
          return call_user_func_array([self::$facade, $method], $arguments);
      }

      if (method_exists('Kodhe\Modules\NotFound\Controllers\NotFound', 'index')) {
        return call_user_func_array(['Kodhe\Modules\NotFound\Controllers\NotFound', 'index'], $arguments);
      }
  }


  /**
   * Set the legacy facade.
   *
   * @param object $facade
   * @throws Exception
   */
  public static function _setFacade(object $facade): void
  {
      if (isset(self::$facade)) {
          throw new \Exception('Cannot change the facade after boot');
      }

      self::$facade = $facade;
  }

  public static function &get_instance($dep = null)
  {
    global $kodhe;

    if (!empty($dep) && $kodhe->di)
		{
			$args = func_get_args();
      $call_func = call_user_func_array(array($kodhe->di, 'make'), $args);
			return $call_func;
		}

		return $kodhe;
  }
}
