<?php

namespace BehaviorPackLoader\handler;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
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
use pocketmine\resourcepacks\ResourcePack;
use pocketmine\resourcepacks\ResourcePackManager;

class BehaviorPacksHandler implements Listener{
	private const PACK_CHUNK_SIZE = 128 * 1024; //128KB

	/** @var ResourcePackManager */
	public $ResourcePackManager = null;//behaviorPack
	/** @var bool */
	public $IsExperimentalGamePlay = false;

	public function __construct($path = null,$Logger = null,$IsExperimentalGamePlay = false){
		$this->ResourcePackManager = new ResourcePackManager($path ?? $this->getDefaultPath(), $Logger ?? $this->getDefaultLogger());
		$this->IsExperimentalGamePlay = $IsExperimentalGamePlay;
	}

	public function send(DataPacketSendEvent $event){
		foreach($event->getPackets() as $key => $packet){
			if($packet instanceof ResourcePackStackPacket){
				$stack = array_map(static function(ResourcePack $pack): ResourcePackStackEntry{
					return new ResourcePackStackEntry($pack->getPackId(), $pack->getPackVersion(), ""); //TODO: subpacks
				}, $this->ResourcePackManager->getResourceStack());

				$packet->behaviorPackStack = $stack;
			}else if($packet instanceof ResourcePacksInfoPacket){
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
						$pk->maxChunkSize = self::PACK_CHUNK_SIZE; //128KB
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

	public function getDefaultLogger(){
		return Server::getInstance()->getLogger();
	}

	public function getDefaultPath(): String{
		return Server::getInstance()->getDataPath()."/behavior_packs/";
	}
}
