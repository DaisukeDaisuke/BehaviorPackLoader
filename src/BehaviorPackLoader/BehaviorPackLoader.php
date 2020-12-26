<?php

namespace BehaviorPackLoader;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\convert\ItemTypeDictionary;
use pocketmine\network\mcpe\protocol\ResourcePackChunkDataPacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkRequestPacket;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\ResourcePackDataInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\resourcepacks\BehaviorPackInfoEntry;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackStackEntry;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackType;
use pocketmine\plugin\PluginBase;
use pocketmine\resourcepacks\ResourcePack;
use pocketmine\resourcepacks\ResourcePackManager;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use function Wazly\Revelation\reveal;


/*
	special Thanks!
	https://qiita.com/KNJ/items/93eac224084da88a3882 (Japanese)
	https://github.com/KNJ/revelation
*/

class BehaviorPackLoader extends PluginBase implements Listener{
	private const PACK_CHUNK_SIZE = 128 * 1024; //128KB

	/** @var ResourcePackManager */
	public $ResourcePackManager = null;//behaviorPack
	/** @var bool */
	public $IsExperimentalGamePlay = false;

	public $item_id_map_array;

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->saveResource("setting.yml");
		$settingConfig = new Config($this->getDataFolder()."setting.yml", Config::YAML);
		$no_vendor = $settingConfig->get("no-vendor");
		if(!$no_vendor&&!file_exists($this->getFile()."vendor/autoload.php")){
			$this->getLogger()->error($this->getFile()."vendor/autoload.php ファイルに関しましては存在致しません為、BehaviorPackLoaderを起動することは出来ません。");
			$this->getLogger()->info("§ehttps://github.com/DaisukeDaisuke/BehaviorPackLoader/releases よりphar形式のプラグインをダウンロードお願い致します。§r");
			$this->getLogger()->info("§cこのプラグインを無効化致します。§r");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		if(!$no_vendor){
			include_once $this->getFile()."vendor/autoload.php";
		}

		if(!file_exists($this->getDataFolder())){
			mkdir($this->getDataFolder(), 0774, true);
		}

		$this->saveResource("add_item_id_map.json");
		$this->saveResource("resource_packs.yml");
		$this->ResourcePackManager = new ResourcePackManager($this->getDataFolder(), $this->getLogger());

		$resourcePacksConfig = new Config($this->getDataFolder()."resource_packs.yml", Config::YAML, []);
		$this->IsExperimentalGamePlay = (bool) $resourcePacksConfig->get("ExperimentalGamePlay");

		$item_id_map = new Config($this->getDataFolder()."add_item_id_map.json", Config::JSON);

		$this->item_id_map_array = $item_id_map->getAll();

		$this->update_item_id_map();
		$this->RegisterItems();
		$this->addCreativeItems();
	}

	public function RegisterItems(){
		foreach($this->item_id_map_array as $string_id => $id){
			ItemFactory::getInstance()->register(new Item($id,0,"test"));
		}
	}

	public function addCreativeItems(){
		foreach($this->item_id_map_array as $string_id => $id){
			CreativeInventory::getInstance()->add(new Item($id));
		}
	}

