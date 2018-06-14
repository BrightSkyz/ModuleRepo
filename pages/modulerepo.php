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

// Can the user view the AdminCP?
if ($user->isLoggedIn()) {
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
		}
	}
} else {
	// Not logged in
	Redirect::to(URL::build('/login'));
	die();
}

$page = 'admin';
$admin_page = 'modulerepo';

$success = false;
$message = "";

if (Input::exists()) {
	if (Token::check(Input::get('token'))) {
		$repositoryServer = "";
		if (isset($_POST['repositoryServer'])) {
			$repositoryServer = $_POST['repositoryServer'];
		}
		$moduleName = "";
		if (isset($_POST['moduleName'])) {
		    $moduleName = $_POST['moduleName'];
		}
		$moduleVersion = "";
		if (isset($_POST['moduleVersion'])) {
		    if ($_POST['moduleVersion'] == "") {
		        $moduleVersion = "latest";
		    } else {
		        $moduleVersion = $_POST['moduleVersion'];
		    }
		}
		if ($repositoryServer != "" && $moduleName != "" && $moduleVersion != "") {
		    // Get the URI to send request to
		    $url = "";
		    $fileJson = json_decode(file_get_contents(ROOT_PATH . '/modules/ModuleRepo/repositories/' . $repositoryServer . '.json'), true);
		    $url_proto = $fileJson['protocol'];
		    $ch = curl_init($url_proto . "://" . $repositoryServer . "/modules/" . $moduleName . "/info.json");
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
            $infoJsonRaw = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (curl_errno($ch)) {
                die('error:' . curl_error($ch));
            }
		    if ($httpcode != "200") {
                curl_close($ch);
                // Set the message
		        $success = false;
                $message = $modulerepo_language->get('language', 'module_fetch_failed');
		    } else {
                curl_close($ch);
		        $start = strpos($infoJsonRaw, "\r\n\r\n") + 4;
                $infoJsonRaw = substr($infoJsonRaw, $start, strlen($infoJsonRaw) - $start);
                $repositoryModuleInfo = json_decode($infoJsonRaw, true);
    		    if ($moduleVersion == "latest") {
    		        $url = $url_proto . "://" . $repositoryServer . "/modules/" . $moduleName . "/" . $repositoryModuleInfo['latest_version'] . ".zip";
    		    } else {
    		        $url = $url_proto . "://" . $repositoryServer . "/modules/" . $moduleName . "/" . $moduleVersion . ".zip";
    		    }
    		    // If we are not confirming the module to install
    		    if (isset($_POST['confirm_submit'])) {
    		        $messageVersion = $moduleVersion;
    		        if ($moduleVersion == "latest") {
    		            $messageVersion = $repositoryModuleInfo['latest_version'] . " (Latest)";
    		        } else if ($moduleVersion === $repositoryModuleInfo['latest_version']) {
    		            $messageVersion = $moduleVersion . " (" . $modulerepo_language->get('language', 'latest') . ")";
    		        }
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_HEADER, 1);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_NOBODY, 1);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                    $raw_file_data = curl_exec($ch);
                    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    if (curl_errno($ch)) {
                        echo 'Error while getting the zip:' . curl_error($ch);
                    }
                    if ($httpcode == "200") {
        		        $success = true;
        		        $messageChanges = array(
                            '{x}' => $moduleName,
                            '{x2}' => $messageVersion,
                            '{x3}' => $repositoryServer
                        );
                        $message = str_replace(array_keys($messageChanges), array_values($messageChanges), $modulerepo_language->get('language', 'are_you_sure_install'));
                    } else {
                        $success = false;
                        $message = $modulerepo_language->get('language', 'module_version_fetch_failed');
                    }
                } else {
        		    // Send request and download the zip
        		    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_HEADER, 1);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                    $raw_file_data = curl_exec($ch);
                    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    if (curl_errno($ch)) {
                        die('Error while getting the zip:' . curl_error($ch));
                    }
                    if ($httpcode == "200") {
                        $start = strpos($raw_file_data, "\r\n\r\n") + 4;
                        $raw_file_data = substr($raw_file_data, $start, strlen($raw_file_data) - $start);
                        // Save zip file
                        file_put_contents(ROOT_PATH . '/modules/' . $moduleName . '.zip', $raw_file_data);
                        // Prepare to extract zip file
                        $file = ROOT_PATH . '/modules/' . $moduleName . '.zip';
                        $path = pathinfo(realpath($file), PATHINFO_DIRNAME);
                        $zip = new ZipArchive;
                        $result = $zip->open($file);
                        if ($result === TRUE) {
                            // Preform zip extraction
                            $zip->extractTo($path);
                            $zip->close();
                            unlink($file);
                            // Install any new modules since we just added one
                    		$directories = glob(ROOT_PATH . '/modules/*' , GLOB_ONLYDIR);

                    		foreach ($directories as $directory) {
                    			$folders = explode('/', $directory);
                    			// Is it already in the database
                    			$exists = $queries->getWhere('modules', array('name', '=', htmlspecialchars($folders[count($folders) - 1])));
                    			if(!count($exists)){
                    			    // Check if just installed by ModuleRepo and add the json file if it was
                    			    if (strtolower(htmlspecialchars($folders[count($folders) - 1])) == strtolower($moduleName)) {
                    			        file_put_contents(realpath(ROOT_PATH . '/modules/' . htmlspecialchars($folders[count($folders) - 1]) . '/modulerepo.json'), json_encode(array(
                                            'module_name' => $moduleName,
                                            'repository_server' => $repositoryServer
                                        )));
                    			    }
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
                    		// Redrect back to the other page
                    		if (isset($_POST['update_page']) && $_POST['update_page'] == "do_redirect") {
                        		// Go back to update page
                                Session::flash('admin_modules', '<div class="alert alert-success">' . $modulerepo_language->get('language', 'module_updated_via_modulerepo') . '</div>');
                            	Redirect::to(URL::build('/admin/update'));
                            	die();
                    		} else {
                    		    // Go back to modules page
                                Session::flash('admin_modules', '<div class="alert alert-success">' . $modulerepo_language->get('language', 'module_installed_via_modulerepo') . '</div>');
                            	Redirect::to(URL::build('/admin/modules'));
                            	die();
                    		}
                        } else {
                            $success = false;
                            $message = $modulerepo_language->get('language', 'zip_extraction_failed');
                        }
                    } else {
                        $success = false;
                        $message = $modulerepo_language->get('language', 'zip_download_failed');
                    }
                    curl_close($ch);
    		    }
		    }
        }

		/*Redirect::to(URL::build('/admin/modulerepo'));
		die();*/

	} else {
		$error = $language->get('admin', 'invalid_token');
	}
}

