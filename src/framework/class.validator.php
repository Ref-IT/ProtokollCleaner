<?php
/**
 * FRAMEWORK Validator
 * filter and validation class
 *
 * @package         Stura - Referat IT - ProtocolHelper
 * @category        framework
 * @author 			michael g
 * @author 			Stura - Referat IT <ref-it@tu-ilmenau.de>
 * @since 			17.02.2018
 * @copyright 		Copyright (C) 2018 - All rights reserved
 * @platform        PHP
 * @requirements    PHP 7.0 or higher
 */
class Validator {

	/**
	 * validator tracks if last test was successfull or not
	 * boolean
	 */
	protected $isError;

	/**
	 * last error message
	 *  -> short error message
	 *  -> on json retuned to sender
	 * string
	 */
	protected $lastErrorMsg;

	/**
	 * last stores last map key if map validation is used
	 */
	protected $lastMapKey = '';

	/**
	 * last error description
	 *  -> error description
	 * string
	 */
	protected $lastErrorDescription;

	/**
	 * last error message
	 * string
	 */
	protected $lastErrorCode;

	/**
	 * filter may sanitize inputs
	 * mixed
	 */
	protected $filtered;

	/**
	 * class constructor
	 */
	function __contstruct(){
	}

	// ==========================================

	/**
	 * set validation status
	 *
	 * @param boolean $isError	error flag
	 * @param integer $code 	html code
	 * @param string  $msg  	short message
	 * @param string  $desc 	error description
	 */
	private function setError($isError, $code=0, $msg='', $desc=''){
		$this->isError = $isError;
		$this->lastErrorCode = $code;
		$this->lastErrorMsg = $msg;
		$this->lastErrorDescription = ($desc == '')? $msg : $desc;
		return $isError;
	}

	/**
	 * @return the $isError
	 */
	public function getIsError()
	{
		return $this->isError;
	}

	/**
	 * @return the $lastErrorMsg
	 */
	public function getLastErrorMsg()
	{
		return $this->lastErrorMsg;
	}

	/**
	 * @return the $lastErrorDescription
	 */
	public function getLastErrorDescription()
	{
		return $this->lastErrorDescription;
	}

	/**
	 * @return the $lastErrorCode
	 */
	public function getLastErrorCode()
	{
		return $this->lastErrorCode;
	}

	/**
	 * @return the $lastMapKey
	 */
	public function getLastMapKey()
	{
		return $this->lastMapKey;
	}

	/**
	 * filter may sanitize input values are stored here
	 * Post validators will create sanitized array
	 * @return the $filtered
	 */
	public function getFiltered($key = NULL)
	{
		if ($key === NULL)
			return $this->filtered;
		else
			return $this->filtered[$key];
	}

	// ==========================================

	/**
	 * call selected validator function
	 * @param mixed $value
	 * @param string $validator
	 * @return boolean value is ok
	 */
	public function validate($value, $validator){
		$validatorName = (is_array($validator))? $validator[0] : $validator;
		$validatorParams = (is_array($validator))? array_slice($validator, 1) : [];
		if (
			method_exists($this, 'V_'.$validatorName)
			&& is_callable([$this, 'V_'.$validatorName]) ){
			return $this->{'V_'.$validatorName}($value, $validatorParams);
		} else {
			$this->setError(true, 403, 'Access Denied', "POST unknown validator");
			error_log("Validator: Unknown Validator: $validatorName");
			return !$this->isError;
		}
	}

	/**
	 * validate POST data with a validation list
	 *
	 * $map format:
	 * 	[
	 * 		'postkey' => 'validator',
	 * 		'postkey2' => ['validator', 'validator_param' => 'validator_value', 'validator_param', ...],
	 *  ]
	 *  validator may contains parameter 'optional' -> so required can be disabled per parameter
	 *
	 * @param array $map
	 * @param boolean $required key is required
	 * @return boolean
	 */
	public function validateMap(&$source_unsafe, $map, $required = true){
		$out = [];
		foreach($map as $key => $validator){
			$this->lastMapKey = $key;
			if (!isset($source_unsafe[$key])){
				if ($required && !in_array('optional', $validator)){
					$this->setError(true, 403, 'Access Denied', "POST missing parameter: '$key'");
					return !$this->isError;
				} else {
					$this->setError(false);
				}
			} else {
				$this->validate($source_unsafe[$key], $validator);
				if ($this->isError) break;
				$out[$key] = $this->filtered;
			}
		}
		$this->filtered = $out;
		return !$this->isError;
	}

