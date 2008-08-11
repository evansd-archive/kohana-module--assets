<?php defined('SYSPATH') or die('No direct script access.');

$config['directory'] = 'views/assets/css';

$config['cache'] = IN_PRODUCTION ? 'static' : FALSE;

$config['pack_css'] = (bool) IN_PRODUCTION;