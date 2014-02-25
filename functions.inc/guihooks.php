<?php
/**
 * This is the User Control Panel Object.
 *
 * Copyright (C) 2013 Schmooze Com, INC
 * Copyright (C) 2013 Andrew Nagy <andrew.nagy@schmoozecom.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package   FreePBX UCP BMO
 * @author   Andrew Nagy <andrew.nagy@schmoozecom.com>
 * @license   AGPL v3
 */
function userman_configpageinit($pagename) {
	global $currentcomponent;
	global $amp_conf;

	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$extension = isset($_REQUEST['extension'])?$_REQUEST['extension']:null;
	$tech_hardware = isset($_REQUEST['tech_hardware'])?$_REQUEST['tech_hardware']:null;

    // We only want to hook 'users' or 'extensions' pages. 
	if ($pagename != 'users' && $pagename != 'extensions')  {
		return true; 
	}
	
	if ($tech_hardware != null || $extdisplay != '' || $pagename == 'users') {
		// On a 'new' user, 'tech_hardware' is set, and there's no extension. Hook into the page. 
		if ($tech_hardware != null ) {
			userman_applyhooks();
		} elseif ($action=="add") { 
			// We don't need to display anything on an 'add', but we do need to handle returned data. 
			if ($_REQUEST['display'] == 'users') {
				userman_applyhooks();
			} else {
				$currentcomponent->addprocessfunc('userman_configprocess', 1);
			}
		} elseif ($extdisplay != '' || $pagename == 'users') { 
			// We're now viewing an extension, so we need to display _and_ process. 
			userman_applyhooks();
			$currentcomponent->addprocessfunc('userman_configprocess', 1);
		} 
	}
}

function userman_applyhooks() {
	global $currentcomponent;
	$currentcomponent->addguifunc('userman_configpageload');
}

function userman_configpageload() {
	global $currentcomponent;
	global $amp_conf;
	global $astman;
	$userman = setup_userman();
	// Init vars from $_REQUEST[]
	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$ext = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$extn = isset($_REQUEST['extension'])?$_REQUEST['extension']:null;
	$display = isset($_REQUEST['display'])?$_REQUEST['display']:null;

	if ($ext==='') {
		$extdisplay = $extn;
	} else {
		$extdisplay = $ext;
	}
	
	if ($action != 'del') {
		if($extdisplay != '') {
			$section = _("Users that can access this Extension");
			foreach($userman->getAllUsers() as $user) {
				$status = in_array($extdisplay, $userman->getGlobalSetting($user['username'],'assigned'));
				$currentcomponent->addguielem($section, new gui_checkbox( 'userman|'.$user['id'],$status, $user['username'], _('If checked this User will be able to access this user/extension'),'true','',''));
			}
		} else {
			$section = _("User Manager Settings");
			$currentcomponent->addguielem($section, new gui_checkbox( 'userman|add',true, 'Add to User Manager', _('If checked this User will be able to access this user/extension'),'true','',''));
			$currentcomponent->addguielem($section, new gui_textbox( 'userman|password',md5(uniqid()), _('Password'), _('If checked this User will be able to access this user/extension')));
			
		}
	} else {
		foreach($userman->getAllUsers() as $user) {
			$assigned = $userman->getGlobalSetting($user['username'],'assigned');
			$assigned = array_diff($assigned, array($extdisplay));
			$userman->setGlobalSetting($user['username'],'assigned',$assigned);
		}
	}
}

function userman_configprocess() {
	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$extension = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$userman = setup_userman();
	//if submitting form, update database
	switch ($action) {
		case "add":
			if(isset($_REQUEST['userman|add']) && !empty($_REQUEST['userman|password'])) {
				dbug('inside');
				$ret = $userman->addUser($extension,$_REQUEST['userman|password']);
				if($ret['status']) {
					$userman->setGlobalSetting($user['username'],'assigned',array($extension));
				}
			}
		break;
		case "edit":
			//TODO: Add/edit
			$users = array();
			foreach($_REQUEST as $key => $value) {
				if(preg_match('/^userman\|(.*)$/i',$key,$matches)) {
					$users[] = $matches[1];
				}
			}
			foreach($userman->getAllUsers() as $user) {
				$assigned = $userman->getGlobalSetting($user['username'],'assigned');
				if(in_array($user['id'],$users)) {
					//add
					if(in_array($extension, $assigned)) {
						continue;
					}
					$assigned[] = $extension;
				} else {
					//remove
					if(!in_array($extension, $assigned)) {
						continue;
					}
					$assigned = array_diff($assigned, array($extension));
				}
				$userman->setGlobalSetting($user['username'],'assigned',$assigned);
			}
		break;
	}
}