	/**
	 * validate POST data with a validation list
	 * add additional mfunction layer, so this will be required
	 *
	 * $map format:
	 * 	[ 'mfunction_name' =>
	 * 		[
	 * 			'postkey' => 'validator',
	 * 			'postkey2' => ['validator', 'validator_param' => 'validator_value', 'validator_param', ...],
	 *  	]
	 *  ]
	 *
	 * @param array $map
	 * @param string $groupKey
	 * @param boolean $required keys is required (groupKey key is always required)
	 * @return boolean
	 */
	public function validatePostGroup($map, $groupKey = 'mfunction', $required = true){
		if (!isset($_POST[$groupKey]) || !isset($map[$_POST[$groupKey]])){
			$this->setError(true, 403, 'Access Denied', "POST request don't match $groupKey.");
			return !$this->isError;
		} else {
			$ret = $this->validateMap($_POST, $map[$_POST[$groupKey]], $required);
			if ($ret) $this->filtered = [$_POST[$groupKey].'' => $this->filtered];
			return $ret;
		}
	}

	// ====== VALIDATORS ========================
	// functions must start with 'V_validatorname'
	
	/**
	 * dummy validator
	 * always return 'valid'
	 * @param $value
	 * @param $params
	 * @return boolean
	 */
	public function V_dummy($value = NULL, $params = NULL){
		$this->filtered = $value;
		return true;
	}

	/**
	 * integer validator
	 *
	 * params:
	 *  KEY  1-> single value, 2-> key value pair
	 * 	min 	2
	 * 	max 	2
	 *  even 	1
	 *  odd 	1
	 *  modulo	2
	 *  error	2	error message on error case
	 *
	 * @param $value
	 * @param $params
	 * @return boolean
	 */
	public function V_integer($value, $params = []){
		if (filter_var($value, FILTER_VALIDATE_INT) === false){
			$msg = (isset($params['error']))? $params['error'] : 'No Integer' ;
			return !$this->setError(true, 200, $msg, 'No Integer');
		} else {
			$v = filter_var($value, FILTER_VALIDATE_INT);
			$this->filtered = $v;
			if (in_array('even', $params, true) && $v%2 != 0){
				$msg = (isset($params['error']))? $params['error'] : 'Integer have to be even' ;
				return !$this->setError(true, 200, $msg, 'integer not even');
			}
			if (in_array('odd', $params, true) && $v%2 == 0){
				$msg = (isset($params['error']))? $params['error'] : 'Integer have to be odd' ;
				return !$this->setError(true, 200, $msg, 'integer not odd');
			}
			if (isset($params['min']) && $v < $params['min']){
				$msg = (isset($params['error']))? $params['error'] : "Integer out of range: smaller than {$params['min']}" ;
				return !$this->setError(true, 200, $msg, 'integer to small');
			}
			if (isset($params['max']) && $v > $params['max']){
				$msg = (isset($params['error']))? $params['error'] : "Integer out of range: larger than {$params['max']}" ;
				return !$this->setError(true, 200, $msg, 'integer to big');
			}
			if (isset($params['modulo']) && $v%$params['modulo'] != 0){
				$msg = (isset($params['error']))? $params['error'] : "Integer modulo failed" ;
				return !$this->setError(true, 200, $msg, 'modulo failed');
			}
			return !$this->setError(false);
		}
	}

	/**
	 * float validator
	 *
	 * params:
	 *  KEY  1-> single value, 2-> key value pair
	 *  decimal_seperator	2	[. or ,] default: .
	 * 	min 				2	min value
	 * 	max 				2	max value
	 *  step				2	step
	 *  format				2	trim to x decimal places
	 *  error				2	error message on error case
	 *
	 * @param $value
	 * @param $params
	 * @return boolean
	 */
	public function V_float($value, $params = []){
		$decimal = (isset($params['decimal_seperator']))? $params['decimal_seperator'] : '.' ;
		if (filter_var($value, FILTER_VALIDATE_FLOAT, array('options' => array('decimal' => $decimal))) === false){
			$msg = (isset($params['error']))? $params['error'] : 'No Float' ;
			return !$this->setError(true, 200, $msg, 'No Float');
		} else {
			$v = filter_var($value, FILTER_VALIDATE_FLOAT, array('options' => array('decimal' => $decimal)));
			if (isset($params['min']) && $v < $params['min']){
				$msg = (isset($params['error']))? $params['error'] : "Float out of range: smaller than {$params['min']}" ;
				return !$this->setError(true, 200, $msg, 'float to small');
			}
			if (isset($params['max']) && $v > $params['max']){
				$msg = (isset($params['error']))? $params['error'] : "Float out of range: larger than {$params['max']}" ;
				return !$this->setError(true, 200, $msg, 'float to big');
			}
			if (isset($params['step']) && fmod($v, ''.(float)$params['step']) != '0' && ''.round(fmod($v, (float)$params['step']), ((strpos($params['step'], '.')!==false)? strlen(substr($params['step'], strpos($params['step'], '.')+1)) : 1) ) != ''.(float)$params['step']){
				$msg = (isset($params['error']))? $params['error'] : "float invalid step" ;
				return !$this->setError(true, 200, $msg, 'float invalid step');
			}
			if (isset($params['format'])){
				$this->filtered = number_format($v, $params['format'], $decimal, '');
			} else {
				$this->filtered = $v;
			}
			return !$this->setError(false);
		}
	}

