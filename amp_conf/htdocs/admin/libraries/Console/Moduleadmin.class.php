<?php
namespace FreePBX\Console\Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
class Moduleadmin extends Command {

	protected function configure(){
		$this->setName('ma')
		->setAliases(array('modadmin'))
		->setDescription('Module Administration')
		->setDefinition(array(
			new InputOption('force', 'f', InputOption::VALUE_NONE, 'Force operation (skips dependency and status checks) <warning>WARNING:</warning> Use at your own risk, modules have dependencies for a reason!'),
			new InputOption('debug', 'd', InputOption::VALUE_NONE, 'Output debug messages to the console (be super chatty)'),
			new InputOption('repo', 'R', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Set the Repos. -R Commercial -R Contributed'),
			new InputArgument('args', InputArgument::IS_ARRAY, 'arguments passed to module admin, this is s stopgap', null),))
		//->setHelp('fwconsole ma -f -R commmercial -R Contributed install module1 module2 module3');
		->setHelp($this->showHelp());
	}

	protected function execute(InputInterface $input, OutputInterface $output){
		$this->out = $output;
		$args = $input->getArgument('args');
		if ($input->getOption('debug')) {
			$this->DEBUG = True;
		} else {
			$this->DEBUG = False;
		}
		if ($input->getOption('force')) {
			$this->force = True;
			$$this->no_warnings = True;
			if($this->DEBUG){$output->writeln('Force Enabled');}
		} else {
			$this->force = False;
			$this->no_warnings = False;
			if($this->DEBUG){$output->writeln('Force Disabled');}
		}
		$repos = $input->getOption('repo');
	    if($repos){

		}

		$output->writeln($text);
		if($args){
			if($this->DEBUG){print_r($args);}
			$this->handleArgs($args);
		}
	}

	private function enableRepo($repo){
		$modulef = \module_functions::create();
		$remote = $modulef->get_remote_repos();
		$modulef->set_active_repo(strtolower($repo),1);
		if(!in_array($repo,$remote)) {
			$this->out->writeln("Repo ".$repo." successfully enabled, but was not found in the remote list");
		}else{
			$this->out->writeln("Repo ".$repo." successfully enabled");
		}
	}

	private function disableRepo($repo){
		$modulef = \module_functions::create();
		$modulef->set_active_repo(strtolower($repo),0);
		$remote = $modulef->get_remote_repos();

		if(!in_array($repo,$remote)) {
			$this->out->writeln("Repo ".$repo." successfully disabled, but was not found in the remote list");
		} else {
			$this->out->writeln("Repo ".$repo." successfully disabled");
		}
	}

	private function doReload() {
		$result = do_reload();
		if ($result['status'] != true) {
			$this->out->writeln("Error(s) have occured, the following is the retrieve_conf output:");
			$retrieve_array = explode('<br/>',$result['retrieve_conf']);
			foreach ($retrieve_array as $line) {
				$this->out->writeln($line);
			}
		}else{
			$this->out->writeln($result['message']);
		}
	}

	private function doInstall($modulename, $force) {
		$this->getIncludes();
		$module = \module_functions::create();
		if (is_array($errors = $module->install($modulename, $this->force))) {
			if(!empty($module->modDepends)) {
				$this->out->writeln("Detected Unmet Dependency..Attempting to install it");
				foreach($module->modDepends as $mod) {
					if($modulename == $mod) {
						continue; //skip self?
					}
					$this->getIncludes(); //get functions from other modules, in case we need them here
					$this->out->writeln("Installing $mod...");
					$status = $this->doInstall($mod, $this->force);
					if($status !== true && $mod != 'framework') {
						if (($status1 = $this->doDownload($mod, $this->force)) === true) {
							if (($status2 = $this->doInstall($mod, $this->force)) !== true) {
								$this->out->writeln("Unable to install module ${modulename}'s dependency ${mod}:");
								$this->out->writeln(' - '.implode("\n - ",$status2));
								continue;
								//exit;
							}
						} else {
							$this->out->writeln("Unable to download module ${modulename}'s dependency ${mod}:");
							$this->out->writeln(' - '.implode("\n - ",$status1));
							continue;
							//exit;
						}
					}
				}
				$this->doInstall($modulename, $this->force);
			} else {
				if($module->notFound) {
					if (($status1 = $this->doDownload($modulename, $this->force)) === true) {
						if (($status2 = $this->doInstall($modulename, $this->force)) !== true) {
							$this->out->writeln("Unable to install module ${modulename}:");
							$this->out->writeln(' - '.implode("\n - ",$status2));
							//exit;
						}
					}
				} else {
					$this->out->writeln("The following error(s) occurred:");
					$this->out->writeln(' - '.implode("\n - ",$errors));
					//exit(2);
				}

			}
		} else {
			$this->out->writeln("Module ".$modulename." successfully installed");
		}
		return true;
	}

	private function doDownload($modulename, $force) {
		global $modulexml_path;
		global $modulerepository_path;
		$modulef = \module_functions::create();
		if (is_array($errors = $modulef->download($modulename, $this->force, 'download_progress', $modulerepository_path, $modulexml_path))) {
			$this->out->writeln("The following error(s) occured:");
			$this->out->writeln(' - '.implode("\n - ",$errors));
			exit(2);
		} else {
			$this->out->writeln("Module ".$modulename." successfully downloaded");
		}
		return true;
	}

	private function doDelete($modulename, $force) {
		$this->getIncludes();
		$module = \module_functions::create();
		if (is_array($errors = $module->delete($modulename, $this->force))) {
			$this->out->writeln("The following error(s) occured:");
			$this->out->writeln(' - '.implode("\n - ",$errors));
			exit(2);
		} else {
			$this->out->writeln("Module ".$modulename." successfully deleted");
		}
	}

	private function doUninstall($modulename, $force) {
		$this->getIncludes();
		$modulef = \module_functions::create();
		if (is_array($errors = $modulef->uninstall($modulename, $this->force))) {
			$this->out->writeln("The following error(s) occured:");
			$this->out->writeln(' - '.implode("\n - ",$errors));
			exit(2);
		} else {
			$this->out->writeln("Module ".$modulename." successfully uninstalled");
		}
	}

	private function doUpgrade($modulename, $force) {
		// either will exit() if there's a problem
		$this->doDownload($modulename, $this->force);
		$this->doInstall($modulename, $this->force);
	}

	private function doInstallLocal($force) {
		$modulef = \module_functions::create();
		//refresh module cache
		$modulef->getinfo(false,false,true);
		$module_info=$modulef->getinfo(false, MODULE_STATUS_NOTINSTALLED);
		foreach ($module_info as $module) {
			if ($module['rawname'] != 'builtin') {
				$modules[] = $module['rawname'];
			}
		}
		if (in_array('core', $modules)){
			$this->out->writeln("Installing core...");
			$this->doInstall('core', $this->force);
		}
		if (count($modules) > 0) {
			$this->out->writeln("Installing: ".implode(', ',$modules));
			foreach ($modules as $module => $name) {
				if (($name != 'core')){//we dont want to reinstall core
					$this->getIncludes(); //get functions from other modules, in case we need them here
					$this->out->writeln("Installing $name...");
					$this->doInstall($name, $this->force);
				}
			}
			$this->out->writeln("Done. All modules installed.");
		} else {
			$this->out->writeln("All modules up to date.");
		}
	}

	/**
	 * @param bool Controls if a simple (names only) or extended (array of name,versions) array is returned
	 */
	private function getInstallableModules($extarray = false) {
		$modulef = \module_functions::create();
		$modules_online = $modulef->getonlinexml();
		$module_info=$modulef->getinfo(false);
		$modules_installable = array();
		global $active_repos;
		$this->check_active_repos();
		foreach ($modules_online as $name) {
			// Theory: module is not in the defined repos, and since it is not local (meaning we loaded it at some point) then we
			//         don't show it. Exception, if the status is BROKEN then we should show it because it was here once.
			//
			if ((!isset($active_repos[$modules_online[$name['rawname']]['repo']]) || !$active_repos[$modules_online[$name['rawname']]['repo']]) && (!isset($module_info[$name['rawname']]) || $module_info[$name['rawname']]['status'] == MODULE_STATUS_NOTINSTALLED)) {
				continue;
			}
			if ((!isset($module_info[$name['rawname']]['status'])) || ($module_info[$name['rawname']]['status'] == MODULE_STATUS_NEEDUPGRADE) || ($module_info[$name['rawname']]['status'] == MODULE_STATUS_NOTINSTALLED)){
				$modules_installable[]=$name['rawname'];
			}
		}
		return $modules_installable;
	}

	/**
	 * @param bool Controls if a simple (names only) or extended (array of name,versions) array is returned
	 */
	private function getUpgradableModules($extarray = false) {
		$modulef = \module_functions::create();
		$modules_local = $modulef->getinfo(false, MODULE_STATUS_ENABLED);
		$modules_online = $modulef->getonlinexml();
		$modules_upgradable = array();
		global $active_repos;
		$this->check_active_repos();
		foreach (array_keys($modules_local) as $name) {
			if (isset($modules_online[$name])) {
				if (version_compare_freepbx($modules_local[$name]['version'], $modules_online[$name]['version']) < 0) {
					if ($extarray) {
						$modules_upgradable[] = array(
							'name' => $name,
							'local_version' => $modules_local[$name]['version'],
							'online_version' => $modules_online[$name]['version'],
						);
					} else {
						$modules_upgradable[] = $name;
					}
				}
			}
		}
		return $modules_upgradable;
	}

	private function doUpgradeAll($force) {
		$modules = $this->getUpgradableModules();
		if (count($modules) > 0) {
			$this->out->writeln("Upgrading: ".implode(', ',$modules));
			foreach ($modules as $modulename) {
				$this->out->writeln("Upgrading $modulename..");
				$this->doUpgrade($modulename, $this->force);
			}
			$this->out->writeln("All upgrades done!");
		} else {
			$this->out->writeln("Up to date.");
		}
	}

	private function mirrorrepo(){
		doInstallAll(true);
		$modulef = \module_functions::create();
		$modules_online = $modulef->getonlinexml();
		$modules_local = $modulef->getinfo();
		unset($modules_local['builtin']); //builtin never gets deleted, so remove it from the list
		foreach ($modules_local as $localmod){
			if (!$modulef->getonlinexml($localmod['rawname'])){
				$this->doDelete($localmod['rawname'],1);
			}
		}
	}

	private function showi18n($modulename) {
		//special case core so that we have everything we need for localizations
		switch($modulename) {
			case 'core':
				$modules = $this->module_getinfo();
			break;
			default:
				$modules = $this->module_getinfo($modulename);
			break;
		}

		$modulesProcessed = array();
		foreach ($modules as $rawname => $mod) {
			if (!isset($modules[$rawname])) {
				fatal($rawname.' not found');
			}

			if (!in_array($modules[$rawname]['name'], $modulesProcessed['name'])) {
				$modulesProcessed['name'][] = $modules[$rawname]['name'];
				if (isset($modules[$rawname]['name'])) {
					echo "# name:\n_(\"".$modules[$rawname]['name']."\");\n";
				}
			}
			if (!in_array($modules[$rawname]['category'], $modulesProcessed['category'])) {
				$modulesProcessed['category'][] = $modules[$rawname]['category'];
				if (isset($modules[$rawname]['category'])) {
					echo "# category:\n_(\"".str_replace("\n","",$modules[$rawname]['category'])."\");\n";
				}
			}
			if (!in_array($modules[$rawname]['description'], $modulesProcessed['description'])) {
				$moduleProcessed['description'][] = $modules[$rawname]['description'];
				if (isset($modules[$rawname]['description'])) {
					echo "# description:\n_(\"".trim(str_replace("\n","",$modules[$rawname]['description']))."\");\n";
				}
			}
			if (isset($modules[$rawname]['menuitems'])) {
				foreach ($modules[$rawname]['menuitems'] as $key => $menuitem) {
					if (!in_array($menuitem, $modulesProcessed['menuitem'])) {
						$modulesProcessed['menuitem'][] = $menuitem;
						echo "# $key:\n_(\"$menuitem\");\n";
					}
				}
			}
		}

		//get our settings
		$freepbx_conf = \freepbx_conf::create();
		$conf = $freepbx_conf->get_conf_settings();
		$settingsProcessed = array();
		foreach ($conf as $keyword => $settings) {
			//we don't need hidden settings as the user never sees them
			if ($settings['hidden'] != true && ($rawname == 'framework' && $settings['module'] == '') || $settings['module'] == $modulename) {
				if (!in_array($settings['name'], $settingsProcessed['name'])) {
					$settingsProcessed['name'][] = $settings['name'];
					echo "# Setting name - $keyword:\n_(\"".$settings['name']."\");\n";
				}
				if (!in_array($settings['category'], $settingsProcessed['category'])) {
					$settingsProcessed['category'][] = $settings['category'];
					echo "# Setting category - $keyword:\n_(\"".$settings['category']."\");\n";
				}
				if (!in_array($settings['description'], $settingsProcessed['description'])) {
					$settingsProcessed['description'][] = $settings['description'];
					echo "# Setting description - $keyword:\n_(\"".$settings['description']."\");\n";
				}
			}
		}
	}

	private function listDisabled(){
		$modulef = \module_functions::create();
		$modules = $modulef->getinfo(false, MODULE_STATUS_DISABLED);
		return array_keys($modules);
	}

	private function listEnabled(){
		$modulef = \module_functions::create();
		$modules = $modulef->getinfo(false, MODULE_STATUS_ENABLED);
		return array_keys($modules);
	}

	private function showCheckDepends($modulename) {
		$modulef = \module_functions::create();
		$modules = $modulef->getinfo($modulename);
		if (!isset($modules[$modulename])) {
			fatal($modulename.' not found');
		}
		if (($errors = $modulef->checkdepends($modules[$modulename])) !== true) {
			$this->out->writeln("The following dependencies are not met:");
			$this->out->writeln(' - '.implode("\n - ",$errors));
			exit(1);
		} else {
			$this->out->writeln("All dependencies met for module ".$modulename);
		}
	}

	private function showEngine() {
		$engine = engine_getinfo();
		foreach ($engine as $key=>$value) {
			$this->out->writeln(str_pad($key,15,' ',STR_PAD_LEFT).': '.$value);
		}
	}

	private function setPerms() {
		//If were running as root, attempt to set proper permissions
		//on the freshly installed files. For simplicity, we run the
		// freepbx default utility for setting freepbx perms
	    global $amp_conf;
	    $current_user = posix_getpwuid(posix_geteuid());
		if ($current_user['uid'] === 0) {
			system($amp_conf['AMPBIN'] . '/fwconsole chown');
		}
	}
	private function check_active_repos() {
		global $active_repos;
		$modulef = \module_functions::create();
		if (!isset($active_repos)) {
			$active_repos = $modulef->get_active_repos();
			$list = implode(',',array_keys($active_repos));
				if (!$this->no_warnings) {
					$this->out->writeln("no repos specified, using: [$list] from last GUI settings");
					$this->out->writeln("");
				}
		}
	}

	private function doInstallAll($force) {
		$this->doUpgradeAll(true);
		$modules = $this->getInstallableModules();
		if (in_array('core', $modules)){
			$this->out->writeln("Installing core...");
			$this->doDownload('core', $this->force);
			$this->doInstall('core', $this->force);
		}
		if (count($modules) > 0) {
			$this->out->writeln("Installing: ".implode(', ',$modules));
			foreach ($modules as $module => $name) {
				if (($name != 'core')){//we dont want to reinstall core
					$this->getIncludes(); //get functions from other modules, in case we need them here
					$this->out->writeln("Installing $name...");
					$this->doDownload($name, $this->force);
					$this->doInstall($name, $this->force);
				}
			}
			$this->out->writeln("Done. All modules installed.");
		} else {
			$this->out->writeln("All modules up to date.");
		}
	}

	private function getIncludes(){
		$modulef = \module_functions::create();
		$active_modules = $modulef->getinfo(false, MODULE_STATUS_ENABLED);
		if(is_array($active_modules)){
			foreach($active_modules as $key => $module) {
				//include module functions
				if (is_file("modules/{$key}/functions.inc.php")) {
					require_once("modules/{$key}/functions.inc.php");
				}
			}
		}
	}

	private function showInfo($modulename) {
		function recursive_print($array, $parentkey = '', $level=0) {
			foreach ($array as $key => $value) {
				if (is_array($value)) {
					// check if there is a numeric key in the sub-array, if so, we don't print the title
					if (!isset($value[0])) {
						$this->out->writeln(str_pad($key,15+($level * 3),' ',STR_PAD_LEFT).': ');
					}
					recursive_print($value, $key, $level + 1);
				} else {
					if (is_numeric($key)) {
						// its just multiple parent keys, so we don't indent, and print the parentkey instead
						$this->out->writeln(str_pad($parentkey,15+(($level-1) * 3),' ',STR_PAD_LEFT).': '.$value);
					} else {
						if ($key == 'status') {
							switch ($value) {
								case MODULE_STATUS_NOTINSTALLED: $value = 'Not Installed'; break;
								case MODULE_STATUS_NEEDUPGRADE: $value = 'Disabled; Needs Upgrade'; break;
								case MODULE_STATUS_ENABLED: $value = 'Enabled'; break;
								case MODULE_STATUS_DISABLED: $value = 'Disabled'; break;
								case MODULE_STATUS_BROKEN: $value = 'Broken'; break;
							}
						}
						$this->out->writeln(str_pad($key,15+($level * 3),' ',STR_PAD_LEFT).': '.$value);
					}
				}
			}
		}
		$modulef = \module_functions::create();
		$modules = $modulef->getinfo($modulename);
		if (!isset($modules[$modulename])) {
			fatal($modulename.' not found');
		}

		recursive_print($modules[$modulename]);
	}

	private function showList($online = false) {
		global $amp_conf;
		$modulef = \module_functions::create();
		$modules_local = $modulef->getinfo(false,false,true);
		$modules = $modules_local;
		global $active_repos;
		$this->check_active_repos();
		if ($online) {
			$modules_online = $modulef->getonlinexml();
			if (isset($modules_online)) {
				$modules += $modules_online;
			}
		}
		ksort($modules);
		$rows = array();
		foreach (array_keys($modules) as $name) {
			$status_index = isset($modules[$name]['status'])?$modules[$name]['status']:'';
			// Don't include modules not in our repo unless they are locally installed already
			if ((!isset($active_repos[$modules[$name]['repo']]) || !$active_repos[$modules[$name]['repo']]) && $status_index != MODULE_STATUS_BROKEN && !isset($modules_local[$name])) {
				continue;
			}
			switch ($status_index) {
				case MODULE_STATUS_NOTINSTALLED:
					if (isset($modules_local[$name])) {
						$status = 'Not Installed (Locally available)';
					} else {
						$status = 'Not Installed (Available online: '.$modules_online[$name]['version'].')';
					}
				break;
				case MODULE_STATUS_DISABLED:
					$status = 'Disabled';
				break;
				case MODULE_STATUS_NEEDUPGRADE:
					$status = 'Disabled; Pending upgrade to '.$modules[$name]['version'];
				break;
				case MODULE_STATUS_BROKEN:
					$status = 'Broken';
				break;
				default:
					// check for online upgrade
					if (isset($modules_online[$name]['version'])) {
						$vercomp = version_compare_freepbx($modules[$name]['version'], $modules_online[$name]['version']);
						if ($vercomp < 0) {
							$status = 'Online upgrade available ('.$modules_online[$name]['version'].')';
						} else if ($vercomp > 0) {
							$status = 'Newer than online version ('.$modules_online[$name]['version'].')';
						} else {
							$status = 'Enabled and up to date';
						}
					} else if (isset($modules_online)) {
						// we're connected to online, but didn't find this module
						$status = 'Enabled; Not available online';
					} else {
						$status = 'Enabled';
					}
				break;
			}
			$module_version = isset($modules[$name]['dbversion'])?$modules[$name]['dbversion']:'';
			array_push($rows,array($name, $module_version, $status));
		}
		$table = new Table($this->out);
		$table
			->setHeaders(array('Module', 'Version', 'Status'))
			->setRows($rows);
		$table->render();
	}

	private function refreshsignatures() {
		$mf = \module_functions::create();
		\FreePBX::GPG();
		$fpbxmodules = \FreePBX::Modules();
		$list = $fpbxmodules->getActiveModules();
		$this->out->writeln("Getting Data from Online Server...");
		$modules_online = $mf->getonlinexml();
		if(empty($modules_online)) {
			$this->out->writeln('Cant Reach Online Server');
			exit(1);
		} else {
			$this->out->writeln("Done");
		}
		$this->out->writeln("Checking Signatures of Modules...");
		foreach($list as $m) {
			//Check signature status, then if its online then if its signed online then redownload (through force)
			$this->out->writeln("Checking ". $m['rawname'] . "...");
			if(isset($m['signature']['status']) && (~$m['signature']['status'] & GPG::STATE_GOOD)) {
				$this->out->writeln("Signature Invalid");
				if(isset($modules_online[$m['rawname']]) && isset($modules_online[$m['rawname']]['signed'])) {
					$this->out->writeln("\tRefreshing ".$m['rawname']);
					$modulename = $m['rawname'];
					$modules = $fpbxmodules->getinfo($modulename);
					$this->doUpgrade($modulename,true);
					$this->out->writeln("\tVerifying GPG...");
					$mf->updateSignature($modulename);
					$this->out->writeln("Done");
				} else {
					$this->out->writeln("\tCould not find signed module on remote server!");
				}
			} else {
				$this->out->writeln("Good");
			}
		}
		$this->out->writeln("Done");
	}

	private function showReverseDepends($modulename) {
		$modulef = \module_functions::create();
		$modules = $modulef->getinfo($modulename);
		if (!isset($modules[$modulename])) {
			fatal($modulename.' not found');
		}

		if (($depmods = $modulef->reversedepends($modulename)) !== false) {
			$this->out->writeln("The following modules depend on this one: ".implode(', ',$depmods));
			exit(1);
		} else {
			$this->out->writeln("No enabled modules depend on this module.");
		}
	}

	private function showUpgrades() {
		$modules = $this->getUpgradableModules(true);
		if (count($modules) > 0) {
			$this->out->writeln("Upgradable: ");
			$rows = array();
			foreach ($modules as $mod) {
				array_push($rows, array($mod['name'],$mod['local_version'],$mod['online_version']));
				//$this->out->writeln($mod['name'].' '.$mod['local_version'].' -> '.$mod['online_version']);
			}
			$table = new Table($this->out);
			$table
				->setHeaders(array('Module', 'Local Version', 'Online Versiob'))
				->setRows($rows);
			$table->render();
		} else {
			$this->out->writeln("Up to date.");
		}
	}

	private function doDisable($modulename, $force) {
		$this->getIncludes();
		$module = \module_functions::create();
		if (is_array($errors = $module->disable($modulename, $force))) {
			$this->out->writeln("<error>The following error(s) occured:</error>");
			$this->out->writeln(' - '.implode("\n - ",$errors));
			exit(2);
		} else {
			$this->out->writeln("Module ".$modulename." successfully disabled");
		}
	}

	private function doEnable($modulename, $force) {
		$this->getIncludes();
		$module = \module_functions::create();
		if (is_array($errors = $module->enable($modulename, $this->force))) {
			$this->out->writeln("<error>The following error(s) occured:</error>");
			$this->out->writeln(' - '.implode("\n - ",$errors));
			exit(2);
		} else {
			$this->out->writeln("Module ".$modulename." successfully enabled");
		}
	}

	private function tryEnable($modulename, $force) {
		$this->getIncludes();
		$module = \module_functions::create();
		if (is_array($errors = $module->enable($modulename, $this->force))) {
			$this->out->writeln("<error>The following error(s) occured:</error>");
			$this->out->writeln(' - '.implode("\n - ",$errors));
		} else {
			$this->out->writeln("Module ".$modulename." successfully enabled");
		}
	}

	private function showHelp(){
		$help .= 'FWConsole Help:';
		$help .= 'Usage: fwconsole [-f][-R reponame][-R reponame][action][arg1][arg2][arg...]' . PHP_EOL;
		$help .= 'Flags:' . PHP_EOL;
		$help .= '-f - FORCE' . PHP_EOL;
		$help .= '-R - REPO, accepts reponame as a single argument' . PHP_EOL;

		$help .= '<question>Module Actions:</question>' . PHP_EOL;
		$rows[] = array('checkdepends','Checks dependencies for provided module[s], accepts argument module[s] ');
		$rows[] = array('disable','Disables module[s] accepts argument module[s]');
		$rows[] = array('download','Download module[s], accepts argument module[s]');
		$rows[] = array('delete','Deleted module[s], accepts argument module[s]');
		$rows[] = array('enable','Enable module[s], accepts argument module[s]');
		$rows[] = array('install','Installs module[s], accepts argument module[s]');
		$rows[] = array('installlocal','Install local module[s], accepts argument module[s]');
		$rows[] = array('uninstall','Uninstalls module[s], accepts argument module[s]');
		$rows[] = array('upgrade','Upgrade module[s], accepts argument module[s]');
		foreach($rows as $k => $v){
			$help .= '<info>'.$v[0].'</info> : <comment>' . $v[1] . '</comment>'. PHP_EOL;
		}
		unset($rows);
		$rows = array();
		$help .= '<question>All inclusive Module Actions:</question>' . PHP_EOL;
		$rows[] = array('installall','Installs all modules, accepts no arguments');
		$rows[] = array('enableall','Trys to enable all modules, accepts no arguments');
		$rows[] = array('upgradeall','Upgrades all modules, accepts no arguments');
		foreach($rows as $k => $v){
			$help .= '<info>'.$v[0].'</info> : <comment>' . $v[1] . '</comment>'. PHP_EOL;
		}
		unset($rows);
		$rows = array();
		$help .= '<question>Repository Actions:</question>' . PHP_EOL;
		$rows[] = array('disablerepo','Disables repo, accepts argument repo[s]');
		$rows[] = array('enablerepo','Enables repo, accepts argument repo[s]');
		$rows[] = array('list','List all local modules, accepts no arguments');
		$rows[] = array('listonline','List online modules, accepts no arguments');
		$rows[] = array('showupgrades','Shows a list of modules that may be updated, accepts no arguments');
		$rows[] = array('i18n','Shows translation information for supplied modules, accepts argument module[s]');
		$rows[] = array('refreshsignatures',' ReDownloads all modules that have invalid signatures');
		foreach($rows as $k => $v){
			$help .= '<info>'.$v[0].'</info> : <comment>' . $v[1] . '</comment>'. PHP_EOL;
		}
		unset($rows);
		$rows = array();
		return $help;

	}

	private function handleArgs($args){
		$action = array_shift($args);
		switch($action){
			case 'install':
				if(empty($args)){
					fatal("Missing module name");
				}
				foreach($args as $module){
					$this->doInstall($module, $this->force);
				}
				$this->setPerms();
				break;
			case 'installall':
				$this->doInstallAll(false);
				$this->setPerms();
				break;
			case 'installlocal':
				$this->doInstallLocal(true);
				$this->setPerms();
			case 'uninstall':
				if(empty($args)){
					fatal("Missing module name");
				}
				foreach($args as $module){
					$this->doUninstall($module, $this->force);
				}
				break;
			case 'download':
				if(empty($args)){
					fatal("Missing module name");
				}
				foreach($args as $module){
						$this->doDownload($module, $this->force);
				}
				$this->setPerms();
				break;
			case 'upgrade':
			case 'update':
				if(empty($args)){
					fatal("Missing module name");
				}
				foreach($args as $module){
					$this->doUpgrade($module, $this->force);
				}
				$this->setPerms();
				break;
			case 'updateall':
			case 'upgradeall':
				$this->doUpgradeAll($force);
				$this->setPerms();
				break;
			case 'list':
				$this->showList();
				break;
			case 'listonline':
				$modulef = \module_functions::create();
				$announcements = $modulef->get_annoucements();
				$this->showList(true);
				break;
			case 'reversedepends':
				if(empty($args)){
					fatal("Missing module name");
				}
				foreach($args as $module){
					$this->showReverseDepends($module);
				}
				break;
			case 'enablerepo':
				if(empty($args)){
					fatal("Missing repo name");
				}
				foreach($args as $repo){
					$this->enableRepo($repo);
				}
				break;
			case 'disablerepo':
				if(empty($args)){
					fatal("Missing repo name");
				}
				foreach($args as $repo){
					$this->enableRepo($repo);
				}
				foreach($args as $module){

				}
				break;
			case 'checkdepends':
				if(empty($args)){
					fatal("Missing module name");
				}
				foreach($args as $module){
					$this->showCheckDepends($module);
				}
				break;
			case 'delete':
				if(empty($args)){
					fatal("Missing module name");
				}
				foreach($args as $module){
					doDelete($module, $this->force);
				}
				$this->setPerms();
				break;
			case 'disable':
				if(empty($args)){
					fatal("Missing module name");
				}
				foreach($args as $module){
					$this->doDisable($module, $this->force);
				}
				break;
			case 'enable':
				if(empty($args)){
					fatal("Missing module name");
				}
				foreach($args as $module){
					$this->doEnable($module, $this->force);
				}
				break;
			case 'enableall':
				$modules = $this->listDisabled();
				foreach($modules as $module){
					$this->out->writeln('Attempting to Enable '. $module );
					$this->tryEnable($module, $this->force);
				}
				$this->out->writeln('This action understands somethings may be disabled for a reason.');
				$this->out->writeln('Please review the output above for any errors while enabling modules');
				break;
			case 'showupgrade':
			case 'showupgrades':
				if($this->DEBUG){$this->out->writeln('Called showupgrade[s]');}
				$this->showUpgrades();
				break;
			case 'i18n':
				if(empty($args)){
					fatal("Missing module name");
				}
				foreach($args as $module){
					$this->showi18n($module);
				}
				break;
			case 'refreshsignatures':
				$this->refreshsignatures();
				$this->setPerms();
				break;
			case 'updatexml':
				break;
			case 'help':
			case 'h':
			case '?':
			default:
				$this->out->writeln('Unknown Command! ('.$$action.')');
				break;
		}
	}
}
