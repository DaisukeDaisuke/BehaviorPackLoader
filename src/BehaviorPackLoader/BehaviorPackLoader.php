<?php

namespace BehaviorPackLoader;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\nbt\NBT;
use pocketmine\item\Item;
use pocketmine\tile\Sign;
use pocketmine\tile\Tile;
use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\entity\Effect;
use pocketmine\entity\Entity;
use pocketmine\item\ToolTier;
use pocketmine\event\Listener;
use pocketmine\item\ItemBlock;
use pocketmine\level\Position;
use pocketmine\nbt\tag\IntTag;
use pocketmine\scheduler\Task;
use pocketmine\command\Command;
use pocketmine\level\Explosion;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\entity\Attribute;
use pocketmine\entity\PigZombie;
use pocketmine\item\ItemFactory;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\plugin\PluginBase;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockToolType;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockLegacyIds;
use pocketmine\nbt\tag\ByteArrayTag;
use pocketmine\command\CommandSender;
use pocketmine\scheduler\ClosureTask;
use BehaviorPackLoader\lib\Revelation;
use pocketmine\level\sound\ExplodeSound;
use pocketmine\entity\Item as itementity;
use pocketmine\resourcepacks\ResourcePack;
use pocketmine\event\block\BlockBreakEvent;

use pocketmine\inventory\CreativeInventory;
use pocketmine\level\particle\DustParticle;
use pocketmine\block\BlockIdentifier as BID;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerKickEvent;

use pocketmine\event\player\PlayerMoveEvent;

use pocketmine\event\player\PlayerQuitEvent;

use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\level\particle\PortalParticle;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\network\protocol\UseItemPacket;
use pocketmine\event\server\ServerCommandEvent;
use pocketmine\level\particle\RedstoneParticle;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;

use pocketmine\resourcepacks\ResourcePackManager;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\level\particle\DestroyBlockParticle;
use pocketmine\level\particle\LargeExplodeParticle;

use pocketmine\event\player\PlayerToggleFlightEvent;
use pocketmine\network\mcpe\protocol\InteractPacket;

use pocketmine\network\mcpe\protocol\TransferPacket;
use pocketmine\network\mcpe\protocol\AddEntityPacket;

use pocketmine\network\mcpe\protocol\BossEventPacket;

use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\level\particle\HugeExplodeSeedParticle;

use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerInputPacket;
use pocketmine\network\mcpe\protocol\ShowCreditsPacket;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;

use pocketmine\network\mcpe\protocol\SetEntityDataPacket;
use pocketmine\network\mcpe\protocol\SetEntityLinkPacket;

use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\network\mcpe\protocol\ResourcePacksInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\ScriptCustomEventPacket;
use pocketmine\network\mcpe\protocol\CompletedUsingItemPacket;

use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\ResourcePackDataInfoPacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkDataPacket;
use pocketmine\network\mcpe\protocol\ResourcePackChunkRequestPacket;
use pocketmine\network\mcpe\protocol\ResourcePackClientResponsePacket;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackType;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackInfoEntry;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackStackEntry;

/*
	special Thanks!
	https://qiita.com/KNJ/items/93eac224084da88a3882 (Japanese)
	https://github.com/KNJ/revelation
*/
use function Wazly\Revelation\reveal;

class BehaviorPackLoader extends PluginBase implements Listener{
	//by pocketmine\network\mcpe\handler\ResourcePacksPacketHandler
	private const PACK_CHUNK_SIZE = 1048576; //1MB

	/** @var ResourcePackManager */
	public $resourcePackManager = null;//behaviorPack
	/** @var bool */
	public $IsExperimentalGamePlay = true;

	/*const ALWAYS_ACCEPTS_INPUT = 		0b00000001;
	const RENDER_GAME_BEHIND = 		0b00000010;
	const ABSORBS_INPUT = 			0b00000100;
	const IS_SHOWING_MENU  = 			0b00001000;
	const SHOULD_STEAL_MOUSE   = 		0b00010000;
	const FORCE_RENDER_BELOW    = 		0b00100000;
	const RENDER_ONLY_WHEN_TOPMOST    = 	0b01000000;*/
	
	public $item_id_map_array;

	public $nowRuntimeId;

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
		//var_dump((bool) $this->IsExperimentalGamePlay);

		$item_id_map = new Config($this->getDataFolder() . "add_item_id_map.json", Config::JSON, [
			"riceball:rice" => 10000,
			"riceball:riceball" => 10001,
			"riceball:Grilled_riceball" => 10002,
		]);

		$this->item_id_map_array = $item_id_map->getAll();

		$this->RegisterItems();
		$this->addCreativeItems();

		$this->nowRuntimeId = count(RuntimeBlockMapping::getInstance()->getBedrockKnownStates());

		reveal(RuntimeBlockMapping::getInstance())->call('registerMapping',$this->nowRuntimeId++,485,0);//
		
	}

	public function RegisterItems(){
		foreach($this->item_id_map_array as $string_id => $id){
			ItemFactory::getInstance()->register(new Item($id,0,"test"));
		}
		$bricksBreakInfo = new BlockBreakInfo(2.0, BlockToolType::PICKAXE, ToolTier::WOOD()->getHarvestLevel(), 30.0);
		BlockFactory::getInstance()->register(new Block(new BID(485),"test",$bricksBreakInfo));
		ItemFactory::getInstance()->register(new ItemBlock(485, 0, 485));
		//BlockFactory::getInstance()->register(new Block(new BID(255 - 700),"test",$bricksBreakInfo));
		
	}

	public function addCreativeItems(){
		foreach($this->item_id_map_array as $string_id => $id){
			CreativeInventory::getInstance()->add(new Item($id));
			//CreativeInventory::getInstance()->add(new Item(255 - 700));
			//ItemFactory::registerItem();
		}
		//CreativeInventory::getInstance()->add(new Item(-450));
		//CreativeInventory::getInstance()->add(new Item(-230));
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
				var_dump($packet->blockTable->getRoot()->getValue()[0]);
				$tag = CompoundTag::create();
				$tag->setTag("name",(new StringTag("testj:test_b")));//test_b jixif
				$tag->setTag("states",CompoundTag::create());
				//$tag->setTag("version",new IntTag("17760256"));

				$tag1 = CompoundTag::create()->setTag("block",$tag)->setTag("id",new ShortTag(485));

				var_dump($packet->blockTable->getRoot()->count());

				$packet->blockTable->getRoot()->push($tag1);
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
