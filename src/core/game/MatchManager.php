<?php

/**
 * CrazedCraft Network Components
 *
 * Copyright (C) 2016 CrazedCraft Network
 *
 * This is private software, you cannot redistribute it and/or modify any way
 * unless otherwise given permission to do so. If you have not been given explicit
 * permission to view or modify this software you should take the appropriate actions
 * to remove this software from your device immediately.
 *
 * @author JackNoordhuis
 *
 * Created on 12/07/2016 at 9:13 PM
 *
 */

namespace core\game;

use pocketmine\event\TimingsHandler;
use pocketmine\plugin\Plugin;

class MatchManager {

	/** @var Plugin */
	private $plugin;

	/** @var TimingsHandler */
	private $timings;

	/** @var MatchHeartbeat */
	private $heartbeat;

	/** @var int */
	private $lastTick = 0;

	/** @var Match[] */
	private $matches = [];

	public function __construct(Plugin $plugin) {
		$this->plugin = $plugin;
		$this->timings = new TimingsHandler("Match Manager");
		$this->heartbeat = new MatchHeartbeat($this);
	}

	/**
	 * @return Plugin
	 */
	public function getPlugin() {
		return $this->plugin;
	}

	/**
	 * @return TimingsHandler
	 */
	public function getTimingsHandler() {
		return $this->timings;
	}

	/**
	 * @return MatchHeartbeat
	 */
	public function getHeartbeat() {
		return $this->heartbeat;
	}

	/**
	 * @return int
	 */
	public function getLastTick() {
		return $this->lastTick;
	}

	/**
	 * Keep all matches moving and clean up inactive ones
	 *
	 * @param $currentTick
	 */
	public function tick($currentTick) {
		$this->timings->startTiming();
		foreach($this->matches as $key => $match) {
			if($match instanceof Match) {
				if($match->isActive()) {
					$match->tick($currentTick);
				} else {
					$match->close();
					unset($this->matches[$key]);
				}
			} else {
				unset($this->matches[$key]);
			}
		}
		$this->timings->stopTiming();
		$this->plugin->getLogger()->debug("Ticked MatchManager in " . round(($currentTick - $this->lastTick) / 20) . " seconds!");
	}

}