<?php
/**
 * CONTROLLER Protocol Controller
 *
 * @package         Stura - Referat IT - ProtocolHelper
 * @category        controller
 * @author 			michael g
 * @author 			Stura - Referat IT <ref-it@tu-ilmenau.de>
 * @since 			17.02.2018
 * @copyright 		Copyright (C) 2018 - All rights reserved
 * @platform        PHP
 * @requirements    PHP 7.0 or higher
 */
 
require_once (SYSBASE . '/framework/class._MotherController.php');
require_once (SYSBASE.'/framework/class.wikiClient.php');

class ProtocolController extends MotherController {
	/**
	 * request protocol from intern wiki and load basic information from database
	 * 
	 * basic information:
	 * 		name
	 * 		url
	 * 		committee
	 * 		(committe_id) if protocol is known in database
	 * 		date
	 * 		(id) if protocol is known in database
	 * 		(draft_url) if protocol is known in database
	 * 		(public_url) if protocol is known in database
	 * 
	 * @param string $committee
	 * @param string $protocol_name
	 * @param boolean $load_attachements
	 * @return Protocol|NULL
	 */
	private function loadWikiProtoBase ($committee, $protocol_name, $load_attachements = false){
		$x = new wikiClient(WIKI_URL, WIKI_USER, WIKI_PASSWORD, WIKI_XMLRPX_PATH);
		prof_flag('get wiki page');
		$a = $x->getPage(parent::$protomap[$committee][0].':'.$protocol_name);
		prof_flag('got wiki page');
		if ($a == false //dont accept non existing wiki pages
			|| $a == ''
			|| strlen($protocol_name) < 10 //protocol start with date tag -> min length 10
			|| !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", substr($protocol_name, 0,10))) { //date format yyyy-mm-dd
			return NULL;
		}
		$p = new Protocol($a);
		$p->committee = $committee;
		$p->committee_id = $this->db->getCreateCommitteeByName($committee)['id'];
		$p->name = $protocol_name;
		$p->url = parent::$protomap[$p->committee][0].':'.$p->name;
		$p->date = date_create_from_format('Y-m-d His', substr($p->name, 0,10).' 000000');
		
		$dbprotocols = $this->db->getProtocols($committee);
		if (array_key_exists($p->name, $dbprotocols)){
			$p->id = $dbprotocols[$p->name]['id'];
			$p->draft_url = $dbprotocols[$p->name]['draft_url'];
			$p->public_url = $dbprotocols[$p->name]['public_url'];
			$p->ignore = $dbprotocols[$p->name]['ignore'];
		}
		$dbresolution = $this->db->getResolutionByPTag($committee, $protocol_name, true);
		if ($dbresolution != NULL && count($dbresolution) >= 1){
			$p->agreed_on = $dbresolution[0]['id'];
		}
		if ($load_attachements){
			prof_flag('get wiki attachement list');
			$p->attachements = $x->listAttachements(parent::$protomap[$p->committee][0].':'.$p->name );
			if ($p->attachements == false) $p->attachements = [];
			prof_flag('got wiki attachement list');
		}
		// use legislatur map
		$tmp_l = $this->db->getLegislaturByDate($p->date->format('Y-m-d'));
		if (count($tmp_l) == 0) $tmp_l = $this->db->getCurrentLegislatur();
		$p->legislatur = intval($tmp_l['number']);
		
		$date_legi = date_create_from_format('Y-m-d His', $tmp_l['start']. ' 000000');
		$p->legislatur_week = intval(floor($p->date->diff($date_legi)->days/7))+1;
		
		return $p;
	}
	
	/**
	 * class constructor
	 * @param Database $db
	 * @param AuthHandler $auth
	 * @param Template $template
	 */
	function __construct($db, $auth, $template){
		parent::__construct($db, $auth, $template);
	}
	
