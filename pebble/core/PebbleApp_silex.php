<?php

namespace pebble\core;

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

		// Before anything on silex but JUST AFTER LOL
		/*
		$this->_silexApp->boot(function ()
		{
			// Get params
			//$getParams = $request->query->all();
			// FIXME : Check redirection here (mobile or anything)
			// TODO : Créer un middleWare pratique pour la détéction mobile et autre
			// TODO : Voir l'utilité du truc
		});
		*/

		// If we are in production mode
		if (!$isDebug)
		{
			// Catch all errors
			//ErrorHandler::register();

			// Catcher errors through silex middleware
			$this->_silexApp->error(function ( \Exception $e, Request $request, $code ) use ($silexApp)
			{
				$error = "---- ".date('D j M Y @ G:i:s').' - '.$code;
				$error .= "\n".$e->getFile().' on line '.$e->getLine();
				$error .= "\n".$e->getCode().' > '.$e->getMessage();
				$error .= "\n";

				//$silexApp['logger']->error($error);
				$this->_logger->addCritical($error);

			}, Application::EARLY_EVENT);
		}
	}


	// ------------------------------------------------------------------------- SERVICES

	/**
	 * TODO : Replacer
	 * @var Logger
	 */
	protected $_logger;

	/**
	 * Register Silex services
	 */
	protected function registerServices ()
	{
		// Get debug app state
		$isDebug = $this->getConfig('app.debug');

		// Target silex app
		$silexApp = $this->_silexApp;

		// Init monolog first so we can catch errors
		$this->_logger = new Logger('pebble');
		$this->_logger->pushHandler(new StreamHandler(PebbleApp::getPathTo('logs', 'production.log'), Logger::CRITICAL));
		$this->_logger->pushHandler(new StreamHandler(PebbleApp::getPathTo('logs', 'debug.log'), Logger::DEBUG));
		$this->_logger->pushHandler(new StreamHandler(PebbleApp::getPathTo('logs', 'info.log'), Logger::INFO));

		// Register twig and configure it
		$silexApp->register(new TwigServiceProvider(), [
			// Set path to views
			'twig.path'			=> $this->getPathTo('view'),
			'twig.options'		=> [
				'debug' => $isDebug
			],

			// Enable twig cache if we are not in debug mode
			'cache' => (
				$isDebug
				? false
				: $this->getPathTo('temp', 'cache')
			)
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