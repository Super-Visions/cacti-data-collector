<?php

/**
 * Class CactiCollector
 *
 * @author Thomas Casteleyn <thomas.casteleyn@super-visions.com>
 * @license http://opensource.org/licenses/AGPL-3.0
 */
abstract class CactiCollector extends Collector
{
	protected $idx;
	protected static $oDB;
	
	/**
	 * Connect to the DB server and return connection identifier
	 *
	 * @return mysqli
	 * @throws Exception
	 */
	protected static function ConnectDB()
	{
		if (is_null(static::$oDB))
		{
			$sServer = Utils::GetConfigurationValue('cacti_db_host');
			$sLogin = Utils::GetConfigurationValue('cacti_db_user');
			$sPassword = Utils::GetConfigurationValue('cacti_db_pass');
			$sDatabase = Utils::GetConfigurationValue('cacti_db_database');

			mysqli_report(MYSQLI_REPORT_STRICT);

			// Connect
			Utils::Log(LOG_INFO, 'Connecting to '.$sServer);
			static::$oDB = new mysqli($sServer, $sLogin, $sPassword, $sDatabase);
		}
		
		return static::$oDB;
	}

}
