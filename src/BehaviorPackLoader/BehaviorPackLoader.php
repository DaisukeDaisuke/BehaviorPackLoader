<?php

namespace BehaviorPackLoader;

use pocketmine\Server;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\entity\Entity;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\protocol\UseItemPacket;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\level\particle\RedstoneParticle;
use pocketmine\level\particle\LargeExplodeParticle;
use pocketmine\utils\Config;
use pocketmine\level\particle\PortalParticle;
use pocketmine\level\particle\DestroyBlockParticle;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\block\Block;
use pocketmine\level\Position;
use pocketmine\level\Explosion;
use pocketmine\entity\Effect;
use pocketmine\nbt\NBT;
use pocketmine\item\Item;
use pocketmine\entity\Item as itementity;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ByteArrayTag;
use pocketmine\entity\PigZombie;

use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\player\PlayerToggleFlightEvent;
use pocketmine\event\block\BlockBreakEvent;

use pocketmine\scheduler\Task;

use pocketmine\entity\Attribute;

use pocketmine\network\mcpe\protocol\AddEntityPacket;
use pocketmine\network\mcpe\protocol\BossEventPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\network\mcpe\protocol\SetEntityDataPacket;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\TransferPacket;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\ShowCreditsPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\PlayerInputPacket;
use pocketmine\network\mcpe\protocol\SetEntityLinkPacket;

use pocketmine\level\particle\HugeExplodeSeedParticle;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

use pocketmine\tile\Tile;
use pocketmine\tile\Sign;

use pocketmine\block\BlockFactory;
use pocketmine\item\ItemFactory;

use pocketmine\network\mcpe\protocol\ScriptCustomEventPacket;

use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\CompletedUsingItemPacket;
use pocketmine\resourcepacks\ResourcePack;
use pocketmine\resourcepacks\ResourcePackManager;

use pocketmine\network\mcpe\protocol\types\ResourcePackType;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkRequestPacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkDataPacket;
use pocketmine\network\mcpe\protocol\ResourcePackDataInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;

use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\BatchPacket;

use BehaviorPackLoader\lib\Revelation;


/*
	special Thanks!
	https://qiita.com/KNJ/items/93eac224084da88a3882 (Japanese)
	https://github.com/KNJ/revelation
*/
use function Wazly\Revelation\reveal;

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

		$this->saveResource("setting.yml");
		$no_vendor = $settingConfig->get("no-vendor");
        if(!$no_vendor&&!file_exists($this->getFile()."vendor/autoload.php")){
            $this->getLogger()->error($this->getFile()."vendor/autoload.php ファイルに関しましては存在致しません為、BehaviorPackLoaderを起動することは出来ません。");
            $this->getLogger()->info("§ehttps://github.com/DaisukeDaisuke/BehaviorPackLoader/releases よりphar形式のプラグインをダウンロードお願い致します。§r");
            $this->getLogger()->info("§cこのプラグインを無効化致します。§r");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        if(!$no_vendor){
			include_once $this->file."vendor/autoload.php";
		}

		if(!file_exists($this->getDataFolder())){
			mkdir($this->getDataFolder(),0774,true);
		}
		$this->saveResource("resource_packs.yml");
		$this->ResourcePackManager = new ResourcePackManager($this->getDataFolder(),$this->getLogger());
		$resourcePacksConfig = new Config($this->getDataFolder() . "resource_packs.yml", Config::YAML, []);
		$this->IsExperimentalGamePlay = (bool) $resourcePacksConfig->get("ExperimentalGamePlay");
		//var_dump((bool) $this->IsExperimentalGamePlay);

		$item_id_map = new Config($this->getDataFolder() . "add_item_id_map.json", Config::JSON, [
			"riceball:rice" => 10000,
			"riceball:riceball" => 10001,
			"riceball:Grilled_riceball" => 10002,
		]);

		var_dump($item_id_map->getAll());

		$this->item_id_map_array = $item_id_map->getAll();

		$this->update_item_id_map();
		$this->RegisterItems();
		$this->addCreativeItems();
	}

	public function RegisterItems(){
		foreach($this->item_id_map_array as $string_id => $id){
			ItemFactory::registerItem(new Item($id,0,"test"));
		}
	}

	public function addCreativeItems(){
		foreach($this->item_id_map_array as $string_id => $id){
			Item::addCreativeItem(Item::get($id));
			//ItemFactory::registerItem();
		}
	}

	public function update_item_id_map(){
		$reveal = $this->reveal(StartGamePacket::class);

		$itemTable = json_decode(file_get_contents($this->getServer()->getResourcePath() . '/vanilla/item_id_map.json'), true);
		$itemTable += $this->item_id_map_array;
		$itemTable = $reveal->callStatic("serializeItemTable",$itemTable);

		$reveal->setStatic("itemTableCache",$itemTable);
	}

	public function send(DataPacketSendEvent $event){
		if($event->getPacket() instanceof ResourcePackStackPacket){
			$packet = $event->getPacket();
			$packet->behaviorPackStack = $this->ResourcePackManager->getResourceStack();
			$packet->isExperimental = true;//$this->IsExperimentalGamePlay;
		}else if($event->getPacket() instanceof ResourcePacksInfoPacket){
			$packet = $event->getPacket();
			$packet->behaviorPackEntries = $this->ResourcePackManager->getResourceStack();
		}else if($event->getPacket() instanceof StartGamePacket){
			if(!$this->IsExperimentalGamePlay) return;
			$packet = $event->getPacket();
			$packet->gameRules["experimentalgameplay"] = [1, true];
		}
	}

	public function Receive(DataPacketReceiveEvent $event){
		if($event->getPacket() instanceof ResourcePackClientResponsePacket){
			$packet = $event->getPacket();
			switch($packet->status){
				case ResourcePackClientResponsePacket::STATUS_REFUSED:
					//
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
}
