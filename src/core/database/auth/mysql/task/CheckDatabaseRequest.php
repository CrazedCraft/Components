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
 * Created on 14/07/2016 at 12:53 AM
 *
 */

namespace core\database\auth\mysql\task;

use core\database\auth\mysql\MySQLAuthDatabase;
use core\database\auth\mysql\MySQLAuthRequest;
use core\Main;
use pocketmine\Server;
use pocketmine\utils\PluginException;

/**
 * Check to make sure the Auth database is online and working
 */
class CheckDatabaseRequest extends MySQLAuthRequest {

	public function __construct(MySQLAuthDatabase $database) {
		parent::__construct($database->getCredentials());
	}

	/**
	 * Attempt to connect to the database
	 */
	public function onRun() {
		$mysqli = $this->getMysqli();
		if($this->checkConnection($mysqli)) return;
		$stmt = $mysqli->stmt_init();
		$stmt->prepare("CREATE TABLE IF NOT EXISTS auth (
			username VARCHAR(16) PRIMARY KEY,
			hash CHAR(128),
			email VARCHAR(32) DEFAULT '',
			lastip VARCHAR(50) DEFAULT '0.0.0.0',
			islocked INT DEFAULT 0,
			lockreason VARCHAR(128) DEFAULT '',
			lang CHAR(6) DEFAULT 'en',
			timeplayed INT DEFAULT 0,
			lastlogin INT DEFAULT 0,
			registerdate INT DEFAULT 0,
			coins INT DEFAULT 0
			)");
		$stmt->execute();
		if($this->checkError($stmt)) return;
		$this->setResult(self::SUCCESS);
	}

	/**
	 * @param Server $server
	 */
	public function onCompletion(Server $server) {
		$plugin = $this->getCore($server);
		if($plugin instanceof Main and $plugin->isEnabled()) {
			$result = $this->getResult();
			switch((is_array($result) ? $result[0] : $result)) {
				case self::CONNECTION_ERROR:
					$server->getLogger()->debug("Failed to complete CheckDatabaseRequest for auth database due to a connection error. Error: {$result[1]}");
					throw new \RuntimeException($result[1]);
				case self::SUCCESS:
					$server->getLogger()->debug("Successfully completed CheckDatabaseRequest for auth database!");
					return;
				case self::MYSQLI_ERROR:
					throw new \RuntimeException($result[1]);
			}
		} else {
			$server->getLogger()->debug("Attempted to complete CheckDatabaseRequest for auth database while Components plugin isn't enabled!");
			throw new PluginException("Components plugin isn't enabled!");
		}
	}

}