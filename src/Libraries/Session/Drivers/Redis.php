<?php namespace Kodhe\Pulen\Libraries\Session\Drivers;
defined('BASEPATH') OR exit('No direct script access allowed');
use Kodhe\Pulen\Libraries\Session\Driver;
use Kodhe\Pulen\Libraries\Session\HandlerInterface;
/**
 * CodeIgniter Session Redis Driver
 *
 * @package	CodeIgniter
 * @subpackage	Libraries
 * @category	Sessions
 * @author	Andrey Andreev
 * @link	https://codeigniter.com/user_guide/libraries/sessions.html
 */
class Redis extends Driver implements HandlerInterface {

	/**
	 * phpRedis instance
	 *
	 * @var	Redis
	 */
	protected $_redis;

	/**
	 * Key prefix
	 *
	 * @var	string
	 */
	protected $_key_prefix = 'ci_session:';

	/**
	 * Lock key
	 *
	 * @var	string
	 */
	protected $_lock_key;

	/**
	 * Key exists flag
	 *
	 * @var bool
	 */
	protected $_key_exists = FALSE;

	/**
	 * Name of setTimeout() method in phpRedis
	 *
	 * Due to some deprecated methods in phpRedis, we need to call the
	 * specific methods depending on the version of phpRedis.
	 *
	 * @var string
	 */
	protected $_setTimeout_name;

	/**
	 * Name of delete() method in phpRedis
	 *
	 * Due to some deprecated methods in phpRedis, we need to call the
	 * specific methods depending on the version of phpRedis.
	 *
	 * @var string
	 */
	protected $_delete_name;

	/**
	 * Success return value of ping() method in phpRedis
	 *
	 * @var mixed
	 */
	protected $_ping_success;

	// ------------------------------------------------------------------------

