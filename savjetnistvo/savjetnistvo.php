<?php
/*
Plugin Name: Savjetništvo
Description: Portal za projekte, susrete, zadaće i plaćanja (klijentski i admin dio).
Version: 0.1.0
Author: Matea
Text Domain: savjetnistvo
*/

if (!defined('ABSPATH')) exit;

define('SAVJETNISTVO_VER', '0.1.0');
define('SAVJETNISTVO_DIR', plugin_dir_path(__FILE__));
define('SAVJETNISTVO_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/src/Core/Plugin.php';
require_once __DIR__ . '/src/Core/Activator.php';
require_once __DIR__ . '/src/Core/Deactivator.php';

register_activation_hook(__FILE__, ['Savjetnistvo\\Core\\Activator','activate']);
register_deactivation_hook(__FILE__, ['Savjetnistvo\\Core\\Deactivator','deactivate']);

Savjetnistvo\Core\Plugin::init();
