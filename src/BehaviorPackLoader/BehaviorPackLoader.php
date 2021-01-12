<?php

namespace BehaviorPackLoader;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\convert\ItemTypeDictionary;
use pocketmine\network\mcpe\protocol\ResourcePackChunkDataPacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkRequestPacket;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\ResourcePackDataInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use pocketmine\network\mcpe\protocol\types\ResourcePackType;
use pocketmine\plugin\PluginBase;
use pocketmine\resourcepacks\ResourcePack;
use pocketmine\resourcepacks\ResourcePackManager;
use pocketmine\utils\Config;

/*
	special Thanks!
	https://qiita.com/KNJ/items/93eac224084da88a3882 (Japanese)
	https://github.com/KNJ/revelation
*/

class BehaviorPackLoader extends PluginBase implements Listener{
	/** @var ResourcePackManager */
	public $ResourcePackManager = null;//behaviorPack
	/** @var bool */
	public $IsExperimentalGamePlay = false;

	/*const ALWAYS_ACCEPTS_INPUT = 		0b00000001;
	const RENDER_GAME_BEHIND = 		0b00000010;
	const ABSORBS_INPUT = 			0b00000100;
	const IS_SHOWING_MENU  = 			0b00001000;
	const SHOULD_STEAL_MOUSE   = 		0b00010000;
	const FORCE_RENDER_BELOW    = 		0b00100000;
	const RENDER_ONLY_WHEN_TOPMOST    = 	0b01000000;*/

	public $item_id_map_array;

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->saveResource("add_item_id_map.json");
		$this->saveResource("resource_packs.yml");
		$this->ResourcePackManager = new ResourcePackManager($this->getDataFolder(), $this->getLogger());
		$resourcePacksConfig = new Config($this->getDataFolder()."resource_packs.yml", Config::YAML, []);
		$this->IsExperimentalGamePlay = (bool) $resourcePacksConfig->get("ExperimentalGamePlay");

		$item_id_map = new Config($this->getDataFolder()."add_item_id_map.json", Config::JSON);

		//var_dump($item_id_map->getAll());

		$this->item_id_map_array = $item_id_map->getAll();

		$this->update_item_id_map();
		$this->RegisterItems();
		$this->addCreativeItems();
	}

	public function RegisterItems(){
		foreach($this->item_id_map_array as $string_id => $id){
			ItemFactory::registerItem(new Item($id, 0, "test"));
		}
	}

	public function addCreativeItems(){
		foreach($this->item_id_map_array as $string_id => $id){
			Item::addCreativeItem(Item::get($id));
		}
	}

	public function update_item_id_map(){
		$stringToIntMap = [];
		$intToStringIdMap = [];

		$simpleCoreToNetMapping = [];
		$simpleNetToCoreMapping = [];

		[$runtimeId, $stringToIntMap, $intToStringIdMap, $itemTypes] = self::bindTo(function(){
			return [max($this->stringToIntMap) + 1, $this->stringToIntMap, $this->intToStringIdMap, $this->itemTypes];
		}, ItemTypeDictionary::getInstance());

		foreach($this->item_id_map_array as $string_id => $id){
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
		}, ItemTranslator::getInstance());

		self::bindTo(function() use ($itemTypes, $stringToIntMap, $intToStringIdMap){
			$this->stringToIntMap = $stringToIntMap;
			$this->intToStringIdMap = $intToStringIdMap;
			$this->itemTypes = $itemTypes;
		}, ItemTypeDictionary::getInstance());
	}

	public function send(DataPacketSendEvent $event){
		if($event->getPacket() instanceof ResourcePackStackPacket){
			$packet = $event->getPacket();
			$packet->behaviorPackStack = $this->ResourcePackManager->getResourceStack();
		}else if($event->getPacket() instanceof ResourcePacksInfoPacket){
			$packet = $event->getPacket();
			$packet->behaviorPackEntries = $this->ResourcePackManager->getResourceStack();
		}else if($event->getPacket() instanceof StartGamePacket){
			if(!$this->IsExperimentalGamePlay) return;
			$packet = $event->getPacket();
			//$packet->gameRules["experimentalgameplay"] = [1, true];
		}
	}

	public function Receive(DataPacketReceiveEvent $event){
		if($event->getPacket() instanceof ResourcePackClientResponsePacket){
			$packet = $event->getPacket();
			switch($packet->status){
				case ResourcePackClientResponsePacket::STATUS_REFUSED:
					//var_dump("REFUSED!!");
					break;
				case ResourcePackClientResponsePacket::STATUS_SEND_PACKS:
					$manager = $this->ResourcePackManager;
					foreach($packet->packIds as $key => $uuid){
						//dirty hack for mojang's dirty hack for versions
						$splitPos = strpos($uuid, "_");
						if($splitPos !== false){
							$uuid = substr($uuid, 0, $splitPos);
						}
						$pack = $manager->getPackById($uuid);
						if(!($pack instanceof ResourcePack)){
							//
							continue;
						}
						//var_dump("!!!!!".$uuid);
						$pk = new ResourcePackDataInfoPacket();
						$pk->packId = $pack->getPackId();
						$pk->maxChunkSize = 1048576; //1MB
						$pk->chunkCount = (int) ceil($pack->getPackSize() / $pk->maxChunkSize);
						$pk->compressedPackSize = $pack->getPackSize();
						$pk->sha256 = $pack->getSha256();
						$pk->packType = ResourcePackType::BEHAVIORS;//ResourcePackType::ADDON;// ...?
						$event->getPlayer()->dataPacket($pk);
						unset($event->getPacket()->packIds[$key]);
					}
					break;
			}
		}else if($event->getPacket() instanceof ResourcePackChunkRequestPacket){
			$packet = $event->getPacket();
			$manager = $this->ResourcePackManager;
			$pack = $manager->getPackById($packet->packId);
			if(!($pack instanceof ResourcePack)){
				//
				return;
			}
			$pk = new ResourcePackChunkDataPacket();
			$pk->packId = $pack->getPackId();
			$pk->chunkIndex = $packet->chunkIndex;
			$pk->data = $pack->getPackChunk(1048576 * $packet->chunkIndex, 1048576);
			$pk->progress = (1048576 * $packet->chunkIndex);
			$event->getPlayer()->dataPacket($pk);
			$event->setCancelled();
		}
	}

	public static function bindTo(\Closure $closure, $class){
		return $closure->bindTo($class, get_class($class))();
	}
}
