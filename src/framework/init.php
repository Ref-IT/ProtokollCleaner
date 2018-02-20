<?php
/**
 * CONFIG FILE ProtocolHelper
 * Application initialisation
 *
 * @package         Stura - Referat IT - ProtocolHelper
 * @category        configuration
 * @author 			michael g
 * @author 			Stura - Referat IT <ref-it@tu-ilmenau.de>
 * @since 			17.02.2018
 * @copyright 		Copyright (C) 2018 - All rights reserved
 * @platform        PHP
 * @requirements    PHP 7.0 or higher
 */
 
/**
 * define global variables
 */
define('SILMPH', true);
define('MAIL_TEST_TIMEOUT', 10); //prevent mailspam with testmails (in minutes)
define('SYSBASE', dirname(__FILE__, 2));
define('FRAMEWORK_PATH', dirname(__FILE__));

/**
 * set php settings
 */
ini_set('session.cookie_lifetime', '0');
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
//ini_set('session.cookie_httponly', '1');
//ini_set('session.cookie_secure', '1'); //https_only
#ini_set('session.gc_maxlifetime', 0);
#ini_set('session.gc_probability', 0);
#ini_set('session.gc_divisor', 0);
ini_set('session.use_trans_sid', '0');
ini_set('session.cache_limiter', 'nocache');
ini_set('session.cookie_lifetime', '0');

/**
 * set php error settings
 */
ini_set('display_errors', (DEBUG)? 1:0);
ini_set("log_errors", 1);
error_reporting(E_ALL);
ini_set("error_log", dirname(__FILE__, 2)."/logs/error.log");

/**
 * set server timezone
 */
date_default_timezone_set(TIMEZONE);

/**
 * include framework helper functions
 */
require_once (dirname(__FILE__)."/functions.php"); //load helper function set

if (DEBUG){
	prof_flag('app_start');
}

/**
 * generate app secret key
 */
if (defined('ENABLE_ADMIN_INSTALL') && ENABLE_ADMIN_INSTALL) {
	if (!file_exists(dirname(__FILE__, 2).'/secret.php')){
		//generate secret key - include external library: defuse-crypto
		require_once(dirname(__FILE__).'/external_libraries/crypto/defuse-crypto.phar');
		$key = Defuse\Crypto\Key::createNewRandomKey();
		$pass_key = $key->saveToAsciiSafeString();
		
		//create file content
		$key_file_content = "<?php /* -------------------------------------------------------- */\n";
		$key_file_content .= "// Must include code to stop this file being accessed directly\n";
		$key_file_content .= "if(!defined('SILMPH')) die(header('Location: index.php')); \n";
		$key_file_content .= "//* -------------------------------------------------------- */\n";
		$key_file_content .= "define('SILMPH_KEY_SECRET', '".$pass_key."');\n ?>";
		
		//create file
		$handle = fopen (dirname(__FILE__, 2).'/secret.php', w);
		fwrite ($handle, $key_file_content);
		fclose ($handle);
		chmod(dirname(__FILE__, 2).'/secret.php', 0400);
	}
}
/**
 * load app secret or die with error
 */
if (!file_exists(dirname(__FILE__, 2).'/secret.php')){
	echo 'Initialisation failed.<br>';
	echo "Activate 'ENABLE_ADMIN_INSTALL' in 'config.php' at least one time.";
	error_log("Initialisation failed. Activate 'ENABLE_ADMIN_INSTALL' in 'config.php' at least one time.");
	die();
} else {
	require_once (dirname(__FILE__, 2)."/secret.php");
}

/**
 * include database
 */
require_once (dirname(__FILE__)."/class.database.php");
$db = NULL; //set in session

/**
 * include template
 */
require_once (dirname(__FILE__)."/class.template.php");

/**
 * include external library: phpmailer
 */
require_once (dirname(__FILE__).'/external_libraries/phpmailer/src/PHPMailer.php');
require_once (dirname(__FILE__).'/external_libraries/phpmailer/src/SMTP.php');
require_once (dirname(__FILE__).'/external_libraries/phpmailer/src/Exception.php');
define('MAIL_LANGUAGE_PATH', dirname(__FILE__).'/external_libraries/phpmailer/language');

/**
 * include framework mail script
 */
require_once (dirname(__FILE__)."/class.mailHandler.php");

/**
 * include session handler
 */
require_once (dirname(__FILE__)."/class.router.php");

/**
 * include session handler
 */
require_once (dirname(__FILE__)."/session.php");

if (DEBUG){
	prof_flag('app_end');
}

// end of file -------------