<?php

namespace pebble\core;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use pebble\helpers\TwigHelpers;
use Cocur\Slugify\Bridge\Twig\SlugifyExtension;
use Cocur\Slugify\Slugify;
use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Twig_Extension_Debug;

trait PebbleApp_silex
{
	// ------------------------------------------------------------------------- PROPERTIES

	/**
	 * Monolog Logger. Will log every critical application errors.
	 * Enabled only when debug mode is disabled.
	 * Not handled error will be caught and logged when debug mode is disabled.
	 * Otherwise, errors will be shown on screen.
	 * @var array
	 */
	protected $_loggers;

	/**
	 *
	 * @param int $pLevel Logger level will define logger name. Please select only from Logger::%LEVEL_NAME%.
	 * @return Logger
	 */
	public function getLogger ($pLevel)
	{
		// Convert level to lowercase name
		$levelName = Logger::getLevelName($pLevel);
		$levelName = strtolower( $levelName );

		// If this logger already exists
		if ( isset($this->_loggers[ $levelName ]) )
		{
			// Returns it
			return $this->_loggers[ $levelName ];
		}

		// Otherwise, create a new logger
		$logger = new Logger( $levelName );

		// Configure it to record file as logger name
		$streamHandler = new StreamHandler(PebbleApp::getPathTo('logs', $levelName.'.log'), $pLevel);

		// Setup custom formatter
		$streamHandler->setFormatter(
			new LineFormatter(
				"%datetime% > %level_name%\n%message%\n%context% %extra%\n\n",
				"Y-m-d H:i:s"
			)
		);

		// Push stream handler with custom line formatter
		$logger->pushHandler( $streamHandler );

		// Store it and return it
		$this->_loggers[ $levelName ] = $logger;
		return $logger;
	}


	/**
	 * The associated Silex Application.
	 * @var null|Application
	 */
	protected $_silexApp;

	/**
	 * The associated Silex Application.
	 * Can return a registerd Silex service if specified
	 * @param string $pServiceName If specified, will return silex service.
	 * @return null|mixed|Application
	 */
	public function getSilex ($pServiceName = '')
	{
		return (
			!empty($pServiceName)
			? $this->_silexApp[ $pServiceName ]
			: $this->_silexApp
		);
	}


	// ------------------------------------------------------------------------- MIDDLEWARES

	/**
	 * Init server info gathering.
	 * Will be added in config.server
	 * Will catch errors
	 */
	protected function initMiddleWares ()
	{
		// Target silex app
		$silexApp = $this->_silexApp;

		// Get debug app state
		$isDebug = $this->getConfig('app.debug');

		// Before anything on silex
		// Route aren't done yet
		$silexApp->before(function (Request $request, Application $app)
		{
			// Get infos from request
			$urlScheme = $request->getScheme();
			$httpHost = $request->getHttpHost();
			$baseURL = $request->getBaseUrl();

			// Record in config bag
			$this->_config['server'] = [
				'scheme'    => $urlScheme,
				'hostName'  => $httpHost,
				'host'      => $urlScheme.'://'.$httpHost,
				'base'      => $baseURL.'/'
			];

			// Launch init phase 2 here
			$this->init2();

		}, Application::EARLY_EVENT);

		// If we are in production mode
		if (!$isDebug)
		{
			// Catcher errors through silex middleware
			$this->_silexApp->error(function ( \Exception $e, Request $request, $code ) use ($silexApp)
			{
				// Get exception file path from web root to avoid extra long logs
				$fileName = $e->getFile();
				$explodedFileFromWebRoot = explode(PEBBLE_WEB_ROOT, $fileName);
				$fileName = (count($explodedFileFromWebRoot) == 2 ? $explodedFileFromWebRoot[1] : $fileName);

				// Get critical logger
				$this->getLogger( Logger::CRITICAL )->critical(
					// And add critical message from error
					$fileName.'('.$e->getLine().') > '.$e->getCode().':'.$e->getMessage()
				);

			}, Application::EARLY_EVENT);
		}
	}


	// ------------------------------------------------------------------------- SERVICES

	/**
	 * Register Silex services
	 */
	protected function registerServices ()
	{
		// Get debug app state
		$isDebug = $this->getConfig('app.debug');

		// Target silex app
		$silexApp = $this->_silexApp;

		// Register twig and configure it
		$silexApp->register(new TwigServiceProvider(), [
			// Set path to views
			'twig.path'			=> $this->getPathTo('view'),
			'twig.options'		=> [
				'debug' => $isDebug,
				// Enable twig cache if we are not in debug mode
				'cache' => ( $isDebug ? false : $this->getPathTo('cache') )
			]
		]);

		// Activate twig debug extension if needed
		$isDebug && $silexApp['twig']->addExtension(
			new Twig_Extension_Debug()
		);

		// Add slug extension for reverse routing
		$silexApp['slugify'] = Slugify::create();
		$silexApp['twig']->addExtension(
			new SlugifyExtension( $silexApp['slugify'] )
		);

		// Add Pebble twig helpers
		$silexApp['twig']->addExtension(
			new TwigHelpers( $this )
		);

		// Call middleware on appController to init custom services for app
		$this->callAppControllerMiddleWare('registerServices', [
			$this,
			$this->getSilex()
		]);
	}


	// ------------------------------------------------------------------------- RUN

	/**
	 * Run the app through Silex
	 */
	function run ()
	{
		$this->_silexApp->run();
	}
}