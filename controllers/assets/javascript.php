<?php defined('SYSPATH') or die('No direct script access.');

class JavaScript_Controller extends Assets_Base_Controller {
	
	// the assets controller will figure this out from the extension, but we might as well save it the trouble
	public $content_type = 'application/x-javascript';
	
	// directory where javascript files are stored, relative to APPROOT
	public $directory;
	
	// variables to be available to any PHP code embedded in the JS files e.g., location of shared libraries
	public $vars = array();
	
	// compression settings - compression off by default
	public $compress = FALSE;
	
	
	
	public function __construct()
	{
		parent::__construct();
		
		foreach((array) Kohana::config('javascript', FALSE, FALSE) as $key => $value)
		{
			if(property_exists($this, $key)) $this->$key = $value;
		}
	}
	
	public function __call($method, $args)
	{
		// concat all the arguments into a filename
		array_unshift($args, $method);
		$path = join('/', $args);
		
		// strip the extension from the filename
		$path = substr($path, 0, -strlen($this->extension) -1);
		
		// find the file, or display 404
		$file = Kohana::find_file($this->directory, $path, FALSE, $this->extension) or Kohana::show_404();
		
		// Load the view in the controller for access to $this
		$output = Kohana::$instance->_kohana_load_view($file, $this->vars);
		
		if( ! empty($this->compress))
		{
			$output = $this->_javascript_compress($output, $this->compress);
		}
		
		echo $output;
		
	}
	
	public function _javascript_compress($data, $config)
	{
		switch($config['type'])
		{
			case 'packer':
				return JavaScriptPacker($data, $config['level']);
		}
	}
	
	
}