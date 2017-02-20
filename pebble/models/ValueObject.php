<?php

namespace pebble\models;

/**
 * Class ValueObject
 * Usage :
 * - Sxtends this class to create any new value object.
 * - Declare, inside your class, every props the value object have on the database.
 * - Use, only for value objects, snake_case naming convention for all database properties.
 * - Integrate validation declaration inside your class.
 * @package pebble\models
 */
class ValueObject
{
	// ------------------------------------------------------------------------- PROPERTIES

	/**
	 * Allow to inject undeclared properties
	 */
	public function __set ($key, $value) {}


	// ------------------------------------------------------------------------- INIT

	/**
	 * Default ValueObject constructor.
	 * Will inject every props of $pData into this object through feedFromArray().
	 * @param null $pData Will inject every properties into this object through feedFromArray().
	 */
	public function __construct ($pData = null)
	{
		if ( !is_null($pData) )
		{
			$this->fromArray( $pData );
		}
	}

	/**
	 * Will inject every props of $pData into this object.
	 * @param array $pData : Will inject every props of $pData into this object.
	 */
	public function fromArray ($pData = [])
	{
		foreach ($pData as $key => $value)
		{
			$this->$key = $value;
		}
	}

	/**
	 * Get this object as an array.
	 * Usefull with DatabaseConnector.
	 * @return array Object properties with key and values and as associative array.
	 */
	public function toArray ()
	{
		return get_object_vars( $this );
	}


	// TODO : Include validator helper strategy pattern inside framework
}