	/**
	 * check if integer and larger than 0
	 * @param integer $value
	 */
	public function V_id ($value, $params = NULL){
		return $this->V_integer($value, ['min' => 1]);
	}

	/**
	 * text validator
	 *
	 * params:
	 *  KEY  1-> single value, 2-> key value pair
	 * 	strip 	1
	 * 	trim 	1
	 *
	 * @param $value
	 * @param $params
	 * @return boolean
	 */
	public function V_text($value, $params = []) {
		if (!is_string($value)){
			return !$this->setError(true, 200, 'No Text', 'No Text');
		} else {
			$s = ''.$value;
	
			if (in_array('strip', $params, true) ){
				$s = strip_tags($s);
			}
			if (in_array('trim', $params, true)){
				$s = trim($s);
			}
			$this->filtered = $s;
			return !$this->setError(false);
		}
	}

	/**
	 * email validator
	 *
	 * $param
	 *  empty	1	allow empty value
	 *
	 * @param $value
	 * @param $params
	 * @return boolean
	 */
	public function V_mail ($value, $params = []) {
		$email = filter_var($value, FILTER_SANITIZE_EMAIL);
		if (in_array('empty', $params, true) && $email === ''){
			$this->filtered = $email;
			return !$this->setError(false);
		}
		$re = '/^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})$/';
		if ($email !== '' && (filter_var($email, FILTER_VALIDATE_EMAIL) === false || !preg_match($re, $email) )){
			return !$this->setError(true, 200, "mail validation failed", 'mail validation failed');
		} else {
			$this->filtered = $email;
			return !$this->setError(false);
		}
	}

	/**
	 * phone validator
	 *
	 * @param $value
	 * @param $params
	 * @return boolean
	 */
	public function V_phone($value, $params = NULL) {
		$phone = ''.trim(strip_tags(''.$value));
		$re = '/[^0-9+ ]/';
		$phone = trim(preg_replace($re, '' ,$phone));
		if ($phone == '' || $phone == false) $this->filtered = '';
		elseif (strlen($phone) > 40) {
			return !$this->setError(true, 200, "phone validation failed", 'phone validation failed');
		} else {
			$this->filtered = $phone;
		}
		return !$this->setError(false);
	}

