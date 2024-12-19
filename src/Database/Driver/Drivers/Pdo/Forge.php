<?php namespace Flame\Database\Driver\Drivers\Pdo;
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * PDO Forge Class
 *
 * @package		CodeIgniter
 * @subpackage	Drivers
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		https://codeigniter.com/database/
 */
use PDO;
class Forge extends \Flame\Database\Forge\Forge {

	/**
	 * CREATE TABLE IF statement
	 *
	 * @var	string
	 */
	protected $_create_table_if	= FALSE;

	/**
	 * DROP TABLE IF statement
	 *
	 * @var	string
	 */
	protected $_drop_table_if	= FALSE;

}