	/**
	 * ACTION plist
	 * (stura) protocol list
	 * render and show protocol list
	 * displays 
	 */
	public function plist(){
		//permission - edit this to add add other committee
		$perm = 'stura';
		
		$x = new wikiClient(WIKI_URL, WIKI_USER, WIKI_PASSWORD, WIKI_XMLRPX_PATH);
		prof_flag('wiki request');
		$intern = $x->getPagelistAutoDepth(parent::$protomap[$perm][0]);
		if (!$intern) $intern = [];
		prof_flag('wiki request end');
		$extern = [];
		if (parent::$protomap[$perm][0] != parent::$protomap[$perm][1]){
			prof_flag('wiki request');
			$extern = $x->getPagelistAutoDepth(parent::$protomap[$perm][1]);
			if (!$extern) $extern = [];
			prof_flag('wiki request end');
		}
		$dbprotocols = $this->db->getProtocols($perm);
		$dbhasreso = $this->db->getProtocolHasResoByCommittee($perm);
		$i_path_lng = strlen(parent::$protomap[$perm][0]) + 1;
		$e_path_lng = strlen(parent::$protomap[$perm][1]) + 1;
		$counter = ['intern' => count($intern), 'published' => count($extern), 'draft' => 0];
		// ------------------------
		// mark protocols that are published but dont exist intern anymore
		$intern_and_extern = [];
		foreach ($intern as $k => $v){
			$name = substr($v, $i_path_lng);
			$intern_and_extern[$name]['intern'] = true;
		}
		foreach ($extern as $k => $v){
			$name = substr($v, $e_path_lng);
			$intern_and_extern[$name]['extern'] = true;
		}
		foreach ($dbprotocols as $name => $p){
			if (isset($p['draft_url'])){
				$intern_and_extern[$name]['draft'] = true;
				$counter['draft']++;
			}
			if (isset($dbhasreso[$name])){
				$intern_and_extern[$name]['reso'] = true;
			}
			if (isset($p['agreed']) && $p['agreed'] > 0){
				$intern_and_extern[$name]['agreed'] = true;
			}
			if (isset($intern_and_extern[$name]) && is_array($intern_and_extern[$name]) && count($intern_and_extern[$name])){
				$intern_and_extern[$name]['id'] = $p['id'];
			}
		}
		krsort($intern_and_extern);
		
		//load template
		$this->t->setTitlePrefix('Protokolle - '.ucwords( $perm, " \t\r\n\f\v-"));
		$this->t->appendCssLink('proto.css', 'screen,projection');
		$this->t->appendJsLink('protocol.js');
		$this->t->printPageHeader();
		$this->includeTemplate(__FUNCTION__, [ //pass arrays by reference
			'int_ext' 	=> &$intern_and_extern, 
			'committee' => $perm,
			'counter' 	=> &$counter
		]);
		$this->t->printPageFooter();
	}
	
