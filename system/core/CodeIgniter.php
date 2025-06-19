<?php

defined('BASEPATH') OR exit('No direct script access allowed');


const CI_VERSION = '3.1.11';


	if (file_exists(APPPATH.'config/'.ENVIRONMENT.'/constants.php'))
	{
		require_once(APPPATH.'config/'.ENVIRONMENT.'/constants.php');
	}

	if (file_exists(APPPATH.'config/constants.php'))
	{
		require_once(APPPATH.'config/constants.php');
	}


	require_once(BASEPATH.'core/Common.php');




if ( ! is_php('5.4'))
{
	ini_set('magic_quotes_runtime', 0);

	if ((bool) ini_get('register_globals'))
	{
		$_protected = array(
			'_SERVER',
			'_GET',
			'_POST',
			'_FILES',
			'_REQUEST',
			'_SESSION',
			'_ENV',
			'_COOKIE',
			'GLOBALS',
			'HTTP_RAW_POST_DATA',
			'system_path',
			'application_folder',
			'view_folder',
			'_protected',
			'_registered'
		);

		$_registered = ini_get('variables_order');
		foreach (array('E' => '_ENV', 'G' => '_GET', 'P' => '_POST', 'C' => '_COOKIE', 'S' => '_SERVER') as $key => $superglobal)
		{
			if (strpos($_registered, $key) === FALSE)
			{
				continue;
			}

			foreach (array_keys($$superglobal) as $var)
			{
				if (isset($GLOBALS[$var]) && ! in_array($var, $_protected, TRUE))
				{
					$GLOBALS[$var] = NULL;
				}
			}
		}
	}
}



	set_error_handler('_error_handler');
	set_exception_handler('_exception_handler');
	register_shutdown_function('_shutdown_handler');


	if ( ! empty($assign_to_config['subclass_prefix']))
	{
		get_config(array('subclass_prefix' => $assign_to_config['subclass_prefix']));
	}


	if ($composer_autoload = config_item('composer_autoload'))
	{
		if ($composer_autoload === TRUE)
		{
			file_exists(APPPATH.'vendor/autoload.php')
				? require_once(APPPATH.'vendor/autoload.php')
				: log_message('error', '$config[\'composer_autoload\'] is set to TRUE but '.APPPATH.'vendor/autoload.php was not found.');
		}
		elseif (file_exists($composer_autoload))
		{
			require_once($composer_autoload);
		}
		else
		{
			log_message('error', 'Could not find the specified $config[\'composer_autoload\'] path: '.$composer_autoload);
		}
	}


	$BM =& load_class('Benchmark', 'core');
	$BM->mark('total_execution_time_start');
	$BM->mark('loading_time:_base_classes_start');


	$EXT =& load_class('Hooks', 'core');


	$EXT->call_hook('pre_system');

	$CFG =& load_class('Config', 'core');

	
	if (isset($assign_to_config) && is_array($assign_to_config))
	{
		foreach ($assign_to_config as $key => $value)
		{
			$CFG->set_item($key, $value);
		}
	}


	$charset = strtoupper(config_item('charset'));
	ini_set('default_charset', $charset);

	if (extension_loaded('mbstring'))
	{
		define('MB_ENABLED', TRUE);
		
		@ini_set('mbstring.internal_encoding', $charset);
		
		mb_substitute_character('none');
	}
	else
	{
		define('MB_ENABLED', FALSE);
	}


	if (extension_loaded('iconv'))
	{
		define('ICONV_ENABLED', TRUE);
		
		@ini_set('iconv.internal_encoding', $charset);
	}
	else
	{
		define('ICONV_ENABLED', FALSE);
	}

	if (is_php('5.6'))
	{
		ini_set('php.internal_encoding', $charset);
	}



	require_once(BASEPATH.'core/compat/mbstring.php');
	require_once(BASEPATH.'core/compat/hash.php');
	require_once(BASEPATH.'core/compat/password.php');
	require_once(BASEPATH.'core/compat/standard.php');


	$UNI =& load_class('Utf8', 'core');


	$URI =& load_class('URI', 'core');


	$RTR =& load_class('Router', 'core', isset($routing) ? $routing : NULL);

/*
 * ------------------------------------------------------
 *  Instantiate the output class
 * ------------------------------------------------------
 */
	$OUT =& load_class('Output', 'core');

/*
 * ------------------------------------------------------
 *	Is there a valid cache file? If so, we're done...
 * ------------------------------------------------------
 */
	if ($EXT->call_hook('cache_override') === FALSE && $OUT->_display_cache($CFG, $URI) === TRUE)
	{
		exit;
	}

/*
 * -----------------------------------------------------
 * Load the security class for xss and csrf support
 * -----------------------------------------------------
 */
	$SEC =& load_class('Security', 'core');

/*
 * ------------------------------------------------------
 *  Load the Input class and sanitize globals
 * ------------------------------------------------------
 */
	$IN	=& load_class('Input', 'core');

/*
 * ------------------------------------------------------
 *  Load the Language class
 * ------------------------------------------------------
 */
	$LANG =& load_class('Lang', 'core');

/*
 * ------------------------------------------------------
 *  Load the app controller and local controller
 * ------------------------------------------------------
 *
 */
	// Load the base controller class
	require_once BASEPATH.'core/Controller.php';

	/**
	 * Reference to the CI_Controller method.
	 *
	 * Returns current CI instance object
	 *
	 * @return CI_Controller
	 */
	function &get_instance()
	{
		return CI_Controller::get_instance();
	}

	if (file_exists(APPPATH.'core/'.$CFG->config['subclass_prefix'].'Controller.php'))
	{
		require_once APPPATH.'core/'.$CFG->config['subclass_prefix'].'Controller.php';
	}

	// Set a mark point for benchmarking
	$BM->mark('loading_time:_base_classes_end');

