<?php
/*
 *	Made by Samerton
 *  https://github.com/NamelessMC/Nameless/
 *  NamelessMC version 2.0.0-pr3
 *
 *  License: MIT
 *
 *  Admin index page
 */

if($user->isLoggedIn()){
	if(!$user->canViewACP()){
		// No
		Redirect::to(URL::build('/'));
		die();
	} else {
		// Check the user has re-authenticated
		if(!$user->isAdmLoggedIn()){
			// They haven't, do so now
			Redirect::to(URL::build('/admin/auth'));
			die();
		} else {
            if(!$user->hasPermission('admincp.modules')){
                require(ROOT_PATH . '/404.php');
                die();
            }
        }
	}
} else {
	// Not logged in
	Redirect::to(URL::build('/login'));
	die();
}

$page = 'admin';
$admin_page = 'modules';

if(isset($_GET['action'])){
	if($_GET['action'] == 'install'){
		// Install any new modules
		$directories = glob(ROOT_PATH . '/modules/*' , GLOB_ONLYDIR);

		foreach($directories as $directory){
			$folders = explode('/', $directory);
			// Is it already in the database?
			$exists = $queries->getWhere('modules', array('name', '=', htmlspecialchars($folders[count($folders) - 1])));
			if(!count($exists)){
				// No, add it now
				$queries->create('modules', array(
					'name' => htmlspecialchars($folders[count($folders) - 1])
				));

				// Require installer if necessary
				if(file_exists(ROOT_PATH . '/modules/' . $folders[count($folders) - 1] . '/install.php')){
					require(ROOT_PATH . '/modules/' . $folders[count($folders) - 1] . '/install.php');
				}
			}
		}

		Log::getInstance()->log(Log::Action('admin/module/install'));

		Session::flash('admin_modules', '<div class="alert alert-success">' . $language->get('admin', 'modules_installed_successfully') . '</div>');

		Redirect::to(URL::build('/admin/modules'));

		die();

	} else if($_GET['action'] == 'enable'){
		// Enable a module
		if(!isset($_GET['m']) || !is_numeric($_GET['m']) || $_GET['m'] == 1) die('Invalid module!');


		$queries->update('modules', $_GET['m'], array(
			'enabled' => 1
		));

		Log::getInstance()->log(Log::Action('admin/module/enable'));
		
		// Get module name
		$name = $queries->getWhere('modules', array('id', '=', $_GET['m']));
		$name = htmlspecialchars($name[0]->name);

		// Cache
		$cache->setCache('modulescache');

		// Get existing enabled modules
		$enabled_modules = $cache->retrieve('enabled_modules');

		$modules = array();

		foreach($enabled_modules as $module){
			$modules[] = $module;
		}

		$modules[] = array(
			'name' => $name,
			'priority' => 4
		);

		// Store
		$cache->store('enabled_modules', $modules);

		Session::flash('admin_modules', '<div class="alert alert-success">' . $language->get('admin', 'module_enabled') . '</div>');

		Redirect::to(URL::build('/admin/modules'));

		die();

	} else if($_GET['action'] == 'disable'){
		// Disable a module
		if(!isset($_GET['m']) || !is_numeric($_GET['m']) || $_GET['m'] == 1) die('Invalid module!');


		$queries->update('modules', $_GET['m'], array(
			'enabled' => 0
		));

		Log::getInstance()->log(Log::Action('admin/module/disable'));

		// Get module name
		$name = $queries->getWhere('modules', array('id', '=', $_GET['m']));
		$name = htmlspecialchars($name[0]->name);

		// Cache
		$cache->setCache('modulescache');

		// Get existing enabled modules
		$enabled_modules = $cache->retrieve('enabled_modules');

		$modules = array();

		foreach($enabled_modules as $module){
			if($module['name'] != $name) $modules[] = $module;
		}

		// Store
		$cache->store('enabled_modules', $modules);

		Session::flash('admin_modules', '<div class="alert alert-success">' . $language->get('admin', 'module_disabled') . '</div>');

		Redirect::to(URL::build('/admin/modules'));

		die();

	} else if($_GET['action'] == 'delete'){
		// Disable a module
		if(!isset($_GET['m']) || !is_numeric($_GET['m']) || $_GET['m'] == 1) die('Invalid module!');


		$queries->update('modules', $_GET['m'], array(
			'enabled' => 0
		));

		Log::getInstance()->log(Log::Action('admin/module/delete'));

		// Get module name
		$name = $queries->getWhere('modules', array('id', '=', $_GET['m']));
		$name = htmlspecialchars($name[0]->name);

		// Cache
		$cache->setCache('modulescache');

		// Get existing enabled modules
		$enabled_modules = $cache->retrieve('enabled_modules');

		$modules = array();

		foreach($enabled_modules as $module){
			if($module['name'] != $name) $modules[] = $module;
		}

		// Store
		$cache->store('enabled_modules', $modules);
		
		// Delete folder containing the module
		$dir = ROOT_PATH . '/modules/' . $name;
        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file) {
            if ($file->isDir()){
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
        
        // Delete module from database
        $queries->delete('modules', array('id', '=', $_GET['m']));
		
		// Reload list of installed modules because we just removed one from file system
	    $directories = glob(ROOT_PATH . '/modules/*' , GLOB_ONLYDIR);
		foreach($directories as $directory){
			$folders = explode('/', $directory);
			// Is it already in the database?
			$exists = $queries->getWhere('modules', array('name', '=', htmlspecialchars($folders[count($folders) - 1])));
			if(!count($exists)){
				// No, add it now
				$queries->create('modules', array(
					'name' => htmlspecialchars($folders[count($folders) - 1])
				));
				// Require installer if necessary
				if(file_exists(ROOT_PATH . '/modules/' . $folders[count($folders) - 1] . '/install.php')){
					require(ROOT_PATH . '/modules/' . $folders[count($folders) - 1] . '/install.php');
				}
			}
		}

		Session::flash('admin_modules', '<div class="alert alert-success">' . $modulerepo_language->get('language', 'module_deleted') . '</div>');

		Redirect::to(URL::build('/admin/modules'));

		die();

	}
}

require(ROOT_PATH . '/modules/ModuleRepo/pages/modules.view.php');

?>