	/**
	 * ACTION pedit
	 * (stura) show modify edit
	 */
	public function pedit_view(){
		//calculate accessmap
		$validator_map = [
			'committee' => ['regex',
				'pattern' => '/'.implode('|', array_keys(PROTOMAP)).'/',
				'maxlength' => 10,
				'error' => 'Du hast nicht die benötigten Berechtigungen, um dieses Protokoll zu bearbeiten.'
			],
			'proto' => ['regex', 
				'pattern' => '/^([2-9]\d\d\d)-(0[1-9]|1[0-2])-([0-3]\d)((-|_)([a-zA-Z0-9]){1,30}((-|_)?([a-zA-Z0-9]){1,2}){0,30})?$/'
			]
		];
		$vali = new Validator();
		$vali->validateMap($_GET, $validator_map, true);
		if ($vali->getIsError()){
			if($vali->getLastErrorCode() == 403){
				$this->renderErrorPage(403, null);
			} else if($vali->getLastErrorCode() == 404){
				$this->renderErrorPage(404, null);
			} else {
				http_response_code ($vali->getLastErrorCode());
				$this->t->printPageHeader();
				echo '<h3>'.$vali->getLastErrorMsg().'</h3>';
				$this->t->printPageFooter();
			}
		} else if (!checkUserPermission($vali->getFiltered()['committee'])) {
			$this->renderErrorPage(403, null);
		} else {
			$p = $this->loadWikiProtoBase($vali->getFiltered()['committee'], $vali->getFiltered()['proto'], true);
			if ($p === NULL) {
				$this->renderErrorPage(404, null);
				return;
			}
			$this->t->setTitlePrefix('Protokollkontrolle');
			$this->t->appendCssLink('proto.css', 'screen,projection');
			$this->t->appendJsLink('protocol.js');
			$this->t->printPageHeader();
			echo $this->getChallenge(); // get/echo post challenge

			//run protocol parser
			$ph = new protocolHelper();
			$ph->parseProto($p, $p->agreed_on === NULL );
			//insert protocol link + status
			protocolOut::printProtoStatus($p);
			//protocol errors
			protocolOut::createProtoTagErrors($p);
			protocolOut::printProtoParseErrors($p);
			//list Attachements
			protocolOut::printAttachements($p);
			//resolution list
			protocolOut::printResolutions($p);
			//show todo-/fixme-/deleteme list
			protocolOut::printTodoElements($p);
			
			//echo protocol diff
			echo $p->preview;
			//TODO detect Legislatur
	
			$this->t->printPageFooter();
		}
	}
	
