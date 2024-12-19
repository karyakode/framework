<?php namespace Flame\Core\Engine;

class BaseController
{
  protected static ?object $facade = null; // Instance facade
  protected static ?object $instance = null; // Instance facade

    public function __construct()
    {
      global $flame;
      $flame = $this;
      log_message('info', 'Controller Class Initialized');
      if(!$this->has('__legacy_controller')) $this->set('__legacy_controller', $this);
    }


    /**
     * Magic method to get properties from the facade.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        return self::$facade->get($name);
    }



}
