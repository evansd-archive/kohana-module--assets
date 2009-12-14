<?php
/**
 * Copyright (c) 2008-2009 David Evans
 * License: MIT-style (see license.txt)
**/

class Assets_Controller extends Assets_Base_Controller
{
	public function __call($method, $args)
	{
		// Concat all the arguments into a filepath
		array_unshift($args, $method);
		$path = join('/', $args);

		// Loop through the routes and see if anything matches
		foreach ((array) Kohana::config('assets', FALSE, FALSE) as $key => $val)
		{
			if (preg_match('#^'.$key.'$#u', $path))
			{
				// If the supplied value is a config array ...
				if (is_array($val))
				{
					// ... get the mapped route ...
					$route = arr::remove('route', $val);

					// ... and apply the rest of the config settings
					$this->apply_config($val);
				}

				// Otherwise treat the value as a simple routing string
				else
				{
					$route = $val;
				}

				if (strpos($route, '$') !== FALSE)
				{
					// Use regex routing
					$routed_path = preg_replace('#^'.$key.'$#u', $route, $path);
				}
				else
				{
					// Standard routing
					$routed_path = $route;
				}

				// A valid route has been found
				break;
			}
		}

		// If no matching route is found, then 404
		if ( ! isset($routed_path)) Event::run('system.404');

		$pathinfo = pathinfo($routed_path);

		$directories = explode('/', $pathinfo['dirname']);

		$directory = array_shift($directories);

		$path = join('/', $directories).'/'.$pathinfo['filename'];

		// Search for file using cascading file system, 404 if not found
		$file = Kohana::find_file($directory, $path, FALSE, $pathinfo['extension']);
		if ( ! $file) Event::run('system.404');
		
		readfile($file);
	}
}