	public function onJoin(PlayerJoinEvent $event){
		$player = $event->getPlayer();
		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function(int $currentTick = 1) use ($player): void{
			$player->getNetworkSession()->getInvManager()->syncContents($player->getInventory());//...?
			//$player->getInventory()->sendContents($player);
		}), 1);

	}

	public function update_item_id_map(){
		$reveal = reveal(ItemTypeDictionary::getInstance());
		$runtimeId = max($reveal->stringToIntMap) + 1;

		$stringToIntMap = [];
		$intToStringIdMap = [];

		$simpleCoreToNetMapping = [];
		$simpleNetToCoreMapping = [];

		$complexCoreToNetMapping = [];
		$complexNetToCoreMapping = [];

		foreach($this->item_id_map_array as $string_id => $id){
			$stringToIntMap[$string_id] = $runtimeId;
			$intToStringIdMap[$runtimeId] = $string_id;

			$simpleCoreToNetMapping[$id] = $runtimeId;
			$simpleNetToCoreMapping[$runtimeId] = $id;

			++$runtimeId;
		}

		$reveal->stringToIntMap += $stringToIntMap;
		$reveal->intToStringIdMap += $intToStringIdMap;

		$reveal = reveal(ItemTranslator::getInstance());
		$reveal->simpleCoreToNetMapping += $simpleCoreToNetMapping;
		$reveal->simpleNetToCoreMapping += $simpleNetToCoreMapping;

		$reveal->complexCoreToNetMapping += $complexCoreToNetMapping;
		$reveal->complexNetToCoreMapping += $complexNetToCoreMapping;
	}

	public function send(DataPacketSendEvent $event){
		foreach($event->getPackets() as $key => $packet){
			if($packet instanceof ResourcePackStackPacket){//
				$stack = array_map(static function(ResourcePack $pack): ResourcePackStackEntry{
					return new ResourcePackStackEntry($pack->getPackId(), $pack->getPackVersion(), ""); //TODO: subpacks
				}, $this->ResourcePackManager->getResourceStack());

				$packet->behaviorPackStack = $stack;
			}else if($packet instanceof ResourcePacksInfoPacket){//
				$resourcePackEntries = array_map(static function(ResourcePack $pack): BehaviorPackInfoEntry{
					//TODO: more stuff
					return new BehaviorPackInfoEntry($pack->getPackId(), $pack->getPackVersion(), $pack->getPackSize(), "", "", "", false);
				}, $this->ResourcePackManager->getResourceStack());

				$packet->behaviorPackEntries = $resourcePackEntries;
			}else if($packet instanceof StartGamePacket){
				if(!$this->IsExperimentalGamePlay) return;
				$packet->gameRules["experimentalgameplay"] = [1, true];
			}
		}
	}

	public function Receive(DataPacketReceiveEvent $event){
		if($event->getPacket() instanceof ResourcePackClientResponsePacket){
			$packet = $event->getPacket();
			switch($packet->status){
				case ResourcePackClientResponsePacket::STATUS_REFUSED:
					//TODO: add lang strings for this
					//$this->session->disconnect("You must accept resource packs to join this server.", true);
					break;
				case ResourcePackClientResponsePacket::STATUS_SEND_PACKS:
					foreach($packet->packIds as $key => $uuid){
						//dirty hack for mojang's dirty hack for versions
						$splitPos = strpos($uuid, "_");
						if($splitPos !== false){
							$uuid = substr($uuid, 0, $splitPos);
						}
						$pack = $this->ResourcePackManager->getPackById($uuid);

						if(!($pack instanceof ResourcePack)){
							//Client requested a resource pack but we don't have it available on the server
							continue;
						}

						$pk = new ResourcePackDataInfoPacket();
						$pk->packId = $pack->getPackId();
						$pk->maxChunkSize = self::PACK_CHUNK_SIZE; //1MB
						$pk->chunkCount = (int) ceil($pack->getPackSize() / self::PACK_CHUNK_SIZE);
						$pk->compressedPackSize = $pack->getPackSize();
						$pk->sha256 = $pack->getSha256();
						$pk->packType = ResourcePackType::BEHAVIORS;
						$event->getOrigin()->sendDataPacket($pk);
						unset($event->getPacket()->packIds[$key]);
					}
					break;
			}
		}else if($event->getPacket() instanceof ResourcePackChunkRequestPacket){
			$packet = $event->getPacket();
			$manager = $this->ResourcePackManager;
			$pack = $manager->getPackById($packet->packId);
			if(!($pack instanceof ResourcePack)){
				return;
			}

			$offset = $packet->chunkIndex * self::PACK_CHUNK_SIZE;

			$pk = new ResourcePackChunkDataPacket();
			$pk->packId = $packet->packId;
			$pk->chunkIndex = $packet->chunkIndex;
			$pk->data = $pack->getPackChunk($offset, self::PACK_CHUNK_SIZE);
			$pk->progress = $offset;
			$event->getOrigin()->sendDataPacket($pk);
			$event->cancel();
		}
	}
}