?>
<!DOCTYPE html>
<html>
  <head>
    <!-- Standard Meta -->
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">

	<?php
	$title = $language->get('admin', 'admin_cp');
	require(ROOT_PATH . '/core/templates/admin_header.php');
	?>

  </head>
  <body>
    <?php require(ROOT_PATH . '/modules/Core/pages/admin/navbar.php'); ?>
	<div class="container">
	  <div class="row">
	    <div class="col-md-3">
		  <?php require(ROOT_PATH . '/modules/Core/pages/admin/sidebar.php'); ?>
		</div>
		<div class="col-md-9">
		  <div class="card">
		    <div class="card-block">
			<h3><?php echo $modulerepo_language->get('language', 'modulerepo_title'); ?></h3>
			<hr />
			<?php
			if (!is_writable(ROOT_PATH . '/modules')) {
			?>
			<div class="alert alert-danger" role="alert">
            <?php echo $modulerepo_language->get('language', 'make_modules_writeable'); ?>
            </div>
			<?
			} else {
			?>
			<?php
			if ($message != "") {
			?>
			<div class="alert alert-<?php if ($success) { echo 'success'; } else { echo 'danger'; } ?>" role="alert">
            <?php echo $message; ?>
            </div>
			<?
			}
			?>
			<form action="" method="post">
			<?php if ((!$success && isset($_POST)) || (!(Input::exists() && Token::check(Input::get('token'))) && !isset($_POST['confirm_submit']))) { ?>
			  <div class="form-group">
			    <label for="inputRepositoryServer"><?php echo $modulerepo_language->get('language', 'repository_server'); ?></label>
				<select name="repositoryServer" class="form-control" id="inputRepositoryServer">
			      <?php
			      $path = ROOT_PATH . '/modules/ModuleRepo/repositories';
                  $files = array_diff(scandir($path), array('.', '..'));
                  foreach ($files as $file) {
                    $fileJson = json_decode(file_get_contents(ROOT_PATH . '/modules/ModuleRepo/repositories/' . $file), true);
                  ?>
                  <option value="<?php echo $fileJson['address']; ?>"><?php echo $fileJson['name']; ?> (<?php echo $fileJson['address']; ?>)</option>
                  <?php
                  }
                  ?>
				</select>
			  </div>
			  <div class="form-group">
			    <label><?php echo $modulerepo_language->get('language', 'module_link'); ?></label>
			    <div class="input-group">
                  <input type="text" name="moduleName" class="form-control" placeholder="module-name" />
                  <span class="input-group-addon" style="border-left: 0; border-right: 0;">@</span>
                  <input type="text" name="moduleVersion" class="form-control" placeholder="latest" />
                </div>
			  </div>
			  <div class="form-group">
			    <input type="hidden" name="token" value="<?php echo Token::get(); ?>">
				<input type="submit" name="confirm_submit" class="btn btn-primary" value="<?php echo $modulerepo_language->get('language', 'install_module'); ?>">
			  </div>
			<?php } else { ?>
			  <div class="form-group">
			    <input type="hidden" name="repositoryServer" value="<?php if (isset($_POST['repositoryServer'])) { echo htmlspecialchars($_POST['repositoryServer']); } ?>" />
                <input type="hidden" name="moduleName" value="<?php if (isset($_POST['moduleName'])) { echo htmlspecialchars($_POST['moduleName']); } ?>" />
                <input type="hidden" name="moduleVersion" value="<?php if (isset($_POST['moduleVersion'])) { echo htmlspecialchars($_POST['moduleVersion']); } ?>" />
			    <input type="hidden" name="token" value="<?php echo Token::get(); ?>">
			    <a class="btn btn-danger" href="<?php
			        if (isset($_POST['update_page']) && $_POST['update_page'] == "do_redirect") {
			            echo URL::build('/admin/update');
			        } else {
			            echo URL::build('/admin/modulerepo');
			        }
			    ?>"><?php echo $modulerepo_language->get('language', 'cancel_install'); ?></a>
				<input type="submit" class="btn btn-primary" value="<?php echo $modulerepo_language->get('language', 'install_module'); ?>">
			  </div>
			<?php } ?>
			</form>
			<?php
			}
			?>
		    </div>
		  </div>
		</div>
	  </div>
    </div>

	<?php require(ROOT_PATH . '/modules/Core/pages/admin/footer.php'); ?>

    <?php require(ROOT_PATH . '/modules/Core/pages/admin/scripts.php'); ?>

  </body>
</html>
