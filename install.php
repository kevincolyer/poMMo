<?php
/**
 * Copyright (C) 2005, 2006, 2007, 2008  Brice Burgess <bhb@iceburg.net>
 * 
 * This file is part of poMMo (http://www.pommo.org)
 * 
 * poMMo is free software; you can redistribute it and/or modify 
 * it under the terms of the GNU General Public License as published 
 * by the Free Software Foundation; either version 2, or any later version.
 * 
 * poMMo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See
 * the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with program; see the file docs/LICENSE. If not, write to the
 * Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA.
 */

/**********************************
	INITIALIZATION METHODS
 *********************************/
require('bootstrap.php');
require_once(Pommo::$_baseDir.'classes/Pommo_Install.php');
Pommo::init(array('authLevel' => 0, 'install' => TRUE));

session_start(); // required by smartyValidate. TODO -> move to prepareForForm() ??
$logger = & Pommo::$_logger;
$dbo = & Pommo::$_dbo;
$dbo->dieOnQuery(FALSE);

/**********************************
	SETUP TEMPLATE, PAGE
 *********************************/
require_once(Pommo::$_baseDir.'classes/Pommo_Template.php');
$smarty = new Pommo_Template();
$smarty->prepareForForm();

// Check to make sure poMMo is not already installed.
if (Pommo_Install::verify()) {
	$logger->addErr(Pommo::_T('poMMo is already installed.'));
	$smarty->assign('installed', TRUE);
	$smarty->display('install.tpl');
	Pommo::kill();
}

if (isset ($_REQUEST['disableDebug']))
	unset ($_REQUEST['debugInstall']);
elseif (isset ($_REQUEST['debugInstall'])) $smarty->assign('debug', TRUE);

if (!SmartyValidate :: is_registered_form() || empty ($_POST)) {
	// ___ USER HAS NOT SENT FORM ___
	SmartyValidate :: connect($smarty, true);
	
	SmartyValidate :: register_validator('list_name', 'list_name', 'notEmpty', false, false, 'trim');
	SmartyValidate :: register_validator('site_name', 'site_name', 'notEmpty', false, false, 'trim');
	SmartyValidate :: register_validator('site_url', 'site_url', 'isURL');
	SmartyValidate :: register_validator('admin_password', 'admin_password', 'notEmpty', false, false, 'trim');
	SmartyValidate :: register_validator('admin_password2', 'admin_password:admin_password2', 'isEqual');
	SmartyValidate :: register_validator('admin_email', 'admin_email', 'isEmail');

	$formError = array ();
	$formError['list_name'] = $formError['site_name'] = $formError['admin_password'] = Pommo::_T('Cannot be empty.');
	$formError['admin_password2'] = Pommo::_T('Passwords must match.');
	$formError['site_url'] = Pommo::_T('Must be a valid URL');
	$formError['admin_email'] = Pommo::_T('Must be a valid email');
	$smarty->assign('formError', $formError);
} else {
	// ___ USER HAS SENT FORM ___
	SmartyValidate :: connect($smarty);

	if (SmartyValidate :: is_valid($_POST))
	{
		// __ FORM IS VALID
		if (isset ($_POST['installerooni']))
		{			
			// drop existing poMMo tables
			foreach (array_keys($dbo->table) as $key)
			{
				$table = $dbo->table[$key];
				$sql = 'DROP TABLE IF EXISTS ' . $table;
				$dbo->query($sql);
			}
			
			if (isset ($_REQUEST['debugInstall']))
			{
				$dbo->debug(TRUE);
			}

			$install = Pommo_Install::parseSQL();

			if ($install)
			{
				// installation of DB went OK, set configuration values to user
				// supplied ones
				$pass = $_POST['admin_password'];

				// install configuration
				require_once(Pommo::$_baseDir.'classes/Pommo_User.php');
				$user = new Pommo_User();
				$user->save('admin', $_POST['admin_password']);
				
				// generate key to uniquely identify this installation
				$key = Pommo_Helper::makeCode(6);
				Pommo_Api::configUpdate(array('key' => $key),TRUE);
				
				Pommo::reloadConfig();
				
				// load configuration [depricated?], set message defaults, load templates
				require_once(Pommo::$_baseDir.
						'classes/Pommo_Helper_Messages.php');
				Pommo_Helper_Messages::resetDefault('all');
				
				// install templates
				$file = Pommo::$_baseDir.'sql/sql.templates.php';
				if(!Pommo_Install::parseSQL(false,$file))
					$logger->addErr('Error Loading Default Mailing Templates.');

				$logger->addMsg(Pommo::_T('Installation Complete! You may now login and setup poMMo.'));
				$logger->addMsg(Pommo::_T('Login Username: ') . 'admin');
				$logger->addMsg(Pommo::_T('Login Password: ') . $pass);
				
				$smarty->assign('installed', TRUE);
			} else {
				// INSTALL FAILED

				$dbo->debug(FALSE);

				// drop existing poMMo tables
				foreach (array_keys($dbo->table) as $key) {
					$table = $dbo->table[$key];
					$sql = 'DROP TABLE IF EXISTS ' . $table;
					$dbo->query($sql);
				}

				$logger->addErr('Installation failed! Enable debbuging to expose the problem.');
			}
		}
	} else {
		// __ FORM NOT VALID
		$logger->addMsg(Pommo::_T('Please review and correct errors with your submission.'));
	}
}
$smarty->assign($_POST);
$smarty->display('install.tpl');
Pommo::kill();

