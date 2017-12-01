<?php
	try {
		require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
		include_file('core', 'authentification', 'php');

		if (!isConnect('admin')) {
			throw new Exception(__('401 - Accès non autorisé', __FILE__));
		}
		if (init('action') == 'changeIncludeState') {
			config::save('include_mode', 1, 'globalcache');
			globalcache::Discovery();
			ajax::success();
		}
		if (init('action') == 'IrLearn') {
			$eqLogic=eqLogic::byId(init('id'));
			if(is_object($eqLogic)){
				if (config::byKey('learn_mode', 'globalcache', 0) == 1) {
					config::save('learn_mode', 1, 'globalcache');
					$eqLogic->IrLearnStart();
				}else{
					config::save('learn_mode', 0, 'globalcache');
					$eqLogic->IrLearnStop();
				}
				ajax::success(true);
			}
			ajax::success(false);
		}
		if (init('action') == 'getCode') {
			$eqLogic=eqLogic::byId(init('id'));
			if(is_object($eqLogic))
				ajax::success($eqLogic->Learn());
			ajax::success(false);
		}
		if (init('action') == 'getCacheMonitor') {
			$return = false;
			$eqLogic=eqLogic::byId(init('id'));
			if(is_object($eqLogic)){
				$cache = cache::byKey('globalcache::Monitor::'.$eqLogic->getId());
				$return=$cache->getValue('[]');
			}
			ajax::success($return);
		}
		throw new Exception(__('Aucune methode correspondante à : ', __FILE__) . init('action'));
	} catch (Exception $e) {
		ajax::error(displayExeption($e), $e->getCode());
	}
?>
