<?php

namespace BehaviorPackLoader;

use BehaviorPackLoader\handler\BehaviorPacksHandler;
use BehaviorPackLoader\Item\ItemLoader;
use pocketmine\plugin\PluginBase;

class Loader extends PluginBase{
	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents(new BehaviorPacksHandler($this->getDataFolder(),$this->getLogger(),false), $this);
		//$this->getServer()->getPluginManager()->registerEvents(new BehaviorPacksHandler($this->getDataFolder(), null, false), $this);
		//$this->getServer()->getPluginManager()->registerEvents(new BehaviorPacksHandler(), $this);
		
		ItemLoader::init($this->getDataFolder());
	}
}
