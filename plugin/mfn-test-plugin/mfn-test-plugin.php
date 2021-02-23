<?php
defined( 'ABSPATH' ) or die( 'Not authorized!' );
/**
 * Plugin Name:       MFN Test Plugin
 * Description:       Test intercepting the creation and removal of photos in the NextGEN gallery plugin-in. 
 *                    This would allow the photo upload and management to be done by NextGEN plugin and still 
 *                    preserve the ability of the MFN plugin functionality.
 * Version:           0.1.19
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Jon Catlin
 * Author URI:        https://www.destini.com/
 * Text Domain:       mfn-test-plugin
 * Domain Path:       /languages
 */

require_once ( dirname(__FILE__).'/mfn_photo_processing.php' );

define("DEBUG_LOG_FILE", plugin_dir_path(__FILE__).'debug.log');
error_log('In mfn_test_plugin'.PHP_EOL, 3, DEBUG_LOG_FILE );
