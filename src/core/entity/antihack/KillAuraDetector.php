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
 */

namespace core\entity\antihack;

use core\CorePlayer;
use core\entity\npc\HumanNPC;
use core\Utils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;

class KillAuraDetector extends HumanNPC {

	/** @var string */
	private $targetUuid = "";

	/** @var Vector3 */
	protected $offsetVector;

	/** @var int */
	protected $visibleTicks = 0;

	/** @var int */
	protected $invisibleTicks = 900; // 45 seconds

	public function initEntity() : void {
		parent::initEntity();
		$this->setInvisible(true);
		$this->setScale(0.2);
		$this->offsetVector = new Vector3();
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
		$this->targetUuid = $player->getUniqueId()->toString();
		$this->spawnTo($player);
	}

	/**
	 * @return CorePlayer
	 */
	public function getTarget() {
		return Utils::getPlayerByUUID($this->targetUuid);
	}

	/**
	 * Check to make sure the target is valid and online
	 *
	 * @return bool
	 */
	public function hasValidTarget() {
		return ($target = $this->getTarget()) instanceof CorePlayer and $target->isOnline() and $target->isAuthenticatedInternally();
	}

	/**
	 * Handle the aura detection and make sure the entity doesn't take damage
	 *
	 * @param EntityDamageEvent $source
	 */
	public function attack(EntityDamageEvent $source) : void {
		if($this->hasValidTarget()) {
			$source->setCancelled();
			if($source instanceof EntityDamageByEntityEvent) {
				$attacker = $source->getDamager();
				if($attacker instanceof CorePlayer and $attacker->getId() === ($target = $this->getTarget())->getId()) {
					$target->addKillAuraTrigger();

					if($this->isInvisible()) {
						$this->visibleTicks += 20; // stay visible for an additional second
					} else {
						$this->invisibleTicks -= 40; // reduce time until potentially visible by 2 seconds
					}
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
	 */
	public function spawnTo(Player $player) : void {
		if(($target = $this->getTarget()) instanceof CorePlayer and $player->getId() === $target->getId()) {
			parent::spawnTo($player);
		}
	}

	/**
	 * Update the detectors position
	 *
	 * @param $currentTick
	 *
	 * @return bool
	 */
	public function onUpdate(int $currentTick) : bool {
		parent::onUpdate($currentTick);

		if($this->hasValidTarget()) {
			$oldPos = $this->getPosition();
			$newPos = $this->getNewPosition();
			if(!$newPos->equals($oldPos)) { // if the player has moved
				$this->setPosition($newPos);
				$this->updateMovement();
			}

			if($this->isInvisible()) {
				if($this->visibleTicks > 0) {
					$this->visibleTicks--;
				} else {
					$this->setInvisible(true);
					$this->invisibleTicks = 1800; // 1.5 minutes
				}
			} else {
				if($this->invisibleTicks > 0) {
					$this->invisibleTicks--;
				} else {
					$triggers = ($target = $this->getTarget())->getKillAuraTriggers();
					$rand = mt_rand(1, 100);
					if($triggers <= 3) {
						if($rand <= 15) {
							$this->visibleTicks = (20 * $triggers) + 20; // 4 seconds max, 2 seconds min
							$this->setInvisible(false);
						} else {
							$this->invisibleTicks = 800; // 40 seconds
						}
					} elseif($triggers >= 7) {
						if($rand <= 80) {
							$this->visibleTicks = (20 * $triggers) + 80; // 15 seconds max, 11.7 seconds min
							$this->setInvisible(false);
						} else {
							$this->invisibleTicks = 200; // 10 seconds
						}
					} else {
						if($rand <= 40) {
							$this->visibleTicks = (20 * $triggers) + 40; // 8 seconds max, 6 seconds min
							$this->setInvisible(false);
						} else {
							$this->invisibleTicks = 800; // 25 seconds
						}
					}
				}
			}
		} else {
			$this->close();
		}

		return true; // always update
	}

	/**
	 * Calculate the updated position of the detector
	 *
	 * @return Vector3
	 */
	public function getNewPosition() {
		$pos = $this->getTarget()->getPosition();
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
		$pos = ($target = $this->getTarget())->getPosition();
		$rad = M_PI * $target->yaw / 180;
		return $pos->add($blocks * sin($rad), 0, -$blocks * sin($rad));
	}

	/**
	 * Make sure the npc doesn't get saved
	 */
	public function saveNBT() : void{
		return;
	}

	/**
	 * Make sure nothing drops in case the NPC dies
	 *
	 * @return array
	 */
	public function getDrops() : array {
		return [];
	}

	/**
	 * Make sure the detector isn't spawned to any other players when its position is updated
	 *
	 * @param Vector3 $pos
	 *
	 * @return bool
	 */
	public function setPosition(Vector3 $pos) : bool {
		if($this->closed){
			return false;
		}

		if($pos instanceof Position and $pos->level !== null and $pos->level !== $this->level){
			if($this->switchLevel($pos->getLevel()) === false){
				return false;
			}
		}

		$this->x = $pos->x;
		$this->y = $pos->y;
		$this->z = $pos->z;

		$radius = $this->width / 2;
		$this->boundingBox->setBounds($pos->x - $radius, $pos->y, $pos->z - $radius, $pos->x + $radius, $pos->y + $this->height, $pos->z + $radius);

		return true;
	}

}