	/**
	 * name validator
	 *
	 * @param $value
	 * $param
	 *  minlength 2		minimum string length
	 *  maxlength 2		maximum string length - default 127, set -1 for unlimited value
	 *  error	  2 	replace whole error message on error case
	 *  empty	  1 	allow empty value
	 *  multi	  2		allow multiple names seperated with this seperator, length 1
	 *  multi_add_space  1 adds space after seperator to prettify list
	 * @return boolean
	 */
	public function V_name($value, $params = NULL)  {
		$name = trim(strip_tags(''.$value));
		if (in_array('empty', $params, true) && $name === ''){
			$this->filtered = '';
			return !$this->setError(false);
		}
		$re = '';
		$re_no_sep = '/^[a-zA-Z0-9äöüÄÖÜéèêóòôáàâíìîúùûÉÈÊÓÒÔÁÀÂÍÌÎÚÙÛß]+[a-zA-Z0-9\-_ .äöüÄÖÜéèêóòôáàâíìîúùûÉÈÊÓÒÔÁÀÂÍÌÎÚÙÛß]*[a-zA-Z0-9äöüÄÖÜéèêóòôáàâíìîúùûÉÈÊÓÒÔÁÀÂÍÌÎÚÙÛß]+$/';
		if (!isset($params['multi']) || strlen($params['multi']) != 1 ){
			$re = $re_no_sep;
			$params['multi'] = NULL;
			unset($params['multi']);
		} else {
			$sep = $params['multi'];
			if (mb_strpos("/\\[]()-", $sep) !== false) $sep = "\\".$sep;
			$re = '/^[a-zA-Z0-9äöüÄÖÜéèêóòôáàâíìîúùûÉÈÊÓÒÔÁÀÂÍÌÎÚÙÛß]+[a-zA-Z0-9\-_ '.$sep.'.äöüÄÖÜéèêóòôáàâíìîúùûÉÈÊÓÒÔÁÀÂÍÌÎÚÙÛß]*[a-zA-Z0-9äöüÄÖÜéèêóòôáàâíìîúùûÉÈÊÓÒÔÁÀÂÍÌÎÚÙÛß]+$/';
		}
		if ( $name !== '' && (!preg_match($re, $name))){
			$msg = ((isset($params['error']) )?$params['error']:'name validation failed');
			return !$this->setError(true, 200, $msg, 'name validation failed');
		}
		if (!isset($params['maxlength'])){
			$params['maxlength'] = 127;
		}
		if (isset($params['maxlength']) && $params['maxlength'] != -1 && strlen($name) > $params['maxlength']){
			$msg = "The name is too long (Maximum length: {$params['maxlength']})";
			if (isset($params['error'])) $msg = $params['error'];
			return !$this->setError(true, 200, $msg, 'name validation failed - too long');
		}
		if (isset($params['minlength']) && strlen($name) < $params['minlength']){
			$msg = "The name is too short (Minimum length: {$params['minlength']})";
			if (isset($params['error'])) $msg = $params['error'];
			return !$this->setError(true, 200, $msg, 'name validation failed - too short');
		}
		if (!isset($params['multi']) || !mb_strpos($name, $params['multi'])){
			$this->filtered=$name;
		} elseif(mb_strpos($name, $params['multi'])) {
			$tmp_list = explode($params['multi'], $name);
			$tmp_names = [];
			foreach ($tmp_list as $tmp_name){
				$tmp_name = trim($tmp_name);
				if (preg_match($re_no_sep, $tmp_name) && $tmp_name){
					$tmp_names[] = $tmp_name;
				}
			}
			$this->filtered=implode($params['multi'].((in_array('multi_add_space', $params, true))?' ':''), $tmp_names);
		}
		return !$this->setError(false);
	}

	/**
	 * url validator
	 *
	 * @param $value
	 * @param $params
	 * @return boolean
	 */
	public function V_url($value, $params = NULL)  {
		$url = trim(strip_tags(''.$value));
		$re = '/^((http[s]?)((:|%3A)\/\/))(((\w)+((-|\.)(\w+))*)+(\w){0,6}?(:([0-5]?[0-9]{1,4}|6([0-4][0-9]{3}|5([0-4][0-9]{2}|5([0-2][0-9]|3[0-5])))))?\/)((\w)+((\.|-)(\w)+)*\/)*$/';
		if (!preg_match($re, $url) || strlen($url) >= 128){
			return !$this->setError(true, 200, "url validation failed", 'url validation failed');
		} else {
			$this->filtered=$url;
		}
		return !$this->setError(false);
	}

	/**
	 * ip validator
	 * check if string is a valid ip address (supports ipv4 and ipv6)
	 * @param $value
	 * @param $params
	 * @return boolean
	 */
	public function V_ip($value, $params = NULL){
		if (self::isValidIP($value)){
			$this->filtered = $value;
			return !$this->setError(false);
		} else {
			return !$this->setError(true, 200, 'No ip address', 'No ip address');
		}
	}

	/**
	 * check if string is a valid ip address (supports ipv4 and ipv6)
	 * helper function
	 *
	 * @param string $ipadr
	 * @param $recursive if true also allowes IP address with surrounding brackets []
	 * @return boolean
	 */
	public static function isValidIP( $ipadr, $recursive = true) {
		if ( preg_match('/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$|^(([a-zA-Z]|[a-zA-Z][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z]|[A-Za-z][A-Za-z0-9\-]*[A-Za-z0-9])$|^\s*((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(%.+)?\s*$/', $ipadr)) {
			return true;
		} else {
			if ($recursive && strlen($ipadr) > 2 && $ipadr[0] == '[' && $ipadr[strlen($ipadr)] == ']'){
				return self::isValidIP(substr($ipadr, 1, -1), false);
			} else {
				return false;
			}
		}
	}

	/**
	 * check if string is ends with other string
	 * @param string $haystack
	 * @param array|string $needle
	 * @param null|string $needleprefix
	 * @return boolean
	 */
	public static function endsWith($haystack, $needle, $needleprefix = null)
	{
		if (is_array($needle)){
			foreach ($needle as $sub){
				$n=(($needleprefix)?$needleprefix:'').$sub;
				if (substr($haystack, -strlen($n))===$n) {
					return true;
				}
			}
			return false;
		} else if (strlen($needle) == 0){
			return true;
		} else {
			return substr($haystack, -strlen($needle))===$needle;
		}
	}

