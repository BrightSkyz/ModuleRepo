<?php
/*
 *	Made by Samerton (extended by BrightSkyz)
 *  https://github.com/NamelessMC/Nameless/
 *  NamelessMC version 2.0.0-pr4
 *
 *  License: MIT
 *
 *  Admin Update page
 */

// Can the user view the AdminCP?
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
		} else if(!$user->hasPermission('admincp.update')){
            // Can't view this page
            require(ROOT_PATH . '/404.php');
            die();
        }
	}
} else {
	// Not logged in
	Redirect::to(URL::build('/login'));
	die();
}

$page = 'admin';
$admin_page = 'update';

// Check for updates
$current_version = $queries->getWhere('settings', array('name', '=', 'nameless_version'));
$current_version = $current_version[0]->value;

$uid = $queries->getWhere('settings', array('name', '=', 'unique_id'));
$uid = $uid[0]->value;

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_URL, 'https://namelessmc.com/nl_core/nl2/stats.php?uid=' . $uid . '&version=' . $current_version);

$update_check = curl_exec($ch);

if(curl_error($ch)){
	$error = curl_error($ch);
} else {
	if($update_check == 'Failed'){
		$error = 'Unknown error';
	}
}

curl_close($ch);
?>
<!DOCTYPE html>
<html lang="<?php echo (defined('HTML_LANG') ? HTML_LANG : 'en'); ?>">
  <head>
    <!-- Standard Meta -->
    <meta charset="<?php echo (defined('LANG_CHARSET') ? LANG_CHARSET : 'utf-8'); ?>">
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
			  <h3><?php echo $language->get('admin', 'update'); ?></h3>
			  <hr>
			  <?php
			  if(!isset($error)){
				  if($update_check == 'None'){
					  echo '<div class="alert alert-success">' . $language->get('admin', 'up_to_date') . '</div>';
				  } else {
					  // Update database value to say when we last checked
					  $update_needed_id = $queries->getWhere('settings', array('name', '=', 'version_checked'));
					  $update_needed_id = $update_needed_id[0]->id;
					  $queries->update('settings', $update_needed_id, array(
						  'value' => date('U')
					  ));

					  echo '<p><strong>' . $language->get('admin', 'new_update_available') . '</strong></p>';
					  $update_check = json_decode($update_check);

                      // Update new version in database
                      $new_version_id = $queries->getWhere('settings', array('name', '=', 'new_version'));
                      if(count($new_version_id)) {
                          $new_version_id = $new_version_id[0]->id;
                          $queries->update('settings', $new_version_id, array(
                              'value' => $update_check->new_version
                          ));
                      } else {
                          $queries->create('settings', array(
                              'name' => 'new_version',
                              'value' => $update_check->new_version
                          ));
                      }

					  if(isset($update_check->urgent) && $update_check->urgent == 'true'){
						  echo '<div class="alert alert-danger">' . $language->get('admin', 'urgent') . '</div>';
						  $need_update = 'urgent';
					  } else {
                          $need_update = 'true';
                      }
                      // Update database values to say we need a version update
                      $update_needed_id = $queries->getWhere('settings', array('name', '=', 'version_update'));
                      $update_needed_id = $update_needed_id[0]->id;
                      $queries->update('settings', $update_needed_id, array(
                          'value' => $need_update
                      ));

					  echo '<ul><li>' . str_replace('{x}', Output::getClean($current_version), $language->get('admin', 'current_version_x')) . '</li>
					  <li>' . str_replace('{x}', Output::getClean($update_check->new_version), $language->get('admin', 'new_version_x')) . '</li></ul>';

					  echo '<h4>' . $language->get('admin', 'instructions') . '</h4>';
					  // Get instructions
					  $ch = curl_init();
					  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                      curl_setopt($ch, CURLOPT_URL, 'https://namelessmc.com/nl_core/nl2/instructions.php?uid=' . $uid . '&version=' . $current_version);

					  $instructions = curl_exec($ch);

					  if(curl_error($ch)){
					 	$instructions_error = curl_error($ch);
					  } else {
						if($instructions == 'Failed'){
							$instructions_error = 'Unknown error';
						}
					  }

					  curl_close($ch);

					  echo Output::getPurified($instructions);

					  echo '<hr />';

                      echo '<a href="https://namelessmc.com/nl_core/nl2/updates/' . str_replace(array('.', '-'), '', Output::getClean($update_check->new_version)) . '.zip" class="btn btn-primary">' . $language->get('admin', 'download') . '</a> ';
				      echo '<a href="' . URL::build('/admin/update_execute') . '" class="btn btn-info" onclick="return confirm(\'' . $language->get('admin', 'install_confirm') . '\');">' . $language->get('admin', 'update') . '</a>';
				  }
			  } else {
			  ?>
			  <div class="alert alert-danger">
			    <?php echo $language->get('admin', 'update_check_error'); ?><br />
				<?php echo $error; ?>
			  </div>
			  <?php
			  }
			  ?>
		<!-- START MODULEREPO -->
    		  <h3 style="display:inline;"><?php echo $language->get('admin', 'modules'); ?></h3>
    		  <br />
    		  <hr />
    		  <?php
    		  if(Session::exists('admin_modules')){
    			  echo Session::flash('admin_modules');
    		  }

    		  // Get all modules
    		  $modules = $queries->getWhere('modules', array('id', '<>', 0));

    		  foreach($modules as $module){
    			  if(isset($module_author)) unset($module_author);
    			  if(isset($module_version)) unset($module_version);
    			  if(isset($nameless_version)) unset($nameless_version);

    			  if(file_exists(ROOT_PATH . '/modules/' . $module->name . '/module.php')) require(ROOT_PATH . '/modules/' . $module->name . '/module.php');
    		  ?>
    		  <div class="row">
    		    <div class="col-md-9">
    		      <strong><?php echo htmlspecialchars($module->name); ?></strong> <?php if(isset($module_version)){ ?><small><?php echo $module_version; ?></small><?php } ?>
    			  <?php if(isset($module_author)){ ?></br><small><?php echo $language->get('admin', 'author'); ?> <?php echo $module_author; ?></small><?php } ?>
    			</div>
    			<div class="col-md-3">
    			  <span class="pull-right">
    			    <?php
					if (!file_exists(ROOT_PATH . '/modules/' . $module->name . '/modulerepo.json')) {
					?>
				    <a href="#" class="btn btn-info disabled"><i class="fa fa-lock" aria-hidden="true"></i></a>
					<?php
					} else {
						$url = "";
						$moduleRepoJson = json_decode(file_get_contents(ROOT_PATH . '/modules/' . $module->name . '/modulerepo.json'), true);
            		    $fileJson = json_decode(file_get_contents(ROOT_PATH . '/modules/ModuleRepo/repositories/' . $moduleRepoJson['repository_server'] . '.json'), true);
            		    $url_proto = $fileJson['protocol'];
            		    $ch = curl_init($url_proto . "://" . $moduleRepoJson['repository_server'] . "/modules/" . $moduleRepoJson['module_name'] . "/info.json");
                        curl_setopt($ch, CURLOPT_HEADER, 1);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
                        $infoJsonRaw = curl_exec($ch);
                        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        if (curl_errno($ch)) {
                            echo 'error:' . curl_error($ch);
                        }
            		    curl_close($ch);
            		    if ($httpcode != "200") {
            		?>
            		<a href="<?php echo URL::build('/admin/update/', 'action=module_update&m=' . $module->id); ?>" class="btn btn-danger"><?php echo $modulerepo_language->get('language', 'error'); ?></a>
            		<?php
            		    } else {
            		        $start = strpos($infoJsonRaw, "\r\n\r\n") + 4;
                            $infoJsonRaw = substr($infoJsonRaw, $start, strlen($infoJsonRaw) - $start);
                            $repositoryModuleInfo = json_decode($infoJsonRaw, true);
                            if ($module_version != $repositoryModuleInfo['latest_version']) {
					?>
					<form action="<?php echo URL::build('/admin/modulerepo'); ?>" method="post">
    					<input type="hidden" name="repositoryServer" value="<?php echo htmlspecialchars($moduleRepoJson['repository_server']); ?>" />
                        <input type="hidden" name="moduleName" value="<?php echo htmlspecialchars($moduleRepoJson['module_name']); ?>" />
                        <input type="hidden" name="moduleVersion" value="latest" />
        			    <input type="hidden" name="token" value="<?php echo Token::get(); ?>">
        			    <input type="hidden" name="update_page" value="do_redirect">
        				<input type="submit" name="confirm_submit" class="btn btn-warning" value="<?php echo $modulerepo_language->get('language', 'update'); ?>">
					</form>
					<?php
                            } else {
                    ?>
                    <a href="#" class="btn btn-success disabled"><?php echo $modulerepo_language->get('language', 'no_update_available'); ?></a>
                    <?php
                            }
						}
					}
					?>
    			  </span>
    			</div>
    		  </div>
    		  <hr />
    		  <?php
    		  }
    		  ?>
		<!-- END MODULEREPO -->
		    </div>
		  </div>
		</div>
	  </div>
    </div>

	<?php
	require(ROOT_PATH . '/modules/Core/pages/admin/footer.php');
	require(ROOT_PATH . '/modules/Core/pages/admin/scripts.php');
	?>

  </body>
</html>
