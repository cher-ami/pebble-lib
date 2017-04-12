<?php

namespace pebble\core;

use pebble\utils\ArrayUtils;
use pebble\utils\StringUtils;
use Exception;
use Symfony\Component\Yaml\Exception\ParseException;

trait PebbleApp_config
{
	// ------------------------------------------------------------------------- PROPERTIES

	/**
	 * All loaded configs
	 * @var array
	 */
	protected $_config = [];

	/**
	 * Get a config prop from its path.
	 * For example :
	 * - app version : getConfig('app.version')
	 * - database infos : getConfig('database')
	 * - database password : getConfig('database.password')
	 * If no config path is given, the whole config will be returned.
	 * @param string $pConfigPath Path to config (see method signature). If empty, will return root config array.
	 * @return array|mixed
	 */
	public function getConfig ($pConfigPath = '')
	{
		return (
			empty($pConfigPath)
			? $this->_config
			: ArrayUtils::traverse($pConfigPath, $this->_config)
		);
	}


	// ------------------------------------------------------------------------- LOAD

	/**
	 * Start config loadings
	 */
	protected function loadConfigs ()
	{
		// First of all, load app config to get env etc
		$this->loadConfig('app', false);

		// Set debug mode from config on silex app
		$this->_silexApp['debug'] = $this->_config['app']['debug'];

		// Browse config folder
		$configFolder = scandir( PebbleApp::getPathTo('config') );
		foreach ($configFolder as $configFileName)
		{
			// Do not load app twice and load only yml files
			if ($configFileName != 'app.yml' && pathinfo($configFileName, PATHINFO_EXTENSION) == 'yml')
			{
				$this->loadConfig(
					pathinfo($configFileName, PATHINFO_FILENAME)
				);
			}
		}
	}

	/**
	 * Load a specific config file from the config folder.
	 * @param string $pConfigName YML Config file name (no slash, no extension)
	 * @param bool $pParseEnvs If we have to parse env first level.
	 * @throws Exception
	 */
	protected function loadConfig ($pConfigName, $pParseEnvs = true)
	{
		// Compute file name
		$filePath = PebbleApp::getPathTo('config', $pConfigName.'.yml');

		// Load YML file
		try
		{
			$configObject = self::loadYMLFile( $filePath );
		}
		catch (ParseException $e)
		{
			throw new Exception("PebbleApp.loadConfig // Parse error on config file `$pConfigName` at `$filePath`");
		}
		catch (Exception $e)
		{
			throw new Exception("PebbleApp.loadConfig // Config file `$pConfigName` not found at `$filePath`");
		}

		// If we have to parse config with env
		if ($pParseEnvs)
		{
			// Get default config
			// If there is not default config in file, create empty array
			$envConfigContent = (
			isset($configObject['default'])
				? $configObject['default']
				: []
			);

			// Get current env name
			$currentEnvName = $this->_config['app']['env'];

			// If we have a specific env configuration
			if (isset($configObject[ $currentEnvName ]))
			{
				// Append to the default one
				ArrayUtils::extendsArray($envConfigContent, $configObject[ $currentEnvName ]);
			}

			// Override config content with env parsed one
			$configObject = $envConfigContent;
		}

		// Record config content from its name
		$this->_config[ $pConfigName ] = $configObject;
	}


	// ------------------------------------------------------------------------- TEMPLATE PROCESSING

	/**
	 * Process templates on $this->_config.
	 */
	protected function processTemplatingOnConfig ()
	{
		// Parse templating on config
		foreach ($this->_config as $key => &$configElement)
		{
			$this->processTemplatingOnConfigObject( $configElement );
		}
	}

	/**
	 * Process templates on a specific config node, recursively.
	 * Uses references, no return.
	 * @param array $pConfig Config array to alter as reference.
	 */
	protected function processTemplatingOnConfigObject (&$pConfig)
	{
		// Browse and keep reference
		foreach ($pConfig as &$configValue)
		{
			// If nested associative array
			if (is_array($configValue))
			{
				// Process recursively
				$this->processTemplatingOnConfigObject($configValue);
			}

			// Parse only strings to avoid accidental casting
			else if (is_string($configValue))
			{
				// Process to templating with config as properties and override reference
				$configValue = StringUtils::quickMustache($configValue, $this->_config);
			}
		}
	}

	/**
	 * Setup dictionary config.
	 * Will add currentPage property inside dictionary config.
	 * Need route to be matched so it will work from init phase 3 only.
	 */
	protected function setupDictionary ()
	{
		// Get current matched route name
		$routeName = $this->getCurrentRouteName();

		// If dictionary exists as a config
		// And if we have a route match
		if ( !is_null($this->_config['dictionary']) && !empty($routeName) )
		{
			// Get dictionary from config - as reference -
			$dictionary = &$this->_config['dictionary'];

			// Shorcut currentPage property inside dictionary
			$dictionary['currentPage'] = (
				// If this page exists in dictionary
				isset($dictionary['pages'][$routeName])
				? $dictionary['pages'][$routeName]

				// Empty array by default to avoid errors
				: []
			);
		}
	}
}