<?php

namespace pebble\utils;

class ArrayUtils
{
	/**
	 * Traverse an array from a path as a string.
	 * Ex : $pPath = 'my.nested.array' will traverse your array and get the value of 'array' inside 'nested' inside 'my' inside $pObject
	 * @param $pPath : The path
	 * @param $pObject : The associative array to traverse
	 * @return : value if found, else null
	 */
	static function traverse ($pPath, $pObject)
	{
		// Check if our object is null
		if (is_null($pObject)) return null;

		// Split the first part of the path
		$explodedPath = explode('.', $pPath, 2);

		// One element in path selector
		if (!isset($explodedPath[1]))
		{
			// Check if this element exists and return it if found
			return isset($pObject[$explodedPath[0]]) ? $pObject[$explodedPath[0]] : null;
		}

		// Nesting detected in path
		// Check if first part of the path is in object
		else if (isset($explodedPath[0]) && isset($pObject[$explodedPath[0]]))
		{
			// Target child from first part of path and traverse recursively
			return ArrayUtils::traverse($explodedPath[1], $pObject[$explodedPath[0]]);
		}

		// Not found
		else return null;
	}

	/**
	 * Will set values of $b inside $a.
	 * References are used, no need to use returned object.
	 * Be careful, $a will be modified
	 * @param $a : Will be modified by reference !
	 * @param $b : Props of this array will be copied to $a;
	 * @return mixed : References are used, no need to use returned object.
	 */
	static function extendsArray (&$a, &$b)
	{
		// Browser array
		foreach ($b as $key => &$value)
		{
			// If it's an array
			if (is_array($value))
			{
				// Recursive remplace
				$a[$key] = ArrayUtils::extendsArray($a[$key], $value);
			}

			// Replace value
			else
			{
				$a[$key] = $value;
			}
		}

		// Return
		return $a;
	}
}
