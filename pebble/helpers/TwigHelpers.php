<?php

namespace pebble\helpers;

use pebble\core\PebbleApp;
use Twig_Extension;

class TwigHelpers extends Twig_Extension
{
	/**
	 * @var PebbleApp
	 */
	protected $_app;

	/**
	 * Constructor.
	 * @codeCoverageIgnore
	 * @param PebbleApp $app
	 */
	public function __construct (PebbleApp $app)
	{
		$this->_app = $app;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName()
	{
		return 'twig_helper';
	}

	// ------------------------------------------------------------------------- TRAITS

	// Load all traits
	use TwigHelpers_asset;
	use TwigHelpers_utils;

	/**
	 * {@inheritDoc}
	 */
	public function getFunctions ()
	{
		// Init all loaded traits
		return [
			$this->init_asset(),
			$this->init_utils()
		];
	}
}