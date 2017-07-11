<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
class globalcache extends eqLogic {
	public static $_widgetPossibility = array('custom' => array(
	        'visibility' => true,
	        'displayName' => true,
	        'displayObjectName' => true,
	        'optionalParameters' => true,
	        'background-color' => true,
	        'text-color' => true,
	        'border' => true,
	        'border-radius' => true
	));
	public static function deamon_info() {
		$return = array();
		$return['log'] = 'globalcache';
		$return['launchable'] = 'ok';
		$return['state'] = 'nok';
		foreach(eqLogic::byType('globalcache') as $globalcache){
			if($globalcache->getIsEnable()){
				$cron = cron::byClassAndFunction('globalcache', 'Monitor', array('id' => $globalcache->getId()));
				if (!is_object($cron)) 	
					return $return;
			}
		}
		$return['state'] = 'ok';
		return $return;
	}
	public static function deamon_start($_debug = false) {
		log::remove('globalcache');
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') 
			return;
		if ($deamon_info['state'] == 'ok') 
			return;
		foreach(eqLogic::byType('globalcache') as $globalcache){
			if($globalcache->getIsEnable()){
				$globalcache->CreateDemon();   
			}
		}
	}
	public static function deamon_stop() {	
		foreach(eqLogic::byType('globalcache') as $globalcache){
			$cron = cron::byClassAndFunction('globalcache', 'Monitor', array('id' => $globalcache->getId()));
			if (is_object($cron)) 	
				$cron->remove();
		}
	}	
	public static function Monitor() {
		log::add('globalcache', 'debug', 'Objet mis à jour => ' . json_encode($_option));
		$globalcache = globalcache::byId($_option['id']);
		if (is_object($globalcache) && $globalcache->getIsEnable()) {
			$Ip=$globalcache->getLogicalId();
			$Port=$globalcache->getPort();
			$socket = stream_socket_client("tcp://$Ip:$Port", $errno, $errstr, 100);
			if (!$socket) 
				throw new Exception(__("$errstr ($errno)", __FILE__));
			log::add('globalcache', 'debug', 'Démarrage du démon');
			while (!feof($socket)) { 
				$Ligne=stream_get_line($socket, 1000000,"\n");
				$globalcache->addCacheMonitor($Ligne);
			}
			fclose($socket); 
		}
	}
	private function addCacheMonitor($_monitor) {
		$cache = cache::byKey('globalcache::Monitor::'.$this->getId());
		$value = json_decode($cache->getValue('[]'), true);
		$value[] = array('datetime' => date('d-m-Y H:i:s'), 'monitor' => $_monitor);
		cache::set('globalcache::Monitor::'.$this->getId(), json_encode(array_slice($value, -250, 250)), 0);
	}
	public function Send($data){
		$adresss=$this->getConfiguration('module').':'.$this->getConfiguration('voie');
		switch($this->getConfiguration('type')){
			case 'relay':
				$cmd="setstate,".$adresss.",".$data;
				$this->sendData($cmd);
			break;
			case 'ir':
				$cmd="set_IR,".$adresss.",".$this->getConfiguration('mode');
				$this->sendData($cmd);
				$cmd="sendir,".$adresss.",".$data;
				$this->sendData($cmd);
			break;
			case 'serial':
				$cmd="set_SERIAL,".$adresss.",".$this->getConfiguration('baudrate').",".$this->getConfiguration('flowcontrol').",".$this->getConfiguration('parity');
				$this->sendData($cmd);
				$this->EncodeData($data);
			break;
		}
	}
	private function sendData($data){		
		$Ip=$this->getLogicalId();
		$Port=$this->getPort();
		$socket = stream_socket_client("tcp://$Ip:$Port", $errno, $errstr, 100);
		if (!$socket) {
			throw new Exception(__("$errstr ($errno)", __FILE__));
		} else {
			log::add('globalcache','info','TX : '.$data);
			fwrite($socket, $data."\n");
		}
		fclose($socket);
	}
	private function CreateDemon() {
		$cron =cron::byClassAndFunction('globalcache', 'Monitor', array('id' => $this->getId()));
		if (!is_object($cron)) {
			$cron = new cron();
			$cron->setClass('globalcache');
			$cron->setFunction('Monitor');
			$cron->setOption(array('id' => $this->getId()));
			$cron->setEnable(1);
			$cron->setDeamon(1);
			$cron->setSchedule('* * * * *');
			$cron->setTimeout('999999');
			$cron->save();
		}
		$cron->save();
		$cron->start();
		$cron->run();
		return $cron;
	}
	private function getPort(){
		$Port=4998;
		switch($this->getConfiguration('type')){	
			case 'serial':
				$NbPrevModule=0;
				foreach(eqLogic::byTypeAndSearhConfiguration('globalcache',array('type'=>'serial')) as $eqLogic){
					if($eqLogic->getConfiguration('module') < $this->getConfiguration('module'))
						$NbPrevModule++;
				}
				$Port+=$NbPrevModule;
			break;
		}			
		rerun $Port;
	}
	private function EncodeData($data){
		for ($i=0; $i < strlen($data); $i++){
			$byte=null;
			switch($this->getConfiguration('codage')){
				case 'ASCII':
					$byte=ord($data[$i]);
				break
				case 'HEXA':
					$byte=dechex(ord($data[$i]));
				break;
				/*case 'JS':
				return json_encode($data);*/
			}
			$this->sendData($byte);
		}
	}
  }
class globalcacheCmd extends cmd {
	public function execute($_options = null){
		switch($this->getSubType()){
			case 'slider':
				$data=$_options['slider'];
			break;
			case 'color':
				$data=$_options['color'];
			break;
			case 'message':
				$data=$_options['message'];
			break;
			case 'other':
				$data=$this->getConfiguration('value');
			break;
		}
		$this->getEqLogic()->Send($data);
	}
}
?>
