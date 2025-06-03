<?php namespace Kodhe\Pulen\Framework\Loader;


class Database
{

  	public static function database($params = '', $return = FALSE, $query_builder = NULL)
  	{
  		// Do we even need to load the database class?
  		if ($return === FALSE && isset(kodhe()->db) && is_object(kodhe()->db) && ! empty(kodhe()->db->conn_id))
  		{
  			return FALSE;
  		}

  		if ($return === TRUE)
  		{
  			return \Kodhe\Pulen\Database\Database::DB($params, $query_builder);
  		}

      if(kodhe()->has('db')) return;
  		// Load the DB class
  		kodhe()->set('db', \Kodhe\Pulen\Database\Database::DB($params, $query_builder));

  	}

  	public static function dbutil($db = NULL, $return = FALSE)
  	{

  		if ( ! is_object($db) OR ! ($db instanceof \Kodhe\Pulen\Database\DB))
  		{
  			class_exists('Kodhe\Pulen\Database\DB', FALSE) OR self::database();
  			$db =& kodhe()->db;
  		}

      $class = 'Kodhe\Pulen\Database\Driver\Drivers\\'.ucwords($this->dbdriver).'\Utility';

  		if ($return === TRUE)
  		{
  			return new $class($db);
  		}

      if(kodhe()->has('dbutil')) return;
  		kodhe()->set('dbutil', new $class($db));

  	}

  	public static function dbforge($db = NULL, $return = FALSE)
  	{
  		if ( ! is_object($db) OR ! ($db instanceof \Kodhe\Pulen\Database\DB))
  		{
  			class_exists('Kodhe\Pulen\Database\DB', FALSE) OR self::database();
  			$db =& kodhe()->db;
  		}

  		if ( ! empty($db->subdriver))
  		{
        $class = 'Kodhe\Pulen\Database\Driver\Drivers\\'.ucwords($db->dbdriver).'\Subdrivers\\'.ucwords($db->subdriver).'\Forge';
  		}
  		else
  		{
        $class = 'Kodhe\Pulen\Database\Driver\Drivers\\'.ucwords($db->dbdriver).'\Forge';
  		}

  		if ($return === TRUE)
  		{
  			return new $class($db);
  		}

      if(kodhe()->has('dbforge')) return;
  		kodhe()->set('dbforge', new $class($db));
  	}
}
