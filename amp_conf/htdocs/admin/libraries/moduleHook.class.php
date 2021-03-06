<?php

class moduleHook {
	public $hookHtml = '';
	public $arrHooks = array();

	private static $obj;

	public static function create() {
		if (!is_object(self::$obj)) {
			self::$obj = new moduleHook(true);
		}
		return self::$obj;
	}


	public function __construct($checker = false) {
		if ($checker !== true) {
			throw new \Exception("Someone tried to new moduleHook. This is a bug");
		}
		self::$obj = $this;
	}

	public function install_hooks($viewing_itemid,$target_module,$target_menuid = '') {
		global $active_modules;

		/*  Loop though all active modules and find which ones have hooks.
		 *  Then process those hooks. Note we split this into two loops
		 *  because of #4057, if drawselects() is called from within a hook
		 *  it's interaction with the same $active_modules array renders the
		 *  foreach loop done after that module and execution ends.
		 */
		$our_hooks = array();
		foreach($active_modules as $this_module) {
			// look for requested hooks for $module
			// ie: findme_hook_extensions()
			$funct = $this_module['rawname'] . '_hook_' . $target_module;
			if( function_exists( $funct ) ) {
				// remember who installed hooks
				// we need to know this for processing form vars
				$this->arrHooks[] = $this_module['rawname'];
				$our_hooks[$this_module['rawname']] = $funct;
			}
		}
		foreach($our_hooks as $thismod => $funct) {
			modgettext::push_textdomain($thismod);
			if ($hookReturn = $funct($target_menuid, $viewing_itemid)) {
				$this->hookHtml .= $hookReturn;
			}
			modgettext::pop_textdomain();
		}
	}
	// process the request from the module we hooked
	public function process_hooks($viewing_itemid, $target_module, $target_menuid, $request) {
		if(is_array($this->arrHooks)) {
			foreach($this->arrHooks as $hookingMod) {
				// check if there is a processing function
				$funct = $hookingMod . '_hookProcess_' . $target_module;
				if( function_exists( $funct ) ) {
					modgettext::push_textdomain(strtolower($hookingMod));
					$funct($viewing_itemid, $request);
					modgettext::pop_textdomain();
				}
			}
		}
	}
}
