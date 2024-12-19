<?php

namespace Flame\Core\Loader\Driver;

use Flame\Core\Loader\Library\Library;

class Driver
{
    /**
     * Instance of the Library loader.
     *
     * @var Library
     */
    protected $library;

    /**
     * Driver constructor.
     *
     * @param Library $library
     */
    public function __construct(Library $library)
    {
        $this->library = $library;
    }

    /**
     * Load a driver or a list of drivers.
     *
     * @param string|array $library The driver name(s) to load.
     * @param mixed|null $params Parameters to pass to the driver.
     * @param string|null $object_name Custom object name for the driver instance.
     * @return $this|bool
     */
    public function make($library, $params = null, $object_name = null)
    {
        // Handle an array of libraries
        if (is_array($library)) {
            foreach ($library as $key => $value) {
                if (is_int($key)) {
                    $this->make($value, $params);
                } else {
                    $this->make($key, $params, $value);
                }
            }
            return $this;
        }

        // Handle invalid or empty input
        if (empty($library)) {
            return false;
        }


        $library = ucfirst($library);

        // Format the library name correctly
        if (strpos($library, '/') === false) {
            $library = $library . '/' . $library;
        }

        // Delegate the loading process to the Library loader
        return $this->library->make($library, $params, $object_name);
    }
}
