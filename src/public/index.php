<?php
/**
 * INDEX FILE ProtocolHelper
 * Application starting point
 *
 * @package         Stura - Referat IT - ProtocolHelper
 * @category        application
 * @author 			michael g
 * @author 			Stura - Referat IT <ref-it@tu-ilmenau.de>
 * @since 			17.02.2018
 * @copyright 		Copyright (C) 2018 - All rights reserved
 * @platform        PHP
 * @requirements    PHP 7.0 or higher
 */
// ===== load framework =====
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (!file_exists ( dirname(__FILE__, 2).'/config.php' )){
	echo "No configuration file found! Please create and edit 'config.php'";
	die();
}
require_once (dirname(__FILE__, 2).'/config.php');