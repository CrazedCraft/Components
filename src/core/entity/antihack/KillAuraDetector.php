<?php

/**
 * KillAuraDetector.php – Components
 *
 * Copyright (C) 2015-2017 Jack Noordhuis
 *
 * This is private software, you cannot redistribute and/or modify it in any way
 * unless given explicit permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author Jack Noordhuis
 *
 * Last modified on 15/10/2017 at 2:04 AM
 *
 */

namespace core\entity\antihack;

use core\CorePlayer;
use core\entity\npc\HumanNPC;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\PlayerListPacket;
use pocketmine\Player;

class KillAuraDetector extends HumanNPC {

	/** @var CorePlayer */
	private $target;

	/** @var Vector3 */
	protected $offsetVector;

	public function initEntity() {
		parent::initEntity();
		$this->setScale(0.2);
	}

	/**
	 * @param Vector3 $offset
	 */
	public function setOffset($offset) {
		$this->offsetVector = $offset;
	}

	/**
	 * Set the player to target
	 *
	 * @param CorePlayer $player
	 */
	public function setTarget(CorePlayer $player) {
		$this->target = $player;
		$this->spawnTo($player);
	}

	/**
	 * @return CorePlayer
	 */
	public function getTarget() {
		return $this->target;
	}

	/**
	 * Check to make sure the target is valid and online
	 *
	 * @return bool
	 */
	public function hasValidTarget() {
		return $this->target instanceof CorePlayer and $this->target->isOnline() and $this->target->isAuthenticated();
	}

	/**
	 * Handle the aura detection and make sure the entity doesn't take damage
	 *
	 * @param float $damage
	 * @param EntityDamageEvent $source
	 */
	public function attack($damage, EntityDamageEvent $source) {
		if($this->hasValidTarget()) {
			$source->setCancelled();
			if($source instanceof EntityDamageByEntityEvent) {
				$attacker = $source->getDamager();
				if($attacker instanceof CorePlayer and $attacker->getId() == $this->target->getId()) {
					$this->target->addKillAuraTrigger();
				}
			}
		} else {
			$this->kill();
		}
	}

	/**
	 * Make sure the entity isn't spawned to any other player except the target
	 *
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function spawnTo(Player $player) {
		if($player->getId() == $this->target->getId()) {
			if($player !== $this and !isset($this->hasSpawned[$player->getId()]) and isset($player->usedChunks[Level::chunkHash($this->chunk->getX(), $this->chunk->getZ())])) {
				$this->hasSpawned[$player->getId()] = $player;

				$pk = new PlayerListPacket();
				$pk->type = PlayerListPacket::TYPE_ADD;
				$pk->entries[] = [$this->getUniqueId(), $this->getId(), "", $player->skinName, $player->skin];
				$player->dataPacket($pk);

				$pk = new AddPlayerPacket();
				$pk->uuid = $this->getUniqueId();
				$pk->username = $this->getNameTag();
				$pk->eid = $this->getId();
				$pk->x = $this->x;
				$pk->y = $this->y;
				$pk->z = $this->z;
				$pk->speedX = $this->motionX;
				$pk->speedY = $this->motionY;
				$pk->speedZ = $this->motionZ;
				$pk->yaw = $this->yaw;
				$pk->pitch = $this->pitch;
				$pk->metadata = $this->dataProperties;
				$player->dataPacket($pk);
			}
		}
		return false;
	}

	/**
	 * Update the detectors position
	 *
	 * @param $currentTick
	 *
	 * @return bool
	 */
	public function onUpdate($currentTick) {
		parent::onUpdate($currentTick);
		$wasVisible = $this->isVisible();
		if(($this->ticksLived % 100) == 0) $this->setVisible(false);
		if($this->hasValidTarget()) {
			$oldPos = $this->getPosition();
			$newPos = $this->getNewPosition();
			if(!$newPos->equals($oldPos)) {
				$this->x = $newPos->x;
				$this->y = $newPos->y;
				$this->z = $newPos->z;
				$this->updateMovement();
			}
			if(!$wasVisible and ($this->ticksLived % 80) == 0) {
				$triggers = $this->target->getKillAuraTriggers();
				if(mt_rand(1, ($triggers <= 3 ? 3 : ($triggers >= 6 ? 1 : 2)) == 1)) { // triggers <= 3: 1 in 3 chance, triggers >= 6: 1 in 1 chance, > 3 and < 6: 1 in 2 chance
					$this->setVisible(true);
				}
			}
		} else {
			$this->kill();
		}
		return true;
	}

	/**
	 * Calculate the updated position of the detector
	 *
	 * @return Vector3
	 */
	public function getNewPosition() {
		//$pos = $this->getBehindTarget(2);
		$pos = $this->target->getPosition();
		return $pos->add($this->offsetVector->x, $this->offsetVector->y, $this->offsetVector->z);
	}

	/**
	 * Get the position the specified amount of blocks distance away from behind the target
	 *
	 * @param $blocks
	 *
	 * @return Vector3
	 */
	public function getBehindTarget($blocks) {
		$pos = $this->target->getPosition();
		$rad = M_PI * $this->target->yaw / 180;
		return $pos->add($blocks * sin($rad), 0, -$blocks * sin($rad));
	}

	/**
	 * Make sure the npc doesn't get saved
	 */
	public function saveNBT() {
		return false;
	}

	/**
	 * Make sure nothing drops in case the NPC dies
	 *
	 * @return array
	 */
	public function getDrops() {
		return [];
	}

}