	/**
	 * Class constructor
	 *
	 * @param	array	$params	Configuration parameters
	 * @return	void
	 */
	public function __construct(&$params)
	{
		parent::__construct($params);

		// Detect the names of some methods in phpRedis instance
		if (version_compare(phpversion('redis'), '5', '>='))
		{
			$this->_setTimeout_name = 'expire';
			$this->_delete_name = 'del';
			$this->_ping_success = TRUE;
		}
		else
		{
			$this->_setTimeout_name = 'setTimeout';
			$this->_delete_name = 'delete';
			$this->_ping_success = '+PONG';
		}

		if (empty($this->_config['save_path']))
		{
			log_message('error', 'Session: No Redis save path configured.');
		}
		elseif (preg_match('#(?:tcp://)?([^:?]+)(?:\:(\d+))?(\?.+)?#', $this->_config['save_path'], $matches))
		{
			isset($matches[3]) OR $matches[3] = ''; // Just to avoid undefined index notices below
			$this->_config['save_path'] = array(
				'host' => $matches[1],
				'port' => empty($matches[2]) ? NULL : $matches[2],
				'password' => preg_match('#auth=([^\s&]+)#', $matches[3], $match) ? $match[1] : NULL,
				'database' => preg_match('#database=(\d+)#', $matches[3], $match) ? (int) $match[1] : NULL,
				'timeout' => preg_match('#timeout=(\d+\.\d+)#', $matches[3], $match) ? (float) $match[1] : NULL
			);

			preg_match('#prefix=([^\s&]+)#', $matches[3], $match) && $this->_key_prefix = $match[1];
		}
		else
		{
			log_message('error', 'Session: Invalid Redis save path format: '.$this->_config['save_path']);
		}

		if ($this->_config['match_ip'] === TRUE)
		{
			$this->_key_prefix .= $_SERVER['REMOTE_ADDR'].':';
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * Open
	 *
	 * Sanitizes save_path and initializes connection.
	 *
	 * @param	string	$save_path	Server path
	 * @param	string	$name		Session cookie name, unused
	 * @return	bool
	 */
	public function open($save_path, $name)
	{
		if (empty($this->_config['save_path']))
		{
			return $this->_failure;
		}

		$redis = new Redis();
		if ( ! $redis->connect($this->_config['save_path']['host'], $this->_config['save_path']['port'], $this->_config['save_path']['timeout']))
		{
			log_message('error', 'Session: Unable to connect to Redis with the configured settings.');
		}
		elseif (isset($this->_config['save_path']['password']) && ! $redis->auth($this->_config['save_path']['password']))
		{
			log_message('error', 'Session: Unable to authenticate to Redis instance.');
		}
		elseif (isset($this->_config['save_path']['database']) && ! $redis->select($this->_config['save_path']['database']))
		{
			log_message('error', 'Session: Unable to select Redis database with index '.$this->_config['save_path']['database']);
		}
		else
		{
			$this->_redis = $redis;
			$this->php5_validate_id();
			return $this->_success;
		}

		return $this->_failure;
	}

	// ------------------------------------------------------------------------

	/**
	 * Read
	 *
	 * Reads session data and acquires a lock
	 *
	 * @param	string	$session_id	Session ID
	 * @return	string	Serialized session data
	 */
	public function read($session_id)
	{
		if (isset($this->_redis) && $this->_get_lock($session_id))
		{
			// Needed by write() to detect session_regenerate_id() calls
			$this->_session_id = $session_id;

			$session_data = $this->_redis->get($this->_key_prefix.$session_id);

			is_string($session_data)
				? $this->_key_exists = TRUE
				: $session_data = '';

			$this->_fingerprint = md5($session_data);
			return $session_data;
		}

		return $this->_failure;
	}

	// ------------------------------------------------------------------------

	/**
	 * Write
	 *
	 * Writes (create / update) session data
	 *
	 * @param	string	$session_id	Session ID
	 * @param	string	$session_data	Serialized session data
	 * @return	bool
	 */
	public function write($session_id, $session_data)
	{
		if ( ! isset($this->_redis, $this->_lock_key))
		{
			return $this->_failure;
		}
		// Was the ID regenerated?
		elseif ($session_id !== $this->_session_id)
		{
			if ( ! $this->_release_lock() OR ! $this->_get_lock($session_id))
			{
				return $this->_failure;
			}

			$this->_key_exists = FALSE;
			$this->_session_id = $session_id;
		}

		$this->_redis->{$this->_setTimeout_name}($this->_lock_key, 300);
		if ($this->_fingerprint !== ($fingerprint = md5($session_data)) OR $this->_key_exists === FALSE)
		{
			if ($this->_redis->set($this->_key_prefix.$session_id, $session_data, $this->_config['expiration']))
			{
				$this->_fingerprint = $fingerprint;
				$this->_key_exists = TRUE;
				return $this->_success;
			}

			return $this->_failure;
		}

		return ($this->_redis->{$this->_setTimeout_name}($this->_key_prefix.$session_id, $this->_config['expiration']))
			? $this->_success
			: $this->_failure;
	}

	// ------------------------------------------------------------------------

	/**
	 * Close
	 *
	 * Releases locks and closes connection.
	 *
	 * @return	bool
	 */
	public function close()
	{
		if (isset($this->_redis))
		{
			try {
				if ($this->_redis->ping() === $this->_ping_success)
				{
					$this->_release_lock();
					if ($this->_redis->close() === FALSE)
					{
						return $this->_failure;
					}
				}
			}
			catch (RedisException $e)
			{
				log_message('error', 'Session: Got RedisException on close(): '.$e->getMessage());
			}

			$this->_redis = NULL;
			return $this->_success;
		}

		return $this->_success;
	}

	// ------------------------------------------------------------------------

	/**
	 * Destroy
	 *
	 * Destroys the current session.
	 *
	 * @param	string	$session_id	Session ID
	 * @return	bool
	 */
	public function destroy($session_id)
	{
		if (isset($this->_redis, $this->_lock_key))
		{
			if (($result = $this->_redis->{$this->_delete_name}($this->_key_prefix.$session_id)) !== 1)
			{
				log_message('debug', 'Session: Redis::'.$this->_delete_name.'() expected to return 1, got '.var_export($result, TRUE).' instead.');
			}

			$this->_cookie_destroy();
			return $this->_success;
		}

		return $this->_failure;
	}

	// ------------------------------------------------------------------------

	/**
	 * Garbage Collector
	 *
	 * Deletes expired sessions
	 *
	 * @param	int 	$maxlifetime	Maximum lifetime of sessions
	 * @return	bool
	 */
	public function gc($maxlifetime)
	{
		// Not necessary, Redis takes care of that.
		return $this->_success;
	}

	// --------------------------------------------------------------------

	/**
	 * Validate ID
	 *
	 * Checks whether a session ID record exists server-side,
	 * to enforce session.use_strict_mode.
	 *
	 * @param	string	$id
	 * @return	bool
	 */
	public function validateSessionId($id)
	{
		return (bool) $this->_redis->exists($this->_key_prefix.$id);
	}

	// ------------------------------------------------------------------------

	/**
	 * Get lock
	 *
	 * Acquires an (emulated) lock.
	 *
	 * @param	string	$session_id	Session ID
	 * @return	bool
	 */
	protected function _get_lock($session_id)
	{
		// PHP 7 reuses the SessionHandler object on regeneration,
		// so we need to check here if the lock key is for the
		// correct session ID.
		if ($this->_lock_key === $this->_key_prefix.$session_id.':lock')
		{
			return $this->_redis->{$this->_setTimeout_name}($this->_lock_key, 300);
		}

		// 30 attempts to obtain a lock, in case another request already has it
		$lock_key = $this->_key_prefix.$session_id.':lock';
		$attempt = 0;
		do
		{
			if (($ttl = $this->_redis->ttl($lock_key)) > 0)
			{
				sleep(1);
				continue;
			}

			if ($ttl === -2 && ! $this->_redis->set($lock_key, time(), array('nx', 'ex' => 300)))
			{
				// Sleep for 1s to wait for lock releases.
				sleep(1);
				continue;
			}
			elseif ( ! $this->_redis->setex($lock_key, 300, time()))
			{
				log_message('error', 'Session: Error while trying to obtain lock for '.$this->_key_prefix.$session_id);
				return FALSE;
			}

			$this->_lock_key = $lock_key;
			break;
		}
		while (++$attempt < 30);

		if ($attempt === 30)
		{
			log_message('error', 'Session: Unable to obtain lock for '.$this->_key_prefix.$session_id.' after 30 attempts, aborting.');
			return FALSE;
		}
		elseif ($ttl === -1)
		{
			log_message('debug', 'Session: Lock for '.$this->_key_prefix.$session_id.' had no TTL, overriding.');
		}

		$this->_lock = TRUE;
		return TRUE;
	}

	// ------------------------------------------------------------------------

	/**
	 * Release lock
	 *
	 * Releases a previously acquired lock
	 *
	 * @return	bool
	 */
	protected function _release_lock()
	{
		if (isset($this->_redis, $this->_lock_key) && $this->_lock)
		{
			if ( ! $this->_redis->{$this->_delete_name}($this->_lock_key))
			{
				log_message('error', 'Session: Error while trying to free lock for '.$this->_lock_key);
				return FALSE;
			}

			$this->_lock_key = NULL;
			$this->_lock = FALSE;
		}

		return TRUE;
	}

}
