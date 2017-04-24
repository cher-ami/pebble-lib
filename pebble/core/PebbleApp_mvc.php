<?php

namespace pebble\core;

use controllers\AppController;
use pebble\utils\ArrayUtils;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait PebbleApp_mvc
{
	// ------------------------------------------------------------------------- APP CONTROLLER

	/**
	 * @var AppController
	 */
	protected $_appController;

	/**
	 * Init app controller if it exists
	 */
	public function initAppController ()
	{
		// Check if our class exists
		$controllerClassName = 'controllers\\AppController';

		// If app controller class exists
		if ( class_exists($controllerClassName) )
		{
			// Create instance from class name
			$this->_appController = new $controllerClassName( $this );
		}
	}

	/**
	 * Will try to call a middleware on the app controller.
	 * Will fail silently and return null if app controller does not exists
	 * Or if middleware on app controller does not exists.
	 * @param string $pMethodName Name of the middleware to call
	 * @param array $pParams Params to send to the middleWare
	 * @return mixed|null App controller middleware result if callable
	 */
	public function callAppControllerMiddleWare ($pMethodName, $pParams)
	{
		// If app controller exists
		// And if this method exists on app controller
		if ( !is_null($this->_appController) && method_exists($this->_appController, $pMethodName) )
		{
			return call_user_func_array(
				[$this->_appController, $pMethodName],
				$pParams
			);
		}

		// No controller or no middleware, return null
		else return null;
	}


	// ------------------------------------------------------------------------- CONTROLLERS

	/**
	 * List of created controllers.
	 * @var array
	 */
	protected $_controllerInstances = [];

	/**
	 * Call an action on a controller.
	 * Will store controller in controller cache to avoid multiple instances of the same controller.
	 * FIXME : Faire une option pour avoir des controleurs multiton ? Une propriété statique singleton à false ou un truc du genre?
	 * @param string $pFullActionName Full action name including controller and action in this format : MyController.myAction
	 * @param array $pParams Action given arguments
	 * @return mixed Results of the action call
	 * @throws Exception If controller or action is not found.
	 */
	public function callAction ($pFullActionName = '', $pParams = [])
	{
		// Separate controller name from action
		$explodedFullAction = explode('.', $pFullActionName);

		// Check if full action name is valid
		if (count($explodedFullAction) != 2)
		{
			throw new Exception("PebbleApp.callAction // Invalid action name `$pFullActionName`. Expected format MyController.myAction");
		}

		// Get controller name and action name from full action
		$controllerName = $explodedFullAction[0];
		$actionName = $explodedFullAction[1];

		// Check if controller is already created
		if ( isset($this->_controllerInstances[ $controllerName ]) )
		{
			// Retrieve controller from created controllers cache
			$controllerInstance = $this->_controllerInstances[ $controllerName ];
		}
		else
		{
			// Check if our class exists
			$controllerClassName = 'controllers\\'.$controllerName;
			if ( !class_exists($controllerClassName) )
			{
				throw new Exception("PebbleApp.callAction // Class `$controllerName` not found.");
			}

			// Create instance from class name
			$controllerInstance = new $controllerClassName( $this );
		}

		// Store controller instance by its name
		$this->_controllerInstances[ $controllerName ] = $controllerInstance;

		// Check if this action exists on this controller instance
		if ( !method_exists($controllerInstance, $actionName) )
		{
			throw new Exception("PebbleApp.callAction // Action `$actionName` not found in controller `$controllerName`.");
		}

		// Call action on controller instance
		return call_user_func_array(
			[$controllerInstance, $actionName],
			$pParams
		);
	}


	// ------------------------------------------------------------------------- VIEWS

	/**
	 * Injected vars for current view.
	 * @var array
	 */
	protected $_viewVars = [];
	public function getViewVars () { return $this->_viewVars; }

	/**
	 * Pending redirect request.
	 * Executed when calling view.
	 */
	protected $_redirectRequest;
	public function getRedirectRequest () { return $this->_redirectRequest; }
	public function setRedirectRequest ($pValue)
	{
		$this->_redirectRequest = $pValue;
	}

	/**
	 * Create and return a vars bag for the view.
	 * Database infos is removed from config because who need sensitive credentials in views ?
	 * Returned structure :
	 * - app -> PebbleApp instance
	 * - silex -> Silex Application instance
	 * - view -> Path of the loaded view
	 * - config -> All loaded configs as associative array, without sensitive data
	 * - vars -> $pVars argument
	 * - request -> $request argument
	 * - exception -> $exception argument
	 * @param string $pViewName Path of the loaded view
	 * @param array $pVars Associative array for specific view vars
	 * @param Request $request The associated request which triggered the route.
	 * @param Exception $exception Exception if error happened
	 * @return array The prepared vars bag
	 */
	protected function makeViewVarsBag ( $pViewName = '', array $pVars = [], Request $request = null, Exception $exception = null )
	{
		// Get config - as a copy -
		$config = $this->getConfig();

		// Remove database informations
		// This is a copy so controllers can still access to it
		// TODO : Créer un système dynamique pour supprimer les données sensibles ?
		unset( $config['database'] );

		// If dictionary does not exists
		// Map dictionary as empty array to avoid errors
		$dictionary = (
			isset( $config['dictionary'] )
			? $config['dictionary']
			: []
		);

		// Create and return bag
		// All those vars are directly accessible from twig templates
		return [
			// Access to the PebbleApp
			'app'           => $this,
			'A'             => $this,

			// Access to the SilexApp
			'silex'         => $this->getSilex(),
			'S'             => $this->getSilex(),

			// Name of the current view (path to twig file)
			'viewName'      => $pViewName,
			'N'             => $pViewName,

			// All loaded config, without sensitive data
			'config'        => $config,
			'C'             => $config,

			// Injected vars from template var files and controllers
			'vars'          => $pVars,
			'V'             => $pVars,

			// Direct access to dictionary
			'dictionary'    => $dictionary,
			'D'             => $dictionary,

			// Request triggering this route
			'request'       => $request,
			'R'             => $request,

			// Exception triggering this error
			'exception'     => $exception,
			'E'             => $exception,
		];
	}

	/**
	 * Inject vars for current view.
	 * @param array $pVars Associative array of data, passed as reference.
	 */
	public function injectViewVars (&$pVars)
	{
		ArrayUtils::extendsArray($this->_viewVars, $pVars);
	}

	/**
	 * Render a twig view from its path.
	 * Path have to be like this : "viewSubFolder/pageName"
	 * With slash between folders and no extension.
	 * For example to load the Home page located at : "app/views/pages/homePage.twig" simply specify "pages/homePage"
	 * @param string $pViewName Folder and page name to target twig template. Slash separated without extension. @see view method comments.
	 * @param Request $request The associated request which triggered the route.
	 * @param array $pVars Var bag as associated array to be injected in view. Will override other vars.
	 * @param int $pStatus Returned HTTP status code. Default is 200.
	 * @param array $pHeaders Associative array for returned headers.
	 * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
	 */
	public function view ($pViewName, Request $request, $pVars = array(), $pStatus = 200, $pHeaders = [])
	{
		// If we have a pending request
		if (isset($this->_redirectRequest))
		{
			return $this->_silexApp->redirect($this->_redirectRequest);
		}

		// Path to yaml file
		$ymlViewFile = $this->getPathTo(
			'view',
			pathinfo($pViewName, PATHINFO_DIRNAME).'/'.pathinfo($pViewName, PATHINFO_FILENAME).'.yml'
		);

		// Load it (will return empty array if file not found)
		$ymlFileVars = PebbleApp::loadYMLFile( $ymlViewFile, 0, false );

		// Inject YML view vars
		$this->injectViewVars( $ymlFileVars );

		// Injected view vars params
		$this->injectViewVars( $pVars );

		// Call app controller middleware
		$this->callAppControllerMiddleWare('beforeView', [$this, $pViewName, $request]);

		// Format view var bag
		$viewVarsBag = $this->makeViewVarsBag( $pViewName, $this->_viewVars, $request, null );

		// Render twig view with compiled vars
		$content = $this->_silexApp['twig']->render( $pViewName.'.twig', $viewVarsBag );

		// Return response with rendered twig and headers
		return new Response(
			$content,
			$pStatus,
			$pHeaders
		);
	}

	/**
	 * Return encoded JSON response
	 * @param array $pContent Associative array which will be converted to ugly json by default.
	 * @param int $pJsonOptions Json option flags.
	 * @param int $pStatus Returned HTTP status code. Default is 200.
	 * @param array $pHeaders Associative array for returned headers.
	 * @return Response
	 */
	public function json ($pContent, $pJsonOptions = 0, $pStatus = 200, $pHeaders = [])
	{
		// Return response with encoded json and headers
		return new Response(
			json_encode( $pContent, $pJsonOptions ),
			$pStatus,
			$pHeaders
		);
	}
}