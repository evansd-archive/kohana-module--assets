<?php defined('SYSPATH') or die('No direct script access.');

class CSS_Controller extends Assets_Base_Controller {
	
	// the assets controller will figure this out from the extension, but we might as well save it the trouble
	public $content_type = 'text/css';
	
	// directory where css files are stored, relative to APPROOT
	public $directory;
	
	// variables to be available to any PHP code embedded in the CSS files e.g., global color settings
	public $vars = array();
	
	// whether to strip whitespace and comments
	public $pack_css;
	
	
	
	public function __construct()
	{
		parent::__construct();
		
		foreach((array) Kohana::config('css', FALSE, FALSE) as $key => $value)
		{
			if(property_exists($this, $key)) $this->$key = $value;
		}
	}
	
	
	
	public function _remap()
	{
		// concat all the arguments into a filename
		$path = join('/', $this->uri->argument_array());
		
		// strip the extension from the filename
		$path = substr($path, 0, -strlen($this->extension) -1);
		
		// find the file, or display 404
		$file = Kohana::find_file($this->directory, $path, FALSE, $this->extension) or Kohana::show_404();
		
		// Load the view in the controller for access to $this
		$output = Kohana::$instance->_kohana_load_view($file, $this->vars);
		
		if($this->pack_css)
		{
			$output = $this->_css_compress($output);
		}
		
		echo $output;
	}
	
	
	/*
		Pilfered from the Kohana media module: controllers/media.php
	*/
	public function _css_compress($data)
	{
		// Remove comments
		$data = preg_replace('~/\*[^*]*\*+([^/][^*]*\*+)*/~', '', $data);

		// Replace all whitespace by single spaces
		$data = preg_replace('~\s+~', ' ', $data);

		// Remove needless whitespace
		$data = preg_replace('~ *+([{}+>:;,]) *~', '$1', trim($data));

		// Remove ; that closes last property of each declaration
		$data = str_replace(';}', '}', $data);

		// Remove empty CSS declarations
		$data = preg_replace('~[^{}]++\{\}~', '', $data);

		return $data;
	}
	
}