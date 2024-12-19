<?php namespace Flame\Core\Router; defined('BASEPATH') OR exit('No direct script access allowed');

use Flame\Core\Module\Module;

class Router extends Legacy
{
    private $located = 0;

		public $module;

    public function fetch_module()
    {
        return $this->module;
    }

    protected function _set_request($segments = [])
    {
        if ($this->translate_uri_dashes === true) {
            foreach (range(0, 2) as $v) {
                isset($segments[$v]) && $segments[$v] = str_replace('-', '_', $segments[$v]);
            }
        }

				$segments[0] = ucfirst($segments[0]);
        $segments = $this->locate($segments);



        if ($this->located == -1) {
            $this->_set_404override_controller();
            return;
        }

        if (empty($segments)) {
            $this->_set_default_controller();
            return;
        }

        $this->set_class($segments[0]);

        if (isset($segments[1])) {
            $this->set_method($segments[1]);
        } else {
            $segments[1] = 'index';
        }

        array_unshift($segments, null);
        unset($segments[0]);
        $this->uri->rsegments = $segments;

    }

    protected function _set_404override_controller() {
			$this->_set_module_path($this->routes['404_override']);
				return;
    }

    /**
     * [_set_default_controller description]
     *
     * @method _set_default_controller
     */
    protected function _set_default_controller()
    {
        if (empty($this->directory)) {
            // set the default controller module path
            $this->_set_module_path($this->default_controller);
        }

        parent::_set_default_controller();

        if (empty($this->class)) {
            $this->_set_404override_controller();
        }
    }

    public function locate($segments)
    {
        $this->directory = null;
        $this->located = 0;
        $ext = $this->config->item('controller_suffix').'.php';

				$routes = Module::parse_routes($segments[0], implode('/', array_map('strtolower',$segments)));

        /* use module route if available */
        if (!empty($segments[0]) && $routes) {
            $segments = $routes;
        }


        // Backward function
        // Before PHP 7.1.0, list() only worked on numerical arrays and assumes the numerical indices start at 0.
        if (version_compare(phpversion(), '7.1', '<')) {
            // php version isn't high enough
            // get the segments array elements
            list($module, $directory, $controller) = array_pad($segments, 3, null);
        } else {
            [$module, $directory, $controller] = array_pad($segments, 3, null);
        }


				$module = $module;
				//$this->_set_module_path(implode('/',$segments));

				//if(!is_array(Module::$locations)) return;
        // check modules
        foreach (Module::$locations as $location => $offset) {
            // module exists?

            if (is_dir($source = resolve_path($location.$module,'controllers').'/')) {
                $this->module = $module;
                $this->directory = resolve_path($offset.$module,'controllers').'/';
                // module sub-controller exists?
                if ($directory) {

                    // module sub-directory exists?
                    if (is_dir($source.$directory.'/')) {
                        $source .= $directory.'/';
                        $this->directory .= $directory.'/';

                        // module sub-directory controller exists?

                        if ($controller) {
                            if (is_file($source.ucfirst($controller).$ext)) {
                                $this->located = 3;
                                return array_slice($segments, 2);
                            }
                            $this->located = -1;
                        }
                    } elseif (is_file($source.ucfirst($directory).$ext)) {
                        $this->located = 2;
												//print_r(array_slice($segments, 1));
                        return array_slice($segments, 1);
                    } else if (is_file($source.ucfirst($controller).$ext)) {
												$this->located = 3;
												return array_slice($segments, 2);
										}
										 else {
                        $this->located = -1;
                    }
                }

                // module controller exists?
                if (is_file($source.ucfirst($module).$ext)) {
                    $this->located = 1;
                    return $segments;
                }
            }
        }

        if (! empty($this->directory)) {
            return;
        }

        // application sub-directory controller exists?
        if ($directory) {
            if (is_file(resolve_path(APPPATH,'controllers').'/'.$module.'/'.ucfirst($directory).$ext)) {
                $this->directory = $module.'/';
                return array_slice($segments, 1);
            }

            // application sub-sub-directory controller exists?
            if ($controller && is_file(resolve_path(APPPATH,'controllers') . '/' . $module . '/' . $directory . '/' . ucfirst($controller) . $ext)) {
                $this->directory = $module.'/'.$directory.'/';
                return array_slice($segments, 2);
            }
        }

        // application controllers sub-directory exists?
        if (is_dir(resolve_path(APPPATH,'controllers').'/'.$module.'/')) {
            $this->directory = $module.'/';
            return array_slice($segments, 1);
        }

        // application controller exists?
        if (is_file(resolve_path(APPPATH,'controllers').'/'.ucfirst($module).$ext)) {
            return $segments;
        }
        $this->located = -1;
    }

    /**
     * [set module path]
     *
     * @method _set_module_path
     *
     * @param  [type]  &$_route [description]
     */
    protected function _set_module_path(&$_route)
    {

        if (! empty($_route)) {
            // Are module/directory/controller/method segments being specified?
            $sgs = sscanf($_route, '%[^/]/%[^/]/%[^/]/%s', $module, $directory, $class, $method);

						$module = ucwords($module);
            // set the module/controller directory location if found
            if ($this->locate([$module, $directory, $class])) {
                //reset to class/method
                switch ($sgs) {
                    case 1: $_route = $module.'/index';
                        break;
                    case 2: $_route = ($this->located < 2) ? $module.'/'.$directory : $directory.'/index';
                        break;
                    case 3: $_route = ($this->located == 2) ? $directory.'/'.$class : $class.'/index';
                        break;
                    case 4: $_route = ($this->located == 3) ? $class.'/'.$method : $method.'/index';
                        break;
                }


            }
        }


				return $_route;
    }

    /**
     * [set_class description]
     *
     * @method set_class
     *
     * @param  [type]    $class [description]
     */
    public function set_class($class)
    {

        $suffix = $this->config->item('controller_suffix');
        // Fixing Error Message: strpos(): Non-string needles will be interpreted as strings in the future.
        // Use an explicit chr() call to preserve the current behavior.
        if ($suffix && strpos($class, $suffix) === false) {
            $class .= $suffix;
        }
        parent::set_class($class);
    }


		function _parse_routes() {
				// Apply the current module's routing config
				// CI v3.x has URI starting at segment 1
				$segstart = (intval(substr(CI_VERSION,0,1)) > 2) ? 1 : 0;
				if ($module = $this->uri->segment($segstart)) {
						foreach (Module::$locations as $location => $offset) {
							$file = resolve_path($location . ucwords($module),'config') . '/routes.php';
								if (is_file($file)) {
										include ($file);
										$route = (!isset($route) or !is_array($route)) ? array() : $route;
										$this->routes = array_merge($this->routes, $route);
										unset($route);
								}
						}
				}

				// Let parent do the heavy routing
				return parent::_parse_routes();
		}



		private function update_module_locations($site_ref)
		{
			$locations = array();
			if(is_array(config_item('modules_locations'))) {
				foreach (config_item('modules_locations') AS $location => $offset)
				{
					$locations[str_replace('__SITE_REF__', $site_ref, $location)] = str_replace('__SITE_REF__', $site_ref, $offset);
				}
				Module::$locations = $locations;
			}

		}

}
