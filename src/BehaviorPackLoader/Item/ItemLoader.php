<?php

declare(strict_types=1);

namespace BehaviorPackLoader\Item;

use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIdentifier;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\convert\ItemTypeDictionary;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use pocketmine\utils\Config;

class ItemLoader{
	public static $item_id_map;

	public function __construct(){
		//none
	}

	public static function init(String $confdir){
		self::$item_id_map = (new Config($confdir."add_item_id_map.json", Config::JSON))->getAll();
		self::register(self::$item_id_map);
		self::registerItems();
	}

	public static function registerItems(){
		$factory = ItemFactory::getInstance();
		//$factory->register(new Item(new ItemIdentifier(10000, 0), "test"));
	}

	public static function register(array $item_id_map){
		$stringToIntMap = [];
		$intToStringIdMap = [];

		$simpleCoreToNetMapping = [];
		$simpleNetToCoreMapping = [];

		[$runtimeId, $stringToIntMap, $intToStringIdMap, $itemTypes] = self::bindTo(function(){
			return [max($this->stringToIntMap) + 4500, $this->stringToIntMap, $this->intToStringIdMap, $this->itemTypes];
		}, ItemTypeDictionary::getInstance());

		foreach($item_id_map as $string_id => $id){
			$stringToIntMap[$string_id] = $runtimeId;
			$intToStringIdMap[$runtimeId] = $string_id;

			$simpleCoreToNetMapping[$id] = $runtimeId;
			$simpleNetToCoreMapping[$runtimeId] = $id;
	
			$itemTypes[] = new ItemTypeEntry($string_id, $runtimeId, false);//true
			++$runtimeId;
		}

		self::bindTo(function() use ($simpleCoreToNetMapping, $simpleNetToCoreMapping){
			$this->simpleCoreToNetMapping += $simpleCoreToNetMapping;
			$this->simpleNetToCoreMapping += $simpleNetToCoreMapping;
		},ItemTranslator::getInstance());

		self::bindTo(function() use ($itemTypes, $stringToIntMap, $intToStringIdMap){
			$this->stringToIntMap += $stringToIntMap;
			$this->intToStringIdMap += $intToStringIdMap;
			$this->itemTypes += $itemTypes;
		},ItemTypeDictionary::getInstance());
	}


	public static function bindTo(\Closure $closure, $class){
		return $closure->bindTo($class, get_class($class))();
	}

	public static function getItem_map(): ?array{
		if(!isset(self::$item_id_map)){
			return null;
		}
		return self::$item_id_map;
	}
}
