<?php

namespace BehaviorPackLoader\Item;

use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\convert\ItemTypeDictionary;
use pocketmine\utils\Config;

class ItemLoader{
	public function __construct(){
		//none
	}

	public static function init(String $confdir){
		$item_id_map = (new Config($confdir."add_item_id_map.json", Config::JSON))->getAll();
		self::register($item_id_map);
		self::registerItems();
	}

	public static function registerItems(){
		$factory = ItemFactory::getInstance();
		//$factory->register(new Item(new ItemIdentifier($id, 0), "test"));
	}

	public static function register(array $item_id_map){
		$stringToIntMap = [];
		$intToStringIdMap = [];

		$simpleCoreToNetMapping = [];
		$simpleNetToCoreMapping = [];

		$runtimeId = self::bindTo(function(){
			return max($this->stringToIntMap) + 1;
		}, ItemTypeDictionary::getInstance());

		foreach($item_id_map as $string_id => $id){
			$stringToIntMap[$string_id] = $runtimeId;
			$intToStringIdMap[$runtimeId] = $string_id;

			$simpleCoreToNetMapping[$id] = $runtimeId;
			$simpleNetToCoreMapping[$runtimeId] = $id;
			++$runtimeId;
		}

		self::bindTo(function() use ($stringToIntMap, $intToStringIdMap){
			$this->stringToIntMap += $stringToIntMap;
			$this->intToStringIdMap += $intToStringIdMap;
		},ItemTypeDictionary::getInstance());

		self::bindTo(function() use ($simpleCoreToNetMapping, $simpleNetToCoreMapping){
			$this->simpleCoreToNetMapping += $simpleCoreToNetMapping;
			$this->simpleNetToCoreMapping += $simpleNetToCoreMapping;
		},ItemTranslator::getInstance());
	}

	public static function bindTo(\Closure $closure, $class){
		return $closure->bindTo($class, get_class($class))();
	}
}
