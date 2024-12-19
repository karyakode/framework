<?php
namespace Flame\Core\Facade;

use InvalidArgumentException;
use RuntimeException;

/**
 * Facade to the legacy API, where the SuperObject contained
 * references to all of the silly stuff.
 */
class Facade {

	protected $loaded = [];
	protected $in_scope = 0;

	/**
	 * Magic getter untuk mengambil properti.
	 *
	 * @param string $name
	 * @return mixed
	 * @throws InvalidArgumentException
	 */
	public function __get($name)
	{
			return $this->get($name);
	}

	/**
	 * Magic isset untuk memeriksa keberadaan properti.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function __isset($name)
	{
			return $this->has($name);
	}

	/**
	 * Magic setter untuk menetapkan nilai properti.
	 *
	 * @param string $name
	 * @param mixed $value
	 * @throws RuntimeException
	 */
	public function __set($name, $value)
	{
			trigger_error("Setting values on this object is deprecated. Tried to set {$name}.",E_USER_DEPRECATED);
			$this->set($name, $value);
	}

	/**
	 * Magic call untuk memanggil metode secara dinamis.
	 *
	 * @param string $method
	 * @param array $args
	 * @return mixed
	 * @throws BadMethodCallException
	 */
	public function __call($method, $args)
	{
			if ($this->in_scope && $this->has('load')) {
					$callback = [$this->get('load'), $method];

					if (is_callable($callback)) {
							return call_user_func_array($callback, $args);
					}
			} elseif ($this->has('__legacy_controller')) {
					$obj = $this->get('__legacy_controller');

					if ($this->has('_mcp_reference')) {
							$obj = $this->get('_mcp_reference');
					}

					if (method_exists($obj, $method)) {
							return call_user_func_array([$obj, $method], $args);
					}
			}

			throw new BadMethodCallException("Method '{$method}' does not exist or is not callable.");
	}

	/**
	 * Menetapkan objek ke loader.
	 *
	 * @param string $name
	 * @param mixed $object
	 * @throws RuntimeException
	 */
	public function set($name, $object)
	{
			if ($this->has($name)) {
				$this->remove($name);
				//return;
					//throw new RuntimeException("Cannot overwrite existing property: {$name}.");
			}

			$this->loaded[$name] = $object;
	}

	/**
	 * Menghapus properti dari loader.
	 *
	 * @param string $name
	 */
	public function remove($name)
	{
			unset($this->loaded[$name]);
	}

	/**
	 * Mendapatkan nilai properti dari loader.
	 *
	 * @param string $name
	 * @return mixed
	 * @throws InvalidArgumentException
	 */
	public function get($name)
	{
			if ($this->has($name)) {
					return $this->loaded[$name];
			}

			throw new InvalidArgumentException("Property '{$name}' does not exist.");
	}

	/**
	 * Memeriksa apakah properti ada dalam loader.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function has($name)
	{
			return array_key_exists($name, $this->loaded);
	}

	public function runFileInFacadeScope($path, array $vars, $eval = false)
	{
	    if (!file_exists($path) || !is_readable($path)) {
	        throw new InvalidArgumentException("File tidak ditemukan atau tidak dapat dibaca: $path");
	    }

	    if ($eval) {
	        $str = file_get_contents($path);
	        return $this->evalStringInFacadeScope($str, $vars);
	    }

	    $this->in_scope++;

	    try {
	        // Batasi scope variabel yang diekstrak
	        $vars = array_filter($vars, 'is_scalar'); // Pastikan hanya variabel sederhana yang diekstrak
	        extract($vars, EXTR_SKIP); // Gunakan EXTR_SKIP untuk menghindari overwrite variabel penting
	        include $path;
	    } catch (Throwable $e) {
	        error_log("Error saat menjalankan file: " . $e->getMessage());
	        throw $e; // Opsional: Kembalikan error ke level aplikasi
	    } finally {
	        $this->in_scope--;
	    }
	}

	/**
	 * Evaluasi string PHP dalam scope yang aman.
	 */
	public function evalStringInFacadeScope($string, array $vars)
	{
	    if (stripos($string, '<?php') !== false || stripos($string, '<?') !== false) {
	        throw new InvalidArgumentException("Kode PHP mentah tidak diizinkan dalam eval.");
	    }

	    $this->in_scope++;

	    try {
	        $vars = array_filter($vars, 'is_scalar'); // Validasi variabel
	        extract($vars, EXTR_SKIP);

	        // Gunakan fungsi sandbox yang lebih aman
	        $output = eval('?>' . htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
	        return $output;
	    } catch (Throwable $e) {
	        error_log("Error saat mengevaluasi string: " . $e->getMessage());
	        throw $e;
	    } finally {
	        $this->in_scope--;
	    }
	}

}
