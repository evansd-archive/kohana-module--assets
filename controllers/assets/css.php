<?php
/**
 * Copyright (c) 2008-2009 David Evans
 * License: MIT-style (see license.txt)
**/

// We load the base class manually as Kohana::__autoload
// won't find controllers in sub-directories
include_once Kohana::find_file('controllers/assets', 'javascript', TRUE);

class CSS_Controller extends Javascript_Controller {

	// The assets controller will figure this out from the extension, but we might as well save it the trouble
	public $content_type = 'text/css';

	// Directory where CSS files are stored, relative to APPROOT
	public $directory = 'css';

	// Config file to load
	public $config_file = 'css';

	// Don't disable short tags as they're unlikely to cause
	// problems in CSS files and may be convenient
	public $disable_short_tags = FALSE;


	protected function _compress($data, $config)
	{
		switch($config === TRUE ? 'strip' : $config['type'])
		{
			case 'strip':
				// Borrowed from the old Kohana media module:
				// http://code.google.com/p/kohanamodules/source/browse/tags/2.2/media/controllers/media.php

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

			case 'yuicompressor':
				$options = isset($config['options']) ? $config['options'] : '';
				return yuicompressor::compress($data, 'css', $options);

			default:
				throw new Kohana_User_Exception('Unknown CSS Compression Type', 'Unknown type: '.$config['type']);
		}
	}

}
