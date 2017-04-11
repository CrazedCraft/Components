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

namespace core\database\ban\mysql\task;

use core\database\ban\mysql\MySQLBanDatabase;
use core\database\mysql\MySQLRequest;
use core\Main;
use pocketmine\Server;
use pocketmine\utils\PluginException;

/**
 * Check to make sure the Auth database is online and working
 */
class CheckDatabaseRequest extends MySQLRequest {

	/** Error states */
	const CONNECTION_FAILURE ="mysqlrequest.connection.failure";
	const MYSQLI_ERROR ="mysqlrequest.table.creation.error";

	public function __construct(MySQLBanDatabase $database) {
		parent::__construct($database->getCredentials());
	}

	/**
	 * Attempt to connect to the database
	 */
	public function onRun() {
		$mysqli = $this->getMysqli();
		if($mysqli->connect_error) {
			$this->setResult([self::CONNECTION_FAILURE, $mysqli->connect_error]);
			return;
		}
		$mysqli->query("
			CREATE TABLE IF NOT EXISTS bans (
				id INT AUTO_INCREMENT PRIMARY KEY,
				username VARCHAR(16),
				ip VARCHAR(50) DEFAULT '0.0.0.0',
				uid VARCHAR(128),
				expires INT DEFAULT 0,
				created INT DEFAULT 0,
				reason VARCHAR(256),
				issuer_name VARCHAR(16),
				valid BIT DEFAULT 1
			)");
		if(isset($mysqli->error) and $mysqli->error) {
			$mysqli->close();
			$this->setResult([self::MYSQLI_ERROR, $mysqli->error]);
			return;
		}
		$mysqli->close();
	}

	/**
	 * @param Server $server
	 */
	public function onCompletion(Server $server) {
		$plugin = $this->getCore($server);
		if($plugin instanceof Main and $plugin->isEnabled()) {
			$result = $this->getResult();
			switch($result[0]) {
				default:
					$server->getLogger()->debug("Successfully completed CheckDatabaseRequest for ban database!");
					return;
				case self::CONNECTION_FAILURE:
					$server->getLogger()->debug("Failed to complete CheckDatabaseRequest for ban database due to a connection error");
					throw new \RuntimeException($result[1]);
				case self::MYSQLI_ERROR:
					$server->getLogger()->debug("Failed to complete CheckDatabaseRequest for ban database due to a mysqli error");
					throw new \RuntimeException($result[1]);
			}
		} else {
			$server->getLogger()->debug("Attempted to complete CheckDatabaseRequest for ban database while Components plugin ins't enabled!");
			throw new PluginException("Components plugin isn't enabled!");
		}
	}

}