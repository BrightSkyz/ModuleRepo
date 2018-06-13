<?php 
/*
 *	Made by BrightSkyz
 *  https://github.com/brightskyz
 *  NamelessMC version 2.0.0-pr4
 *
 *  License: MIT
 *
 *  ModuleRepo for NamelessMC
 */

// Custom language
$modulerepo_language = new Language(ROOT_PATH . '/modules/ModuleRepo/language', LANGUAGE);

// Define URLs which belong to this module
$pages->add('ModuleRepo', '/admin/modulerepo', 'pages/modulerepo.php');
$pages->add('ModuleRepo', '/admin/modules', 'pages/modules.php');
$pages->add('ModuleRepo', '/admin/update', 'pages/update.php');

// Add link to admin sidebar
if(!isset($admin_sidebar)) $admin_sidebar = array();
/*$admin_sidebar['modulerepo'] = array(
	'title' => $modulerepo_language->get('language', 'modulerepo_title'),
	'url' => URL::build('/admin/modulerepo')
);*/