	/**
	 * ACTION p_publish
	 * (stura) publish protocol
	 */
	public function p_publish(){
		//calculate accessmap
		$validator_map = [
			'committee' => ['regex',
				'pattern' => '/'.implode('|', array_keys(PROTOMAP)).'/',
				'maxlength' => 10,
				'error' => 'Du hast nicht die benötigten Berechtigungen, um dieses Protokoll zu bearbeiten.'
			],
			'proto' => ['regex', 
				'pattern' => '/^([2-9]\d\d\d)-(0[1-9]|1[0-2])-([0-3]\d)((-|_)([a-zA-Z0-9]){1,30}((-|_)?([a-zA-Z0-9]){1,2}){0,30})?$/'
			],
			'period' => ['integer',
				'min' => '1',
				'max' => '99',
				'error' => 'Ungültige Ligislatur.'
			],
			'attach' => ['array',
				'empty',
				'false',
				'error' => 'Ungültige Protokollanhänge.',
				'validator' => ['regex',
					'pattern' => '/^(([a-zA-Z0-9\-_äöüÄÖÜéèêóòôáàâíìîúùûÉÈÊÓÒÔÁÀÂÍÌÎÚÙÛß])+((\.)([a-zA-Z0-9\-_äöüÄÖÜéèêóòôáàâíìîúùûÉÈÊÓÒÔÁÀÂÍÌÎÚÙÛß])+)*)$/'
				]
			]
		];
		$vali = new Validator();
		$vali->validateMap($_POST, $validator_map, true);
		if ($vali->getIsError()){
			if($vali->getLastErrorCode() == 403){
				$this->json_access_denied();
			} else if($vali->getLastErrorCode() == 404){
				$this->json_not_found();
			} else {
				http_response_code ($vali->getLastErrorCode());
				$this->json_result = ['success' => false, 'eMsg' => $vali->getLastErrorMsg()];
				$this->print_json_result();
			}
		} else if (!checkUserPermission($vali->getFiltered('committee'))) {
			$this->json_access_denied();
		} else if (parent::$protomap[$vali->getFiltered('committee')][0] == parent::$protomap[$vali->getFiltered('committee')][1]) {
			// on save dont allow intern == extern protocol path =>> parse view is ok, but no storing
			//may allow partial save like Todos, Fixmes, resolutions...
			http_response_code (403);
			$this->json_result = ['success' => false, 'eMsg' => 'Your not allowed to store this protocol.'];
			$this->print_json_result();
		} else {
			$p = $this->loadWikiProtoBase($vali->getFiltered('committee'), $vali->getFiltered()['proto'], true);
			if ($p === NULL) {
				$this->json_not_found();
				return;
			}
			//run protocol parser
			$ph = new protocolHelper();
			$ph->parseProto($p, $p->agreed_on === NULL, true);
			protocolOut::createProtoTagErrors($p);
			//---------------------------------
			// check and store
			// check for fatal errors -> abort
			if (isset($p->parse_errors['f']) && count($p->parse_errors['f']) > 0){
				$this->json_result = [
					'success' => false,
					'eMsg' => 'Protokoll entkält kritische Fehler:<strong><br>* '.implode('<br>* ', $p->parse_errors['f'] ).'</strong>'
				];
				$this->print_json_result();
				return;
			}
			//---------------------------------
			//check attachements
			$copy_attachements = []; //this attachements will be copied
			$tmp_attach = $vali->getFiltered('attach');
			foreach($p->attachements as $attach){
				$tmp = explode(':', $attach);
				$name = end($tmp);
				$key = array_search($name, $tmp_attach);
				if ($key !== false){
					$copy_attachements[]=$attach;
					unset($tmp_attach[$key]);
				}
			}
			unset($tmp);
			//now tmp_attach should be empty
			if (count($tmp_attach) > 0){
				$this->json_result = [
					'success' => false,
					'eMsg' => 'Unbekannte Dateianhänge:<strong><br>* '.implode('<br>* ', $tmp_attach ).'</strong>'
				];
				$this->print_json_result();
				return;
			}
			//---------------------------------
			//check legislatur
			if ($p->legislatur !== $vali->getFiltered('period') && abs($p->legislatur - $vali->getFiltered('period')) > 1 && checkUserPermission('legislatur_all')){
				$this->json_result = [
					'success' => false,
					'eMsg' => 'Du bist nicht berechtigt, die Legislaturnummer um mehr als 1 zu ändern.'
				];
				$this->print_json_result();
				return;
			}
			$p->legislatur = $vali->getFiltered('period');
			
			//---------------------------------
			//create protocol in wiki
			$x = new wikiClient(WIKI_URL, WIKI_USER, WIKI_PASSWORD, WIKI_XMLRPX_PATH);
			prof_flag('write wiki page');
			$put_res = $x->putPage(parent::$protomap[$vali->getFiltered('committee')][1].':'.$p->name, $p->external, ['sum' => 'GENERIERT mit '.BASE_TITLE.' von ('. $this->auth->getUserName().')']);
			prof_flag('wiki page written');
			if ($put_res == false){
				$this->json_result = [
					'success' => false,
					'eMsg' => 'Fehler beim Veröffentlichen. (Code: '.$x->getStatusCode().')'
				];
				error_log('Proto Publish: Could not publish. Request: Put Page - '.parent::$protomap[$vali->getFiltered('committee')][1].':'.$p->name.' - Wiki respond: '.$x->getStatusCode().' - '.(($x->isError())?$x->getError():''));
				$this->print_json_result();
				return;
			}
			$is_draft = true;
			if ($p->agreed_on === NULL){
				$p->public_url = NULL;
				$p->draft_url = $p->name;
			} else {
				$is_draft = false;
				$p->public_url = $p->name;
				$p->draft_url = NULL;
			}
			
			//---------------------------------
			//create/update protocol in db
			$this->db->createUpdateProtocol($p);
			
			//---------------------------------
			//create/update resolutions
			$db_resolutions = $this->db->getResolutionByOnProtocol($p->id, true);
			$gremium = $this->db->getCreateCommitteebyName($vali->getFiltered('committee'));

			/*
			 * PER RESOLUTION TYPE
			1. p - db keys = rest_map_indexes
			2. foreach db_text -> key in new,
			   case db_text[key] == p_text[key]
				unset db_text[key]
				unset p_text[key]
				dic[key] = key
				- use dic or update directly
			   case isset(p_text[key]) && db_text[key] != p_text[key] ;;but false !== p_key = in_array db_text[key] in p_text -> p_key
				unset db_text[key]
				unset p_text[p_key]
				dic[p_key] = key
				- use dic or update directly
			   case db_text[db_key] not in p_text
				ignore -> delete
				if key not in rest_map_indexes, add there
			3. foreach p_text -> key
				if key in rest_map -> reuse
					else
						dic[key] = rest_map.pop()
				-> insert
			4. update elements of dic (not required, done inline)
			 *
			 *  */

			$resoTodo = [];
			$p_reso_keys = [];

			//init data for matching algo
			foreach($db_resolutions as $k => $r){
				$resoTodo[$r['type_long']]['db'][$k] = $r['text'];
			}
			foreach($p->resolutions as $k => $r){
				$resoTodo[$r['type_long']]['p'][$r['r_tag']] = $r['text'];
				$p_reso_keys[$r['r_tag']] = $k;
				$p->resolutions[$k]['on_protocol'] = $p->id;
			}

			//per type
			foreach ($resoTodo as $reso_type => $rtexts){
				$db_text = (isset($rtexts['db']))? $rtexts['db'] : [];
				$p_text  = (isset($rtexts['p']))?  $rtexts['p']  : [];

				//1.
				$new_map_keys = array_diff(array_keys($p_text), array_keys($db_text));
				//2.
				foreach( $db_text as $db_k => $v ) {
					if (isset($p_text[$db_k]) && $db_text[$db_k] == $p_text[$db_k]) {
						unset( $db_text[$db_k] );
						unset( $p_text[$db_k] );
						// update reso
						$db_reso = $db_resolutions[$db_k];
						$p_reso = $p->resolutions[$p_reso_keys[$db_k]];
						$p_reso['id'] = $db_reso['id'];

						if ($reso_type == 'Protokoll') {
							//if old reso has p_tag, and new is not the same, remove agreed id of protocol
							if ($db_reso['p_tag'] !== $p_reso['p_tag']){
								$this->db->updateProtocolRemoveAgreedByAgreedId( $db_reso['id'], $gremium['id'] );
							}
							//may set new agreed tag
							if (isset($p_reso['p_link_date']) && is_array($p_reso['p_link_date'])){
								foreach ($p_reso['p_link_date'] as $resoDate){
									$this->db->updateProtocolSetAgreed($db_reso['id'], $gremium['id'], $resoDate);
								}
							} else {
								error_log('427_1: protocol.php --- undefined index: p_link_date, skip setAgreed'."\n\t".print_r($p_reso));
							}
						}
						//real reso update
						$this->db->updateResolution($p_reso);
					} elseif(isset($p_text[$db_k]) && $db_text[$db_k] != $p_text[$db_k] && false !== ($p_k = array_search( $db_text[$db_k], $p_text, true))) {
						unset( $db_text[$db_k] );
						unset( $p_text[$p_k] );

						// update reso
						$db_reso = $db_resolutions[$db_k];
						$p_reso = $p->resolutions[$p_reso_keys[$p_k]];
						$p_reso['id'] = $db_reso['id'];
						$p_reso['r_tag'] = $db_k; // weise alten key zu

						if ($reso_type == 'Protokoll') {
							//if old reso has p_tag, and new is not the same, remove agreed id of protocol
							if ($db_reso['p_tag'] !== $p_reso['p_tag']){
								$this->db->updateProtocolRemoveAgreedByAgreedId( $db_reso['id'], $gremium['id'] );
							}
							//may set new agreed tag
							if (isset($p_reso['p_link_date']) && is_array($p_reso['p_link_date'])){
								foreach ($p_reso['p_link_date'] as $resoDate){
									$this->db->updateProtocolSetAgreed($p_reso['id'], $gremium['id'], $resoDate);
								}
							} else {
								error_log('453_1: protocol.php --- undefined index: p_link_date, skip setAgreed'."\n\t".print_r($p_reso));
							}
						}
						//real reso update
						$this->db->updateResolution($p_reso);
					} elseif (!isset($p_text[$db_k]) || $db_text[$db_k] != $p_text[$db_k] && false === (array_search( $db_text[$db_k], $p_text, true))) {
						unset( $db_text[$db_k] );

						// ignore -> delete reso
						$db_reso = $db_resolutions[$db_k];

						if ($reso_type == 'Protokoll') {
							//if old reso has p_tag, and new is not the same, remove agreed id of protocol
							$this->db->updateProtocolRemoveAgreedByAgreedId( $db_reso['id'], $gremium['id'] );
						}

						// real reso delete
						$this->db->deleteResolutionById($db_reso['id']);

						// if key not in rest_map_indexes, add there
						if (!in_array($db_k, $new_map_keys,true)){
							$new_map_keys[] = $db_k;
						}
					}
				}
				//3.
				foreach($p_text as $p_k => $v){
					$p_reso = $p->resolutions[$p_reso_keys[$p_k]];
					if (false===( $pos = array_search( $p_k, $new_map_keys, true))) {
						$p_reso['r_tag'] = array_shift($new_map_keys);
					} else {
						unset($new_map_keys[$pos]);
					}
					//insert
					$newid = $this->db->createResolution($p_reso);
					if ($reso_type == 'Protokoll') {
						$p_reso['id'] = $newid;

						//may set new agreed tag
						if (isset($p_reso['p_link_date']) && is_array($p_reso['p_link_date'])){
							foreach ($p_reso['p_link_date'] as $resoDate){
								$this->db->updateProtocolSetAgreed($p_reso['id'], $gremium['id'], $resoDate);
							}
						} else {
							error_log('500_1: protocol.php --- undefined index: p_link_date, skip setAgreed'."\n\t".print_r($p_reso));
						}
					}
				}
			}

			//---------------------------------
			//create/update/delete todo|fixme|deleteme
			$db_todo = $this->db->getTodosByProtocol($p->id, true);
			foreach($p->todos as $proto_todo)
			{
				$proto_todo['on_protocol'] = $p->id;
				//create missing todo
				if (!isset($db_todo[$proto_todo['hash']])){
					$this->db->createTodo($proto_todo);
				} else {
					//ignore existing
					unset($db_todo[$proto_todo['hash']]);
				}
			}
			//delete only not 'done' todos
			$del_ids = [];
			foreach($db_todo as $todo)
			{
				if ($todo['done'] == 0){
					$del_ids[] = $todo['id'];
				}
			}
			if (count($del_ids) > 0){
				$this->db->deleteTodoById($del_ids);
			}
			//---------------------------------
			//test if attachements need to be removed
			$put_url = ($is_draft == true)?$p->draft_url : $p->public_url;
			//check existing attachements
			$current_attachements = $x->listAttachements($put_url);
			if ($current_attachements == false) $current_attachements = [];
			//copy missing attachements
			foreach($copy_attachements as $att){
				$tmp = explode(':', $att);
				$name = end($tmp);
				$exists = array_search($put_url.':'.$name, $current_attachements);
				if ($exists !== false){
					unset($current_attachements[$exists]);
					continue;
				}
				$data = $x->getAttachement($att);
				if ($data) {
					$x->putAttachement($put_url.':'.$name, $data, ['ow' => true]);
				} else {
					error_log("Couldn't fetch attachement: $att");
				}
			}
			unset($tmp);
			//remove not needed attachements
			foreach($current_attachements as $del_att){
				$x->deleteAttachement($del_att);
			}
			
			http_response_code (200);
			$this->json_result = [
				'success' => true,
				'msg' => 'Protokoll erfolgreich erstellt',
				'timing' => prof_print(false)['sum']
			];
			$this->print_json_result();
		}
	}
	