/*
 * ------------------------------------------------------
 *  Sanity checks
 * ------------------------------------------------------
 *
 *  The Router class has already validated the request,
 *  leaving us with 3 options here:
 *
 *	1) an empty class name, if we reached the default
 *	   controller, but it didn't exist;
 *	2) a query string which doesn't go through a
 *	   file_exists() check
 *	3) a regular request for a non-existing page
 *
 *  We handle all of these as a 404 error.
 *
 *  Furthermore, none of the methods in the app controller
 *  or the loader class can be called via the URI, nor can
 *  controller methods that begin with an underscore.
 */

	$e404 = FALSE;
	$class = ucfirst($RTR->class);
	$method = $RTR->method;

	if (empty($class) OR ! file_exists(APPPATH.'controllers/'.$RTR->directory.$class.'.php'))
	{
		$e404 = TRUE;
	}
	else
	{
		require_once(APPPATH.'controllers/'.$RTR->directory.$class.'.php');

		if ( ! class_exists($class, FALSE) OR $method[0] === '_' OR method_exists('CI_Controller', $method))
		{
			$e404 = TRUE;
		}
		elseif (method_exists($class, '_remap'))
		{
			$params = array($method, array_slice($URI->rsegments, 2));
			$method = '_remap';
		}
		elseif ( ! method_exists($class, $method))
		{
			$e404 = TRUE;
		}
		/**
		 * DO NOT CHANGE THIS, NOTHING ELSE WORKS!
		 *
		 * - method_exists() returns true for non-public methods, which passes the previous elseif
		 * - is_callable() returns false for PHP 4-style constructors, even if there's a __construct()
		 * - method_exists($class, '__construct') won't work because CI_Controller::__construct() is inherited
		 * - People will only complain if this doesn't work, even though it is documented that it shouldn't.
		 *
		 * ReflectionMethod::isConstructor() is the ONLY reliable check,
		 * knowing which method will be executed as a constructor.
		 */
		elseif ( ! is_callable(array($class, $method)))
		{
			$reflection = new ReflectionMethod($class, $method);
			if ( ! $reflection->isPublic() OR $reflection->isConstructor())
			{
				$e404 = TRUE;
			}
		}
	}

	if ($e404)
	{
		if ( ! empty($RTR->routes['404_override']))
		{
			if (sscanf($RTR->routes['404_override'], '%[^/]/%s', $error_class, $error_method) !== 2)
			{
				$error_method = 'index';
			}

			$error_class = ucfirst($error_class);

			if ( ! class_exists($error_class, FALSE))
			{
				if (file_exists(APPPATH.'controllers/'.$RTR->directory.$error_class.'.php'))
				{
					require_once(APPPATH.'controllers/'.$RTR->directory.$error_class.'.php');
					$e404 = ! class_exists($error_class, FALSE);
				}
				// Were we in a directory? If so, check for a global override
				elseif ( ! empty($RTR->directory) && file_exists(APPPATH.'controllers/'.$error_class.'.php'))
				{
					require_once(APPPATH.'controllers/'.$error_class.'.php');
					if (($e404 = ! class_exists($error_class, FALSE)) === FALSE)
					{
						$RTR->directory = '';
					}
				}
			}
			else
			{
				$e404 = FALSE;
			}
		}

		// Did we reset the $e404 flag? If so, set the rsegments, starting from index 1
		if ( ! $e404)
		{
			$class = $error_class;
			$method = $error_method;

			$URI->rsegments = array(
				1 => $class,
				2 => $method
			);
		}
		else
		{
			show_404($RTR->directory.$class.'/'.$method);
		}
	}

	if ($method !== '_remap')
	{
		$params = array_slice($URI->rsegments, 2);
	}

/*
 * ------------------------------------------------------
 *  Is there a "pre_controller" hook?
 * ------------------------------------------------------
 */
	$EXT->call_hook('pre_controller');

/*
 * ------------------------------------------------------
 *  Instantiate the requested controller
 * ------------------------------------------------------
 */
	// Mark a start point so we can benchmark the controller
	$BM->mark('controller_execution_time_( '.$class.' / '.$method.' )_start');

	$CI = new $class();

/*
 * ------------------------------------------------------
 *  Is there a "post_controller_constructor" hook?
 * ------------------------------------------------------
 */
	$EXT->call_hook('post_controller_constructor');

/*
 * ------------------------------------------------------
 *  Call the requested method
 * ------------------------------------------------------
 */
	call_user_func_array(array(&$CI, $method), $params);

	// Mark a benchmark end point
	$BM->mark('controller_execution_time_( '.$class.' / '.$method.' )_end');

/*
 * ------------------------------------------------------
 *  Is there a "post_controller" hook?
 * ------------------------------------------------------
 */
	$EXT->call_hook('post_controller');

/*
 * ------------------------------------------------------
 *  Send the final rendered output to the browser
 * ------------------------------------------------------
 */
	if ($EXT->call_hook('display_override') === FALSE)
	{
		$OUT->_display();
	}

/*
 * ------------------------------------------------------
 *  Is there a "post_system" hook?
 * ------------------------------------------------------
 */
	$EXT->call_hook('post_system');
