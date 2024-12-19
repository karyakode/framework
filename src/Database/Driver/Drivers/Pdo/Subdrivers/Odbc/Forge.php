<?php namespace Flame\Database\Driver\Drivers\Pdo\Subdrivers\Odbc;
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * PDO ODBC Forge Class
 *
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		https://codeigniter.com/database/
 */
class Forge extends \Flame\Database\Driver\Drivers\Pdo\Forge {

	/**
	 * UNSIGNED support
	 *
	 * @var	bool|array
	 */
	protected $_unsigned		= FALSE;

	// --------------------------------------------------------------------

	/**
	 * Field attribute AUTO_INCREMENT
	 *
	 * @param	array	&$attributes
	 * @param	array	&$field
	 * @return	void
	 */
	protected function _attr_auto_increment(&$attributes, &$field)
	{
		// Not supported (in most databases at least)
	}

}