	/**
	 * domain validator
	 *
	 * $param
	 *  empty	1	allow empty value
	 *
	 * @param $value
	 * @param $params
	 * @return boolean
	 */
	public function V_domain($value, $params = NULL){
		$host = trim(strip_tags(''.$value));
		if (in_array('empty', $params, true) && $host === ''){
			$this->filtered = $host;
			return !$this->setError(false);
		}
		if ($this->V_ip($host)){
			$this->filtered = $host;
			return !$this->setError(false);
		} else if ( preg_match("/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$/", $host) &&
			( (version_compare(PHP_VERSION, '7.0.0') >= 0) && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)!==false  ||
				(version_compare(PHP_VERSION, '7.0.0') < 0) ) ) {
			$this->filtered = $host;
			return !$this->setError(false);
		} else {
			$value_idn = idn_to_ascii($host);
			if ( preg_match("/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$/", $value_idn) &&
				( (version_compare(PHP_VERSION, '7.0.0') >= 0) && filter_var($value_idn, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)!==false  ||
					(version_compare(PHP_VERSION, '7.0.0') < 0) ) ) {
				$this->filtered = $value_idn;
				return !$this->setError(false);
			} else {
				return !$this->setError(true, 200, 'Kein gültiger Hostname angegeben' );
			}
		}
	}

	/**
	 * regex validator
	 *
	 * $param
	 *  regex	   2	match pattern
	 *  errorkey   2	replace 'regex' with errorkey on error case
	 *  error	   2	replace whole error message on error case
	 *  upper	   1	string to uppercase
	 *  lower	   1	string to lower case
	 *  replace    2	touple [search, replace] replace string
	 *  minlength  2	minimum string length
	 *  maxlength  2	maximum string length
	 *  noTagStrip 1	disable tag strip before validation
	 *  noTrim	   1	disable trim whitespaces
	 *  trimLeft   2	trim Text on left side, parameter trim characters
	 *  trimRight  2	trim Text on right side, parameter trim characters
	 *  empty	   1	allow empty string if not in regex
	 *
	 * @param $value
	 * @param $params
	 * @return boolean
	 */
	public function V_regex($value, $params = ['pattern' => '/.*/']) {
		$v = ''.$value;
		if (!in_array('noTagStrip', $params, true)){
			$v = strip_tags($v);
		}
		if (!in_array('noTrim', $params, true)){
			$v = trim($v);
		}
		if (isset($params['trimLeft'])){
			$v = ltrim ( $v , $params['trimLeft'] );
		}
		if (isset($params['trimRight'])){
			$v = rtrim ( $v , $params['trimRight'] );
		}
		if (in_array('empty', $params, true) && $v === ''){
			$this->filtered = $v;
			return !$this->setError(false);
		}
		if (isset($params['replace'])){
			$v = str_replace($params['replace'][0], $params['replace'][1], $v);
		}
		if (in_array('upper', $params, true)){
			$v = strtoupper($v);
		}
		if (in_array('lower', $params, true)){
			$v = strtolower($v);
		}
		if (isset($params['maxlength']) && strlen($v) >= $params['maxlength']){
			$msg = "String is too long (Maximum length: {$params['maxlength']})";
			return !$this->setError(true, 200, $msg);
		}
		if (isset($params['minlength']) && strlen($v) < $params['minlength']){
			$msg = "String is too short (Minimum length: {$params['minlength']})";
			return !$this->setError(true, 200, $msg);
		}
		$re = $params['pattern'];
		if (!preg_match($re, $v) || (isset($params['maxlength']) && strlen($v) >= $params['maxlength'])) {
			$msg = ((isset($params['errorkey']) )?$params['errorkey']:'regex').' validation failed';
			if (isset($params['error'])) $msg = $params['error'];
			return !$this->setError(true, 200, $msg, $msg);
		} else {
			$this->filtered=$v;
		}
		return !$this->setError(false);
	}

	/**
	 * password validator
	 *
	 * $param
	 *  minlength 2		minimum string length
	 *  maxlength 2		maximum string length
	 *  encrypt   1  	encrypt password
	 *  empty	  1 	allow empty value
	 *
	 * @param $value
	 * @param $params
	 * @return boolean
	 */
	public function V_password($value, $params = []) {
		$p = trim(strip_tags(''.$value));
	
		if (in_array('empty', $params, true) && $p === ''){
			$this->filtered = $p;
			return !$this->setError(false);
		}
		if (isset($params['maxlength']) && strlen($p) >= $params['maxlength']){
			$msg = "The password is too long (Maximum length: {$params['maxlength']})";
			return !$this->setError(true, 200, $msg);
		}
		if (isset($params['minlength']) && strlen($p) < $params['minlength']){
			$msg = "The password is too short (Minimum length: {$params['minlength']})";
			return !$this->setError(true, 200, $msg);
		}
		if (in_array('encrypt', $params, true)){
			$p = silmph_encrypt_key ($p, SILMPH_KEY_SECRET);
		}
		$this->filtered=$p;
		return !$this->setError(false);
	}

	/**
	 * name validator
	 *
	 * @param $value
	 * @param $params
	 * @return boolean
	 */
	public function V_path($value, $params = NULL) {
		$path = trim(strip_tags(''.$value));
		$re = '/^((\w)+((\.|-)(\w)+)*)(\/(\w)+((\.|-)(\w)+)*)*$/';
		if (!preg_match($re, $path) || strlen($path) >= 128){
			return !$this->setError(true, 200, "path validation failed", 'path validation failed');
		} else {
			$this->filtered=$path;
		}
		return !$this->setError(false);
	}

	/**
	 * color validator
	 *
	 * @param $value
	 * @param $params
	 * @return boolean
	 */
	public function V_color($value, $params = NULL) {
		$color = trim(strip_tags(''.$value));
		$re = '/^([a-fA-F0-9]){6}$/';
		if (!preg_match($re, $color) || strlen($color) != 6){
			return !$this->setError(true, 200, "color validation failed", 'color validation failed');
		} else {
			$this->filtered=$color;
		}
		return !$this->setError(false);
	}

	/**
	 * filename validator
	 *
	 * $param
	 *  error	2	overwrite error message
	 *
	 * @param $value
	 * @param $params
	 * @return boolean
	 */
	public function V_filename($value, $params = NULL) {
		$re = '/[^a-zA-Z0-9\-_(). äöüÄÖÜéèêóòôáàâíìîúùûÉÈÊÓÒÔÁÀÂÍÌÎÚÙÛß]/';
		$fname = trim(preg_replace($re, '' ,strip_tags(''.$value)));
		$fname = str_replace('..', '.', $fname);
		$fname = str_replace('..', '.', $fname);
		if (( strlen($fname) >= 255) || ( $fname === '' )){
			$msg = (isset($params['error']))? $params['error'] : 'filename validation failed';
			return !$this->setError(true, 200, $msg, 'filename validation failed');
		} else {
			$this->filtered=$fname;
		}
		return !$this->setError(false);
	}

	/**
	 * time validator
	 *
	 * $param
	 *  empty		1 	allow empty value
	 *  format		2	datetime-format
	 *  error		2	overwrite error message
	 *  parse		2	parse date to format after validation
	 *
	 * @param $value
	 * @param $params
	 * @return boolean
	 */
	public function V_time($value, $params = NULL) {
		$time = trim(strip_tags(''.$value));
		$fmt = (isset($params['format']))? $params['format'] : 'H:i';
		if (in_array('empty', $params, true) && ($time === '0' || $time === 'false' || $time === ''||$time === false || $time == 0)){
			$this->filtered = false;
			return !$this->setError(false);
		} elseif (!in_array('empty', $params, true) && ($time === '0' || $time === 'false' || $time === ''||$time === false || $time == 0)){
			$msg = (isset($params['error']))? $params['error'] : 'time validation failed, format: "'.$fmt.'"';
			return !$this->setError(true, 200, $msg, 'time validation failed, format: "'.$fmt.'"');
		} else {
			$d = DateTime::createFromFormat($fmt, $time);
			if($d && $d->format($fmt) == $time){
				$this->filtered = $d->format((isset($params['parse']))?$params['parse']:$fmt);
				return !$this->setError(false);
			} else {
				$msg = (isset($params['error']))? $params['error'] : 'time validation failed, format: "'.$fmt.'"';
				return !$this->setError(true, 200, $msg, 'time validation failed, format: "'.$fmt.'"');
			}
		}
	}

	/**
	 * array validator
	 * test if element is array
	 *
	 *
	 * $param
	 *  key			2	validate array key to -> validatorelelemnt, requires param->validator to be set
	 *  minlength	2	minimum string length
	 *  maxlength	2	maximum string length
	 *  empty		1	allow empty array
	 *  false		1	allow false -> reset to empty array
	 *  validator	2	run this validator on each array element
	 *  error		2	overwrite error message
	 *
	 * @param array $a
	 * @param array $params
	 * @return boolean
	 */
	public function V_array($a, $params){
		if (!is_array($a)){
			if ($a === '0' && in_array('false', $params, true)){
				$a = [];
			} else {
				$msg = (isset($params['error']))? $params['error'] : 'Value is no array';
				return !$this->setError(true, 200, $msg, 'array validator failed');
			}
		}
		if ((!in_array('empty', $params, true) || count($a) > 0) && isset($params['minlength']) && count($a) < $params['minlength']){
			$msg = (isset($params['error']))? $params['error'] : 'Array to short: require minimal length of "'.$params['minlength'].'" elements';
			return !$this->setError(true, 200, $msg, 'array validator failed: array to short');
		}
		if (isset($params['maxlength']) && count($a) > $params['maxlength']){
			$msg = (isset($params['error']))? $params['error'] : 'Array to long: maximal array length "'.$params['maxlength'].'"';
			return !$this->setError(true, 200, $msg, 'array validator failed: array to long');
		}
		if (!in_array('empty', $params, true) && count($a) == 0){
			$msg = (isset($params['error']))? $params['error'] : 'Array to short: empty array is not permitted.';
			return !$this->setError(true, 200, $msg, 'array validator failed: array is empty');
		}
		if (!isset($params['validator'])){
			$this->filtered=$a;
			return !$this->setError(false);
		}
		$out = [];
		$tmp_last_mapkey = $this->lastMapKey;
		$tmp_last_key = '';
		foreach($a as $key => $entry){
			$tmp_last_key = $key;
			$this->lastMapKey = '';
			//key
			$keyFiltered = NULL;
			if (isset($params['key'])){
				$this->validate($key, $params['key']);
				if ($this->isError) break;
				$keyFiltered = $this->filtered;
			}
			//value
			$this->validate($entry, $params['validator']);
			if ($this->isError) break;
			if ($keyFiltered === NULL){
				$out[] = $this->filtered;
			} else {
				$out[$keyFiltered] = $this->filtered;
			}
		}
		if ($this->isError) {
			$curr = $this->_capsule_lastMapKey();
			$this->lastMapKey = "{$tmp_last_mapkey}[{$tmp_last_key}]{$curr}";
		}
		$this->filtered = $out;
		return !$this->isError;
	}

	/**
	 * arraymap validator
	 * run validator on array and given map
	 *
	 * $param
	 *  map 		2 validation map
	 *  reqired 	2 boolean, default false
	 *
	 * @param array $a
	 * @param array $params
	 * @return boolean
	 */
	public function V_arraymap($a, $params){
		if (!isset($params['map'])){
			return !$this->setError(true, 200, 'invalid configuration on arraymap validation', 'arraymap validator failed: wrong configuration: missing parameter map');
		}
		$tmp_last_mapkey = $this->lastMapKey;
		$this->validateMap($a, $params['map'], (!isset($params['map'])? 'required': $params['required'] ) );
		if ($this->isError){
			$curr = $this->_capsule_lastMapKey();
			$this->lastMapKey = "{$tmp_last_mapkey}{$curr}";
		}
		return !$this->isError;
	}

	/**
	 * date validator
	 *
	 * $param
	 *  format		2	datetime-format
	 *  error		2	overwrite error message
	 *  parse		2	parse date to format after validation
	 *  empty		1	allow empty array
	 *
	 * @param $value
	 * @param $params
	 * @return boolean
	 */
	public function V_date($value, $params = NULL) {
		$date = trim(strip_tags(''.$value));
		if (in_array('empty', $params, true) && $date === ''){
			$this->filtered = $date;
			return !$this->setError(false);
		}
		$fmt = (isset($params['format']))? $params['format'] : 'Y-m-d';
		$d = DateTime::createFromFormat($fmt, $date);
		if($d && $d->format($fmt) == $date){
			$this->filtered = $d->format((isset($params['parse']))?$params['parse']:$fmt);
		} else {
			$msg = (isset($params['error']))? $params['error'] : 'date validation failed, format: "'.$fmt.'"';
			return !$this->setError(true, 200, $msg, 'date validation failed, format: "'.$fmt.'"');
		}
		return !$this->setError(false);
	}

	/**
	 * array validator
	 * test if string is valid iban
	 *
	 *
	 * $param
	 *  empty		1	allow empty array
	 *  error		2	overwrite error message
	 *
	 * @param string $value
	 * @param array $params
	 * @return boolean
	 */
	public function V_iban($value, $params){
		$iban = trim(strip_tags(''.$value));
		$iban = strtoupper($iban); // to upper
		$iban = str_replace(' ', '', $iban); //remove spaces
		//empty
		if (in_array('empty', $params, true) && $iban === ''){
			$this->filtered = $iban;
			return !$this->setError(false);
		}
		//check iban
		if (!self::_checkIBAN($iban)){
			$msg = (isset($params['error']))? $params['error'] : 'iban validation failed';
			return !$this->setError(true, 200, $msg, 'iban validation failed');
		} else {
			$this->filtered=$iban;
		}
		return !$this->setError(false);
	}

	/**
	 * check if string is valid iban,
	 *
	 * @param $iban iban srstring to check
	 *
	 * @return bool
	 * @see https://en.wikipedia.org/wiki/International_Bank_Account_Number#Validating_the_IBAN
	 */
	public static function _checkIBAN($iban){
		$iban = strtoupper(str_replace(' ', '', $iban));
		$Countries = ARRAY('AL' => 28, 'AD' => 24, 'AT' => 20, 'AZ' => 28, 'BH' => 22, 'BE' => 16, 'BA' => 20, 'BR' => 29, 'BG' => 22, 'CR' => 21, 'HR' => 21, 'CY' => 28, 'CZ' => 24, 'DK' => 18, 'DO' => 28, 'EE' => 20, 'FO' => 18, 'FI' => 18, 'FR' => 27, 'GE' => 22, 'DE' => 22, 'GI' => 23, 'GR' => 27, 'GL' => 18, 'GT' => 28, 'HU' => 28, 'IS' => 26, 'IE' => 22, 'IL' => 23, 'IT' => 27, 'JO' => 30, 'KZ' => 20, 'KW' => 30, 'LV' => 21, 'LB' => 28, 'LI' => 21, 'LT' => 20, 'LU' => 20, 'MK' => 19, 'MT' => 31, 'MR' => 27, 'MU' => 30, 'MC' => 27, 'MD' => 24, 'ME' => 22, 'NL' => 18, 'NO' => 15, 'PK' => 24, 'PS' => 29, 'PL' => 28, 'PT' => 25, 'QA' => 29, 'RO' => 24, 'SM' => 27, 'SA' => 24, 'RS' => 22, 'SK' => 24, 'SI' => 19, 'ES' => 24, 'SE' => 24, 'CH' => 21, 'TN' => 24, 'TR' => 26, 'AE' => 23, 'GB' => 22, 'VG' => 24);
	
		//1. check country code exists + iban has valid length
		if( !array_key_exists(substr($iban,0,2), $Countries)
			|| strlen($iban) != $Countries[substr($iban,0,2)]){
			return false;
		}
	
		//2. Rearrange countrycode and checksum
		$rearranged = substr($iban, 4) . substr($iban, 0, 4);
	
		//3. convert to integer
		$iban_letters = str_split($rearranged);
		$iban_int_only = '';
		foreach ($iban_int_only as $char){
			if (is_int($char)) $iban_int_only .= $char;
			else {
				$ord = ord($char) - 55; // ascii representation - 55, so a => 10, b => 11, ...
				if ($ord >= 10 && $ord <= 35){
					$iban_int_only .= $ord;
				} else {
					return false;
				}
			}
		}
	
		//4. calculate mod 97 -> have to be 1
		if (self::_bcmod($iban_int_only, '97') == 1){
			return true;
		}else{
			return false;
		}
	}

	/**
	 * _bcmod - get modulus (substitute for bcmod)
	 * be careful with big $modulus values
	 *
	 * @param string $left_operand <p>The left operand, as a string.</p>
	 * @param int $modulus <p>The modulus, as a string. </p>
	 *
	 * based on
	 * https://stackoverflow.com/questions/10626277/function-bcmod-is-not-available
	 * by Andrius Baranauskas and Laurynas Butkus :) Vilnius, Lithuania
	 **/
	public static function _bcmod($left_operand, $modulus){
		if (function_exists('bcmod')){
			return bcmod($left_operand, $modulus);
		} else {
			$take = 5; // how many numbers to take at once?
			$mod = '';
			do
			{
				$a = (int)$mod.substr( $left_operand, 0, $take );
				$left_operand = substr( $left_operand, $take );
				$mod = $a % $modulus;
			}
			while ( strlen($left_operand) );
	
			return (int)$mod;
		}
	}

	private function _capsule_lastMapKey(){
		$capsuled = $this->lastMapKey;
		if ($capsuled != ''){
			if (substr($capsuled, 0, 1) != '[' && substr($capsuled, -1) != ']'){
				$capsuled = "[$capsuled]";
			} elseif (substr($capsuled, 0, 1) != '[' && substr($capsuled, -1) == ']'){
				$pos = strpos($capsuled, '[');
				$capsuled = "[".substr($capsuled, 0, $pos).']'.substr($capsuled, $pos);
			}
		}
		return $capsuled;
	}
}