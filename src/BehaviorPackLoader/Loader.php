<?php

namespace BehaviorPackLoader;

use BehaviorPackLoader\handler\BehaviorPacksHandler;
use pocketmine\plugin\PluginBase;

class Loader extends PluginBase{
	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents(new BehaviorPacksHandler(), $this);
		//$this->getServer()->getPluginManager()->registerEvents(new BehaviorPacksHandler($this->getDataFolder(),$this->getLogger(),false), $this);
	}
}