<?php

namespace pebble\helpers;

use Exception;
use Twig_SimpleFunction;

/**
 * FIXME : Faire marcher ce truc pour l'auto-completion de getConfig etc
 * //@traitUses pebble\helpers\TwigHelpers
 **/
trait TwigHelpers_asset
{
	/**
	 * Loaded assets config.
	 * We store to limit access to method getConfig.
	 * @var null|array
	 */
	protected $_assetsConfig;

	/**
	 * App version
	 * We store to limit access to method getConfig.
	 * @var string
	 */
	protected $_appVersion;

	/**
	 * Init asset function helper
	 * @return null|Twig_SimpleFunction
	 */
	protected function init_asset ()
	{
		// Load assets config
		$this->_assetsConfig = $this->_app->getConfig('assets');

		// Get app version
		$this->_appVersion = $this->_app->getConfig('app.version');

		// If we have assets config, create helper
		return (
			!is_null($this->_assetsConfig)
			? new Twig_SimpleFunction('asset', [$this, 'assetFunction'])
			: null
		);
	}

	/**
	 * Asset function helper for twig.
	 * Will create path to asset from assets configs file and fileName argument.
	 * @param string $fileName File we need to target
	 * @param string $assetType Type of asset this file is
	 * @param bool $pVersion If we need to include version cache busting suffix.
	 * @return string The computed file name with base and cache busting suffix if needed
	 * @throws Exception If asset type not found in assets config file
	 */
	public function assetFunction ($fileName, $assetType, $pVersion = true)
	{
		// Check if this asset type exists
		if ( !(isset($this->_assetsConfig[$assetType])) )
		{
			throw new Exception("TwigHelpers_asset.assetFunction // Asset type `$assetType` does not exists in assets config file.");
		}

		// Compute suffix from arguments
		$versionSuffix = (
			$pVersion
			? '?'.$this->_appVersion
			: ''
		);

		// Return with base, fileName and suffix
		return $this->_assetsConfig[$assetType].$fileName.$versionSuffix;
	}
}