	/**
	 * ACTION p_ignore
	 * ignore protocol -> d'ont remember on mails and new protocols
	 */
	public function p_ignore(){
		//calculate accessmap
		$validator_map = [
			'committee' => ['regex',
				'pattern' => '/'.implode('|', array_keys(PROTOMAP)).'/',
				'maxlength' => 10,
				'error' => 'Du hast nicht die benötigten Berechtigungen, um dieses Protokoll zu bearbeiten.'
			],
			'proto' => ['regex',
				'pattern' => '/^([2-9]\d\d\d)-(0[1-9]|1[0-2])-([0-3]\d)((-|_)([a-zA-Z0-9]){1,30}((-|_)?([a-zA-Z0-9]){1,2}){0,30})?$/'
			],
		];
		$vali = new Validator();
		$vali->validateMap($_POST, $validator_map, true);
		if ($vali->getIsError()){
			if($vali->getLastErrorCode() == 403){
				$this->json_access_denied();
			} else if($vali->getLastErrorCode() == 404){
				$this->json_not_found();
			} else {
				http_response_code ($vali->getLastErrorCode());
				$this->json_result = ['success' => false, 'eMsg' => $vali->getLastErrorMsg()];
				$this->print_json_result();
			}
		} else if (!checkUserPermission($vali->getFiltered('committee'))) {
			$this->json_access_denied();
		} else if (parent::$protomap[$vali->getFiltered('committee')][0] == parent::$protomap[$vali->getFiltered('committee')][1]) {
			// on save dont allow intern == extern protocol path =>> parse view is ok, but no storing
			//may allow partial save like Todos, Fixmes, resolutions...
			http_response_code (403);
			$this->json_result = ['success' => false, 'eMsg' => 'Your not allowed to store this protocol.'];
			$this->print_json_result();
		} else {
			// load protocol from db ------------------
			$dbprotocols = $this->db->getProtocols($vali->getFiltered('committee'));
			$p = NULL;
			if (array_key_exists($vali->getFiltered('proto'), $dbprotocols)){
				$p = new Protocol('');
				$p->name = $dbprotocols[$vali->getFiltered('proto')]['name'];
				$p->id = $dbprotocols[$p->name]['id'];
				$p->url = $dbprotocols[$p->name]['url'];
				$p->date = $dbprotocols[$p->name]['date'];
				$p->agreed = $dbprotocols[$p->name]['agreed'];
				$p->committee_id = $dbprotocols[$p->name]['gremium'];
				$p->legislatur = $dbprotocols[$p->name]['legislatur'];
				$p->draft_url = $dbprotocols[$p->name]['draft_url'];
				$p->public_url = $dbprotocols[$p->name]['public_url'];
				$p->ignore = $dbprotocols[$p->name]['ignore'];
			} else {
			// load protocol from wiki -----------------
				$p = $this->loadWikiProtoBase($vali->getFiltered('committee'), $vali->getFiltered()['proto'], false);
			}
			if ($p === NULL) {
				$this->json_not_found();
				return;
			}
			//---------------------------------
			$p->ignore = ($p->ignore)? 0 : 1;
			//create/update protocol in db
			$ok = $this->db->createUpdateProtocol($p);
			if ($ok){
				$this->json_result = [
					'success' => true,
					'msg' => ($p->ignore)?'Protokoll wird bei zukünftigen Einladungen und Protokollen ignoriert. (Sollte nur bei nicht beschlussfähigen Sitzungen geschehen.)': 'Protokoll wird bei zukünftigen Einladungen berücksichtigt.',
					'timing' => prof_print(false)['sum']
				];
			} else {
				$this->json_result = [
					'success' => false,
					'eMsg' => 'Fehler beim Ändern der Protokolleinstellung',
					'timing' => prof_print(false)['sum']
				];
			}
			http_response_code (200);
			$this->print_json_result();
		}
	}
}