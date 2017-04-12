<?php

namespace pebble\core;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait PebbleApp_routing
{
	// ------------------------------------------------------------------------- PROPERTIES

	/**
	 * Current route matched with URL.
	 * Will have properties from the matched route from routes.yml
	 */
	public function getCurrentRoute () { return $this->_currentRoute; }
	protected $_currentRoute;

	/**
	 * Current route name matched with URL.
	 * Will be the key of the matched route from routes.yml
	 * @var string
	 */
	public function getCurrentRouteName () { return $this->_currentRouteName; }
	protected $_currentRouteName;


	// ------------------------------------------------------------------------- INIT

	/**
	 * Init application routing from config.
	 * @throws Exception
	 */
	protected function initRouting ()
	{
		// Get routes from config
		$routesList = $this->getConfig('routes');

		// Register error handling
		// FIXME : Voir pourquoi l'autocomplete pine ici alors que ça marche ?
		$this->_silexApp->error(
			function (Exception $e, Request $request) use ($routesList)
			{
				// Not found
				if ($e instanceof NotFoundHttpException)
				{
					return $this->routeHandler( $request, 'notFound', $e );
				}

				// Fatal and not debugging
				else if ( !$this->_silexApp['debug'] )
				{
					return $this->routeHandler( $request, 'fatal', $e );
				}
			}
		);

		// If we have no routes, do not go further
		if ( is_null($routesList) ) return;

		// FIXME : Voir le routage du site LHS
		// FIXME : Ca parrait plus simple avec ->match
		// FIXME : Mais il faudrait garder ce système de controlleurs

		// Browse routes
		foreach ($routesList as $routeName => $routeConfig)
		{
			// Skip notFound and fatal error handling
			if ($routeName == 'notFound' || $routeName == 'fatal') continue;

			// Check if route is valid
			if  (
				!isset($routeConfig['url'])
				||
				(
					!isset($routeConfig['action'])
					&&
					!isset($routeConfig['view'])
				)
			)
			{
				throw new Exception("PebbleApp.initRouting // Invalid route configuration for $routeName. Needs 'url' and 'action' fields.");
			}

			// Get method from config
			// Default is get
			$method = (
				isset( $routeConfig['method'] )
				? strtolower( $routeConfig['method'] )
				: 'get'
			);

			// Create scoped route handler to have routeName
			$routeHandler = function (Request $request = null) use ($routeName)
			{
				return $this->routeHandler( $request, $routeName );
			};

			// GET
			if ($method == 'get')
			{
				// Setup route
				$this->_silexApp->get( $routeConfig['url'], $routeHandler );
			}

			// POST
			else if ($method == 'post')
			{
				// Setup route
				$this->_silexApp->post( $routeConfig['url'], $routeHandler );
			}

			// Unknown method
			else throw new Exception("PebbleApp.initRouting // Invalid routing method `$method` for route `$routeName`. Please see routes.yml config file.");
		}
	}


	// ------------------------------------------------------------------------- HANDLERS

	/**
	 * Called when a route is caught.
	 * @param Request|null $request The associated request which triggered the route.
	 * @param string $pRouteName The route name from the routes config.
	 * @param Exception $exception Triggering exception.
	 * @return mixed
	 * @throws Exception If route not found but it should be ok..
	 */
	protected function routeHandler (Request $request = null, $pRouteName = '', $exception = null)
	{
		// Get route list
		$routeList = $this->getConfig('routes');

		// Target route from name
		$targetRoute = $routeList[ $pRouteName ];

		// Save current route
		$this->_currentRoute = $targetRoute;
		$this->_currentRouteName = $pRouteName;

		// Check if route exists, anyway we should never fall in this.
		if ( !isset($targetRoute) )
		{
			throw new Exception("PebbleApp.routeHandler // Invalid route `$pRouteName`, not found in routes config.");
		}

		// Launch init phase 3
		$this->init3();

		// If we have an action
		if ( isset($targetRoute['action']) && !empty($targetRoute['action']) )
		{
			// And call action
			return $this->callAction(
				$targetRoute['action'],
				(
					is_null($exception)
					? [ $this, $request ]
					: [ $this, $request, $exception ]
				)
			);
		}

		// If we have a view without controller
		else if ( isset($targetRoute['view']) && !empty($targetRoute['view']) )
		{
			// And call action
			return $this->view(
				$targetRoute['view'],
				$request
			);
		}

		// No route and no action, invalid route
		else throw new Exception('PebbleApp.routeHandler // Invalid route (no view and no action).');
	}


	// ------------------------------------------------------------------------- HELPERS

	// TODO : Continuer le routage inverse

	public function routeToAction ($pRouteName = '')
	{
		// Get route list
		$routeList = $this->getConfig('routes');

		// Check if route exists, anyway we should never fall in this.
		if ( !isset($routeList[$pRouteName]) )
		{
			throw new Exception("PebbleApp.routeToAction // Invalid route `$pRouteName`, not found in routes config.");
		}

		// TODO : Voir ce service : UrlGeneratorServiceProvider

		throw new Exception('TODO');
	}

}