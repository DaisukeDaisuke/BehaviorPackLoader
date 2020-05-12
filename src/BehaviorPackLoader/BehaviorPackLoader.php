<?php

namespace BehaviorPackLoader;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\item\Item;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\item\ItemFactory;
use pocketmine\plugin\PluginBase;
use pocketmine\resourcepacks\ResourcePack;

use pocketmine\inventory\CreativeInventory;
use pocketmine\event\server\DataPacketSendEvent;

use pocketmine\resourcepacks\ResourcePackManager;
use pocketmine\event\server\DataPacketReceiveEvent;

use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\ScriptCustomEventPacket;
use pocketmine\network\mcpe\protocol\ResourcePackDataInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkDataPacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkRequestPacket;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackType;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackInfoEntry;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackStackEntry;

class BehaviorPackLoader extends PluginBase implements Listener{
	//by pocketmine\network\mcpe\handler\ResourcePacksPacketHandler
	private const PACK_CHUNK_SIZE = 1048576; //1MB

	/** @var ResourcePackManager */
	public $resourcePackManager = null;//behaviorPack
	/** @var bool */
	public $IsExperimentalGamePlay;

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
		if(!file_exists($this->getDataFolder())){
			mkdir($this->getDataFolder(),0774,true);
		}

		$this->saveResource("resource_packs.yml");
		$this->saveResource("setting.yml");

		$settingConfig = new Config($this->getDataFolder()."setting.yml",Config::YAML);
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

		$this->resourcePackManager = new ResourcePackManager($this->getDataFolder(),$this->getLogger());
		$resourcePacksConfig = new Config($this->getDataFolder() . "resource_packs.yml", Config::YAML, []);
		$this->IsExperimentalGamePlay = (bool) $resourcePacksConfig->get("ExperimentalGamePlay");

		$item_id_map = new Config($this->getDataFolder() . "add_item_id_map.json", Config::JSON, [
			"riceball:rice" => 10000,
			"riceball:riceball" => 10001,
			"riceball:Grilled_riceball" => 10002,
		]);

		$this->item_id_map_array = $item_id_map->getAll();
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

	public function send(DataPacketSendEvent $event){
		foreach($event->getPackets() as $key => $packet){
			if($packet instanceof ResourcePackStackPacket){//
				$stack = array_map(static function(ResourcePack $pack) : ResourcePackStackEntry{
					return new ResourcePackStackEntry($pack->getPackId(), $pack->getPackVersion(), ""); //TODO: subpacks
				}, $this->resourcePackManager->getResourceStack());

				$packet->behaviorPackStack = $stack;
				$packet->isExperimental = true;//$this->IsExperimentalGamePlay;
			}else if($packet instanceof ResourcePacksInfoPacket){//
				$resourcePackEntries = array_map(static function(ResourcePack $pack) : ResourcePackInfoEntry{
					//TODO: more stuff
					return new ResourcePackInfoEntry($pack->getPackId(), $pack->getPackVersion(), $pack->getPackSize(), "", "", "", false);
				}, $this->resourcePackManager->getResourceStack());

				$packet->behaviorPackEntries = $resourcePackEntries;
			}else if($packet instanceof StartGamePacket){
				$packet->itemTable += $this->item_id_map_array;

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
						$pack = $this->resourcePackManager->getPackById($uuid);

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
						$pk->packType = ResourcePackType::BEHAVIORS;//ResourcePackType::ADDON;// ...?
						$event->getOrigin()->sendDataPacket($pk);
						unset($event->getPacket()->packIds[$key]);

						/*$this->session->sendDataPacket(ResourcePackDataInfoPacket::create(
							$pack->getPackId(),
							self::PACK_CHUNK_SIZE,
							(int) ceil($pack->getPackSize() / self::PACK_CHUNK_SIZE),
							$pack->getPackSize(),
							$pack->getSha256()
						));*/
					}
					break;
			}
		}else if($event->getPacket() instanceof ResourcePackChunkRequestPacket){
			$packet = $event->getPacket();
			$manager = $this->resourcePackManager;
			$pack = $manager->getPackById($packet->packId);
			if(!($pack instanceof ResourcePack)){
				return;
			}

			$offset = $packet->chunkIndex * self::PACK_CHUNK_SIZE;

			$pk = new ResourcePackChunkDataPacket();
			$pk->packId = $packet->packId;
			$pk->chunkIndex = $packet->chunkIndex;
			$pk->data = $pack->getPackChunk($offset, self::PACK_CHUNK_SIZE);//$pack->getPackChunk(1048576 * $packet->chunkIndex, 1048576);
			$pk->progress = $offset;
			$event->getOrigin()->sendDataPacket($pk);
			$event->setCancelled();
		}
	}
}
