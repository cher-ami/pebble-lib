<?php

namespace pebble\helpers;

use Exception;
use pebble\utils\StringUtils;
use Twig_SimpleFunction;

/**
 * //@traitUses pebble\helpers\TwigHelpers
 **/
trait TwigHelpers_utils
{
	/**
	 * Init utils function helper
	 * @return null|Twig_SimpleFunction
	 */
	protected function init_utils ()
	{
		return new Twig_SimpleFunction(
			'QuickMustache', [
				$this, 'quickMustacheFunction'
			]
		);
	}

	/**
	 * Ultra simple template engine
	 * Delimiters are double mustaches like this : {{myVar}}
	 * Compatible with nested variables !
	 * Will keep the placeholder if the property is not in $pValues
	 * @param $pTemplate : Template string to process.
	 * @param $pValues : Parameters bag including variables to replace.
	 * @return mixed : Templatised string
	 */
	public function quickMustacheFunction ($pTemplate, $pValues)
	{
		return StringUtils::quickMustache($pTemplate, $pValues);
	}
}