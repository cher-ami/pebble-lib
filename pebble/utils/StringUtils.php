<?php

namespace pebble\utils;

class StringUtils
{
	/**
	 * Ultra simple template engine
	 * Delimiters are double mustaches like this : {{myVar}}
	 * Compatible with nested variables !
	 * Will keep the placeholder if the property is not in $pValues
	 * @param $pTemplate : Template string to process.
	 * @param $pValues : Parameters bag including variables to replace.
	 * @return mixed : Templatised string
	 */
	static function quickMustache ($pTemplate, $pValues)
	{
		return preg_replace_callback(
			'/{{([a-zA-Z0-9\.?]+)}}/',
			function ($matches) use ($pValues)
			{
				// Traverse the parameters bag with this path
				$traversedValue = ArrayUtils::traverse($matches[1], $pValues);

				// Return the value if found, else keep the placeholder
				return is_null($traversedValue) ? $matches[0] : $traversedValue;
			},
			$pTemplate
		);
	}
}
