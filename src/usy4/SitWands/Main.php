<?php

namespace usy4\SitWands;

use usy4\SitWands\commands\SitWandsCommand;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\StringMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\types\entity\Vec3MetadataProperty;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\entity\Entity;
use pocketmine\item\VanillaItems;
use pocketmine\event\Listener;

use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;

use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;
use pocketmine\event\player\PlayerJumpEvent;

class Main extends PluginBase implements Listener{


	public $sit = [];
	public $sit2 = [];

	public function onEnable() : void{

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getServer()->getCommandMap()->register($this->getName(), new SitWandsCommand($this));        
    }

	public function addWands(Player $player) 
	{
		$item = VanillaItems::STICK();
		$item->setCustomName("§r§dSit on the player §6Wand\n§7[Damage SomeOne]");
		$player->getInventory()->addItem($item);
		$item2 = VanillaItems::BLAZE_ROD();
		$item2->setCustomName("§r§dPlayer sit on you §6Wand\n§7[Damage SomeOne]");
		$player->sendMessage("Done.");
		$player->getInventory()->addItem($item2);
	}
	
	/**
       * @ignoreCancelled true
       * @priority MONITOR
      */    
	
	public function onDamage(EntityDamageEvent $event): void{
		$entity = $event->getEntity();
		if($entity instanceof Player){
			if($event instanceof EntityDamageByEntityEvent && ($damager = $event->getDamager()) instanceof Player){
				$item = $damager->getInventory()->getItemInHand()->getName();
				if($item == "§r§dSit on the player §6Wand\n§7[Damage SomeOne]"){
					$event->cancel();
					if(isset($this->sit[$damager->getName()]) or isset($this->sit2[$damager->getName()])) {	
						$event->cancel();	
						$damager->sendTip("§cYou Already In Sit Mode");	
						return;	
					}	
					if(isset($this->sit[$entity->getName()]) or isset($this->sit2[$entity->getName()])) {
						$event->cancel();
						$damager->sendTip("§cS/he Already In Sit Mode");
						return;	 
					}	 
					$this->onHit($entity, $damager);
				}
				if($item == "§r§dPlayer sit on you §6Wand\n§7[Damage SomeOne]"){
					$event->cancel();
					if(isset($this->sit[$damager->getName()]) or isset($this->sit2[$damager->getName()])){
						$event->cancel();
						$damager->sendTip("§cYou Already In Sit Mode");
						return;
					}
					if(isset($this->sit[$entity->getName()])) {
						$event->cancel();
						$damager->sendTip("§cS/he Already In Sit Mode");
						return;
					}
					$this->onHit($damager, $entity);
				}	
			}
		}
	}

	public function onHit(Player $player1, Player $player2) {
		if($player2->getName() === $player1->getName()) 
			return;			
		$player1->sendMessage($player2->getName().'§d sit on You');	
		$player2->sendMessage('§dYou sit on §r'.$player1->getName());
		$this->sit[$player2->getName()] = $player1->getName();
		$this->sit2[$player1->getName()] = $player2->getName();
		$this->SitOnPlayer($player1, $player2);
	}
       
	public function onDataPacketReceive(DataPacketReceiveEvent $event){
		$packet = $event->getPacket();
		  if ($packet instanceof InteractPacket){
			  if($packet->action === InteractPacket::ACTION_LEAVE_VEHICLE){
				    $player = $event->getOrigin()->getPlayer();
					if(isset($this->sit[$player->getName()])){
						$this->dismountFromPlayer($player);
					}
				  $event->cancel();
			  }
		  }
	}
    
public function sitOnPlayer(?Player $player1, ?Player $player2) {

        $pk = SetActorLinkPacket::create(
            new EntityLink(
                $player1->getId(),
                $player2->getId(),
                EntityLink::TYPE_RIDER,
                true,
                true,
                0.0
            )
        );

        foreach ( $this->getServer()->getOnlinePlayers() as $p){
            if(!$p->isConnected() || !$p->isOnline()) continue;
            $p->getNetworkSession()->sendDataPacket($pk);
        }

            $player2->getNetworkProperties()->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, new Vector3(0, +1.6, 0));
            $player2->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, true);
    }

	public function dismountFromPlayer(Player $entity): bool {
		foreach($this->sit as $p){
            if($p == $this->sit[$entity->getName()]){
			unset($this->sit2[($p)]);
			unset($this->sit[($p)]);
            }
		}
		unset($this->sit[($entity->getName())]);
		$entity->teleport(new Vector3($entity->getPosition()->getX(), $entity->getPosition()->getY()+0.1, $entity->getPosition()->getZ()));
		return true;
	}

    
	public function dismountFromPlayer2(Player $entity): bool {
        foreach($this->sit2 as $p){
            if($p == $this->sit2[$entity->getName()]){
			unset($this->sit2[($p)]);
			unset($this->sit[($p)]);
            }
		}
		unset($this->sit2[($entity->getName())]);
        	$entity->teleport(new Vector3($entity->getPosition()->getX(), $entity->getPosition()->getY()+0.1, $entity->getPosition()->getZ()));
        	return true;
    	}


    	public function onSneak(PlayerToggleSneakEvent $event){
        	$player = $event->getPlayer();
        	if(isset($this->sit[$player->getName()])){
            		$this->dismountFromPlayer($player);
        	}
    	}
    	
	public function onJump(PlayerJumpEvent $event){
        	$player = $event->getPlayer();
        	if(isset($this->sit[$player->getName()])){
        		$this->dismountFromPlayer($player);
        	}
    	}
	
    	public function onTeleport(EntityTeleportEvent $event){
        	$player = $event->getEntity();
		if($player instanceof Player){
			if(isset($this->sit[$player->getName()])){
				$this->dismountFromPlayer($player);
			}
		}
    	}


    	public function onQuit(PlayerQuitEvent $event) {
        	$player = $event->getPlayer();
        	if(isset($this->sit[$player->getName()])){
            		$this->dismountFromPlayer($player);
        	}
        	if(isset($this->sit2[$player->getName()])){
            		$this->dismountFromPlayer2($player);
        	}
    	}

    	public function onDeath(PlayerDeathEvent $event) {
        	$player = $event->getPlayer();
        	if(isset($this->sit[$player->getName()])){
            		$this->dismountFromPlayer($player);
        	}
        	if(isset($this->sit2[$player->getName()])){
            		$this->dismountFromPlayer2($player);
        	}
    	}

}
