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
		$this->loadConfig('app', $this->_config, false);

		// Set debug mode from config on silex app
		$this->_silexApp['debug'] = $this->_config['app']['debug'];

		// Browser and load all configs recursively from configs folder
		$this->browseFolderAndLoadConfigs(
			'',
			$this->_config
		);
	}

	/**
	 * Browse a config folder and load every config file found.
	 * Config file type are YML / JSON / TXT.
	 * Will store parsed data info $pConfigNode as reference.
	 * Runs recursively with folders.
	 * @param string $pFolder Folder path to browse from config folder.
	 * @param array $pConfigNode Reference of the config array where parsed configs will be pushed.
	 */
	protected function browseFolderAndLoadConfigs ($pFolder, &$pConfigNode)
	{
		// Browse config folder
		$configFolder = scandir( PebbleApp::getPathTo('config').$pFolder );
		foreach ($configFolder as $configFileName)
		{
			// Do not load app twice, its loaded in $this->loadConfigs()
			if ($configFileName == 'app.yml') continue;

			// Get file name and extension
			$fileExtension = pathinfo($configFileName, PATHINFO_EXTENSION);
			$fileName = pathinfo($configFileName, PATHINFO_FILENAME);

			// If this is a sub-folder
			if ($fileExtension == '' && $fileName != '' && $fileName != '.')
			{
				// Create the sub-node from the directory name
				$pConfigNode[ $fileName ] = [];

				// Parse this sub-folder recursively and load into this new sub-node
				$this->browseFolderAndLoadConfigs(
					$pFolder.'/'.$fileName.'/',
					$pConfigNode[ $fileName ]
				);
			}

			// If this is a YML or JSON file
			else if ($fileExtension == 'yml' || $fileExtension == 'json' || $fileExtension == 'txt')
			{
				$this->loadConfig(
					$pFolder.$fileName,
					$pConfigNode,
					true,
					$fileExtension
				);
			}
		}
	}

	/**
	 * Load a specific config file from the config folder.
	 * Only file name (no folders) will be used to inject into $pConfigNode.
	 * $pConfigNode need to target good depth, $pConfigName will not be used to target that depth.
	 *
	 * EX :
	 * - if $pConfigName is 'dictionary/generics'
	 * - $pConfigNode needs to target $this->_config['dictionary']
	 * - otherwise generics.yml props will be included into $this->_config
	 *
	 * @param string $pConfigName YML or JSON Config file name (no trailing slash, no extension), from config folder.
	 * @param array $pConfigNode Config node reference where to store loaded config.
	 * @param bool $pParseEnvs If we have to parse env first level.
	 * @param string $pFileType 'yml' to load a yml file. 'json' to load a json file.
	 * @throws Exception
	 */
	protected function loadConfig ($pConfigName, &$pConfigNode, $pParseEnvs = true, $pFileType = 'yml')
	{
		// Compute file path and name
		$filePath = PebbleApp::getPathTo('config', $pConfigName.'.'.$pFileType);
		$fileName = pathinfo($filePath, PATHINFO_FILENAME);

		// Load config file
		try
		{
			if ($pFileType == 'yml')
			{
				$configObject = self::loadConfigFile( $filePath, 0 );
			}
			else if ($pFileType == 'json')
			{
				$configObject = self::loadConfigFile( $filePath, -1 );
			}
			else if ($pFileType == 'txt')
			{
				$configObject = self::loadConfigFile( $filePath, -2 );
			}
			else
			{
				throw new Exception("PebbleApp.loadConfig // Invalid config file type `$pFileType`.");
			}
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
				// If this is a string, we are in clone mode
				if (is_string($configObject[ $currentEnvName ]))
				{
					// The name of the env to clone is the value of current env
					// A bit difficult but, in yml :
					// production : "staging"
					// will load "staging" properties for "production" env
					$envToClone = $configObject[ $currentEnvName ];

					// If env to clone does not exists, config is invalid
					if (!isset($configObject[ $envToClone ]))
					{
						throw new Exception("PebbleApp.loadConfig // Config file `$pConfigName` not valid. Environment named `$envToClone` does not exists. Please check if this config file is valid and have environment nodes on first level.");
					}

					// Append to the default one
					ArrayUtils::extendsArray($envConfigContent, $configObject[ $envToClone ]);
				}
				else
				{
					// Append to the default one
					ArrayUtils::extendsArray($envConfigContent, $configObject[ $currentEnvName ]);
				}
			}

			// Override config content with env parsed one
			$configObject = $envConfigContent;
		}

		// Record config content from its name
		$pConfigNode[ $fileName ] = $configObject;
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
	 * Inject dictionary page data into dictionary "currentPage" node from page route name.
	 * This node will be available at dictionary's first level.
	 * Dictionary page data can be overridden.
	 * Associative array will be overridden with ArrayUtils.
	 * @see ArrayUtils::extendsArray
	 * @param string $pRouteName Route name opening the page we want to target.
	 * @param array $pOverride (optional) override data over dictionary page data.
	 */
	public function injectCurrentPageDictionary ($pRouteName, $pOverride = [])
	{
		// If dictionary exists as a config
		// And if we have a route match
		if ( !is_null($this->_config['dictionary']) && !empty($pRouteName) )
		{
			// Get dictionary from config - as reference -
			$dictionary = &$this->_config['dictionary'];

			// Shorcut currentPage property inside dictionary
			$currentPage = (
				// If this page exists in dictionary
				isset($dictionary['pages'][$pRouteName])
				? $dictionary['pages'][$pRouteName]

				// Empty array by default to avoid errors
				: []
			);

			// Override on top of currentPage as reference
			ArrayUtils::extendsArray($dictionary['currentPage'], $pOverride);

			// Inject data into dictionary
			$this->injectDictionary([
				'currentPage' => $currentPage
			]);
		}
	}

	/**
	 * Inject data into dictionary.
	 * Associative array will be overridden with ArrayUtils.
	 * @see ArrayUtils::extendsArray
	 * @param array $pDataToInject Data to override.
	 */
	public function injectDictionary ($pDataToInject)
	{
		// If dictionary exists as a config
		// And if we have a route match
		if ( !is_null($this->_config['dictionary']) )
		{
			// Get dictionary from config - as reference -
			$dictionary = &$this->_config['dictionary'];

			// Inject and override data into dictionary
			ArrayUtils::extendsArray($dictionary, $pDataToInject);
		}
	}
}