<?php namespace Flame\Database\Driver\Drivers\Sqlite3;
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * SQLite3 Utility Class
 *
 * @category	Database
 * @author	Andrey Andreev
 * @link	https://codeigniter.com/user_guide/database/
 */
class Utility extends \Flame\Database\Utility\Utility {

	/**
	 * Export
	 *
	 * @param	array	$params	Preferences
	 * @return	mixed
	 */
	protected function _backup($params = array())
	{
		// Not supported
		return $this->db->display_error('db_unsupported_feature');
	}

}
