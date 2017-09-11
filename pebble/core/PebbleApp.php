<?php


namespace pebble\core;

use Exception;
use Silex\Application;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;


// ----------------------------------------------------------------------------- MANDATORY CONSTANTS

// Target pebble app directory and define it
if (!defined('PEBBLE_APP_BASE'))
{
	define('PEBBLE_APP_BASE', realpath(__DIR__ . '/../pebble-lib'));
}

// Check app directory validity
if (empty(PEBBLE_APP_BASE) ||!is_dir(PEBBLE_APP_BASE))
{
	die('Invalid app directory');
}


class PebbleApp
{
	// ------------------------------------------------------------------------- SINGLETON

	/**
	 * @var PebbleApp
	 */
	static protected $__instance;

	/**
	 * Get PebbleApp instance.
	 * If you want to inject a specific instance of SilexApp, please use constructor, once.
	 * @return PebbleApp
	 */
	static function getInstance ()
	{
		return self::$__instance;
	}


	// ------------------------------------------------------------------------- PATHS

	/**
	 * Paths to application elements.
	 * @var array
	 */
	const PATH = [
		// Application files
		'config'        => 'configs/',
		'controller'    => 'controllers/',
		'model'         => 'models/',
		'view'          => 'views/',

		// Generated files
		'temp'          => 'temp/',
		'cache'         => 'temp/cache/',
		'logs'          => 'temp/logs/'
	];

	/**
	 * Compute full path to app folder.
	 * @see constant PebbleApp::PATH.
	 * @param string $pPathName Key of the PATH to compute.
	 * @param string $pFilePath Will be added after path if specified.
	 * @return string Computed full folder path
	 * @throws Exception If key not found
	 */
	static function getPathTo ($pPathName, $pFilePath = '')
	{
		// Check if path name it exists
		if ( !array_key_exists($pPathName, self::PATH) )
		{
			throw new Exception("PebbleApp::getPathTo // `$pPathName` path key doesn't exists. See PebbleApp::PATH constant keys.");
		}

		// Return with base and file path
		return PEBBLE_APP_BASE.(self::PATH[$pPathName]).$pFilePath;
	}


	// ------------------------------------------------------------------------- YML HELPER

	/**
	 * Load and parse a config file.
	 * Can be YML file or JSON file.
	 *
	 * To load a JSON file, set $pFlags at -1.
	 *
	 * To load a TXT file, set $pFlags at -2.
	 *
	 * Parsed file content will be returned as an array.
	 * @param string $pFilePath Full path to the file. @use PebbleApp::getPathTo to compute path.
	 * @param int $pFlags Parsing flags, @see Yaml class constants. -1 to load a JSON.
	 * @param bool $pCheckFileExists If true, will throw Exception if file not found. If false and file not found, will return an empty array.
	 * @return mixed Parsed data from yml file as array
	 * @throws Exception If file not found and $pCheckFileExists is true.
	 * @throws ParseException from Yaml::parse method.
	 */
	static function loadConfigFile ($pFilePath, $pFlags = 0, $pCheckFileExists = true)
	{
		// Throw an exception if this file does not exists
		if ( !file_exists($pFilePath) )
		{
			// If we have to check if the file exists, we throw the error
			if ($pCheckFileExists)
			{
				throw new Exception("PebbleApp::loadConfigFile // File `$pFilePath` not found or not readable.");
			}

			// Else we just return a void array
			else return [];
		}

		// Load file content as string
		$configContent = file_get_contents( $pFilePath );

		// Parse JSON file
		if ($pFlags == -1)
		{
			// BETTER : Configurable flags
			$parsedContent = json_decode( $configContent, true );
		}

		// TXT File
		else if ($pFlags == -2)
		{
			$parsedContent = $configContent;
		}

		// YML File
		else
		{
			$parsedContent = Yaml::parse( $configContent, $pFlags );
		}

		// Return parsed content and return empty array if no data to avoid apocalypse
		return is_null($parsedContent) ? [] : $parsedContent;
	}


	// ------------------------------------------------------------------------- CONSTRUCT

	/**
	 * PebbleApp constructor.
	 * This class compose the silex app.
	 * @param null $pSilexApplication
	 * @throws Exception if singleton misused.
	 */
	function __construct ($pSilexApplication = null)
	{
		// Manage singleton
		if ( is_null(self::$__instance) )
		{
			self::$__instance = $this;
		}
		else
		{
			throw new Exception('PebbleApp.__construct // PebbleApp is a singleton. Only one instance can be constructed.');
		}

		// Record link to silex app or create one
		$this->_silexApp = (
			is_null($pSilexApplication)
			? new Application()
			: $pSilexApplication
		);

		// Prepare initFromTile sequence
		$this->init1();
	}


	// ------------------------------------------------------------------------- INIT

	/**
	 * Initialisation phase 1.
	 * Triggered just after construction.
	 * Here Silex isn't started.
	 * - Init app controller if available
	 * - Loading raw configs
	 * - Init routing from config
	 * - Initialising Silex middleware
	 */
	protected function init1 ()
	{
		// Init app controller if available
		$this->initAppController();

		// Load application configs
		$this->loadConfigs();

		// Init routes
		$this->initRouting();

		// Init server
		$this->initMiddleWares();
	}

	/**
	 * Initialisation phase 2
	 * Triggered by Silex early before middleware.
	 * We are after the run method.
	 * Here, we have the request object.
	 * - Process templating on all config
	 * - Register services
	 * - Init custom app dependencies
	 */
	protected function init2 ()
	{
		// Process templating on all config
		$this->processTemplatingOnConfig();

		// Register Silex and Symfony services
		$this->registerServices();

		// Init app dependencies through custom AppController
		$this->callAppControllerMiddleWare('initAppDependencies', [ $this ]);
	}

	/**
	 * Initialisation phase 3
	 * We are after the route matching method.
	 * Here we have route and page information.
	 * - Inject dictionary for current page
	 */
	protected function init3 ()
	{
		// Setup dictionary for current page
		$this->injectCurrentPageDictionary( $this->getCurrentRouteName() );
	}


	// ------------------------------------------------------------------------- TRAITS

	/**
	 * Config loading and processing
	 */
	use PebbleApp_config;

	/**
	 * Routing management
	 */
	use PebbleApp_routing;

	/**
	 * Silex integration
	 */
	use PebbleApp_silex;

	/**
	 * MVC management
	 */
	use PebbleApp_mvc;
}