<?php namespace Flame\Libraries\Cache;
defined('BASEPATH') OR exit('No direct script access allowed');
use Flame\Libraries\Driver\Library as DriverLibrary;
/**
 * CodeIgniter Caching Class
 *
 * @package     CodeIgniter
 * @subpackage  Libraries
 * @category    Core
 * @author      EllisLab Dev Team
 * @link
 */
class Cache extends DriverLibrary {

    /**
     * Valid cache drivers
     *
     * @var array
     */
    protected $valid_drivers = array(
        'apc',
        'dummy',
        'file',
        'memcached',
        'redis',
        'wincache'
    );

    /**
     * Path of cache files (if file-based cache)
     *
     * @var string
     */
    protected $_cache_path = NULL;

    /**
     * Reference to the driver
     *
     * @var mixed
     */
    protected $_adapter = 'file';

    /**
     * Fallback driver
     *
     * @var string
     */
    protected $_backup_driver = 'file';

    /**
     * Cache key prefix
     *
     * @var string
     */
    public $key_prefix = '';

    /**
     * Constructor
     *
     * Initialize class properties based on the configuration array.
     *
     * @param array $config Configuration array
     * @return void
     */
    public function __construct($config = array())
    {

        isset($config['adapter']) && $this->_adapter = $config['adapter'];
        isset($config['backup']) && $this->_backup_driver = $config['backup'];
        isset($config['key_prefix']) && $this->key_prefix = $config['key_prefix'];


        // If the specified adapter isn't available, check the backup.
        if ( ! $this->is_supported($this->_adapter))
        {

            if ( ! $this->is_supported($this->_backup_driver))
            {
                // Backup isn't supported either. Default to 'Dummy' driver.
                log_message('error', 'Cache adapter "'.$this->_adapter.'" and backup "'.$this->_backup_driver.'" are both unavailable. Cache is now using "Dummy" adapter.');
                $this->_adapter = 'dummy';
            }
            else
            {
                // Backup is supported. Set it to primary.
                log_message('debug', 'Cache adapter "'.$this->_adapter.'" is unavailable. Falling back to "'.$this->_backup_driver.'" backup adapter.');
                $this->_adapter = $this->_backup_driver;
            }
        }
    }

    /**
     * Get a cache item
     *
     * @param string $id Cache ID
     * @return mixed Value matching $id or FALSE on failure
     */
    public function get($id)
    {
        return $this->{$this->_adapter}->get($this->key_prefix.$id);
    }

    /**
     * Save a cache item
     *
     * @param string $id Cache ID
     * @param mixed $data Data to store
     * @param int $ttl Cache TTL (in seconds)
     * @param bool $raw Whether to store the raw value
     * @return bool TRUE on success, FALSE on failure
     */
    public function save($id, $data, $ttl = 60, $raw = FALSE)
    {
        return $this->{$this->_adapter}->save($this->key_prefix.$id, $data, $ttl, $raw);
    }

    /**
     * Delete a cache item
     *
     * @param string $id Cache ID
     * @return bool TRUE on success, FALSE on failure
     */
    public function delete($id)
    {
        return $this->{$this->_adapter}->delete($this->key_prefix.$id);
    }

    /**
     * Increment a cache item
     *
     * @param string $id Cache ID
     * @param int $offset Step/value to add
     * @return mixed New value on success or FALSE on failure
     */
    public function increment($id, $offset = 1)
    {
        return $this->{$this->_adapter}->increment($this->key_prefix.$id, $offset);
    }

    /**
     * Decrement a cache item
     *
     * @param string $id Cache ID
     * @param int $offset Step/value to reduce by
     * @return mixed New value on success or FALSE on failure
     */
    public function decrement($id, $offset = 1)
    {
        return $this->{$this->_adapter}->decrement($this->key_prefix.$id, $offset);
    }

    /**
     * Clean the cache
     *
     * @return bool TRUE on success, FALSE on failure
     */
    public function clean()
    {
        return $this->{$this->_adapter}->clean();
    }

    /**
     * Cache info
     *
     * @param string $type Cache type
     * @return mixed Cache info or FALSE on failure
     */
    public function cache_info($type = 'user')
    {
        return $this->{$this->_adapter}->cache_info($type);
    }

    /**
     * Get cache metadata
     *
     * @param string $id Cache ID
     * @return mixed Cache item metadata
     */
    public function get_metadata($id)
    {
        return $this->{$this->_adapter}->get_metadata($this->key_prefix.$id);
    }

    /**
     * Check if the driver is supported
     *
     * @param string $driver Driver name
     * @return bool TRUE if supported, FALSE otherwise
     */
    public function is_supported($driver)
    {
        static $support = [];

        if (empty($support[$driver]))
        {
          $support[$driver] = $this->{$driver}->is_supported();
        }

        return $support[$driver];
    }
}
