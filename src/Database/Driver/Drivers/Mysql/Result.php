<?php namespace Kodhe\Pulen\Database\Driver\Drivers\Mysql;
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MySQL Result Class
 *
 * This class extends the parent result class: CI_DB_result
 *
 * @category	Database
 * @author		EllisLab Dev Team
 * @link		https://codeigniter.com/user_guide/database/
 */
class Result extends \Kodhe\Pulen\Database\Result\Result {

	/**
	 * Class constructor
	 *
	 * @param	object	&$driver_object
	 * @return	void
	 */
	public function __construct(&$driver_object)
	{
		parent::__construct($driver_object);

		// Required, due to mysql_data_seek() causing nightmares
		// with empty result sets
		$this->num_rows = mysql_num_rows($this->result_id);
	}

	// --------------------------------------------------------------------

	/**
	 * Number of rows in the result set
	 *
	 * @return	int
	 */
	public function num_rows()
	{
		return $this->num_rows;
	}

	// --------------------------------------------------------------------

	/**
	 * Number of fields in the result set
	 *
	 * @return	int
	 */
	public function num_fields()
	{
		return mysql_num_fields($this->result_id);
	}

	// --------------------------------------------------------------------

	/**
	 * Fetch Field Names
	 *
	 * Generates an array of column names
	 *
	 * @return	array
	 */
	public function list_fields()
	{
		$field_names = array();
		mysql_field_seek($this->result_id, 0);
		while ($field = mysql_fetch_field($this->result_id))
		{
			$field_names[] = $field->name;
		}

		return $field_names;
	}

	// --------------------------------------------------------------------

	/**
	 * Field data
	 *
	 * Generates an array of objects containing field meta-data
	 *
	 * @return	array
	 */
	public function field_data()
	{
		$retval = array();
		for ($i = 0, $c = $this->num_fields(); $i < $c; $i++)
		{
			$retval[$i]			= new stdClass();
			$retval[$i]->name		= mysql_field_name($this->result_id, $i);
			$retval[$i]->type		= mysql_field_type($this->result_id, $i);
			$retval[$i]->max_length		= mysql_field_len($this->result_id, $i);
			$retval[$i]->primary_key	= (int) (strpos(mysql_field_flags($this->result_id, $i), 'primary_key') !== FALSE);
		}

		return $retval;
	}

	// --------------------------------------------------------------------

	/**
	 * Free the result
	 *
	 * @return	void
	 */
	public function free_result()
	{
		if (is_resource($this->result_id))
		{
			mysql_free_result($this->result_id);
			$this->result_id = FALSE;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Data Seek
	 *
	 * Moves the internal pointer to the desired offset. We call
	 * this internally before fetching results to make sure the
	 * result set starts at zero.
	 *
	 * @param	int	$n
	 * @return	bool
	 */
	public function data_seek($n = 0)
	{
		return $this->num_rows
			? mysql_data_seek($this->result_id, $n)
			: FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Result - associative array
	 *
	 * Returns the result set as an array
	 *
	 * @return	array
	 */
	protected function _fetch_assoc()
	{
		return mysql_fetch_assoc($this->result_id);
	}

	// --------------------------------------------------------------------

	/**
	 * Result - object
	 *
	 * Returns the result set as an object
	 *
	 * @param	string	$class_name
	 * @return	object
	 */
	protected function _fetch_object($class_name = 'stdClass')
	{
		return mysql_fetch_object($this->result_id, $class_name);
	}

}
