<?php

/**
 * Class CactiInterfaceCollector
 *
 * @author Thomas Casteleyn <thomas.casteleyn@super-visions.com>
 * @license http://opensource.org/licenses/AGPL-3.0
 */
class CactiInterfaceCollector extends CactiCollector
{
	protected $aInterfaces = null;
	
	/**
	 * @return bool
	 */
	public function Prepare()
	{
		$bRet = parent::Prepare();
		if (!$bRet) return false;
		
		if (is_null($this->aInterfaces))
		{
			$sQuery = Utils::GetConfigurationValue('interface_sql_query');
			$sPath = Utils::GetConfigurationValue('cacti_cli_path');

			$oDB = static::ConnectDB();
			$aDevices = CactiDeviceCollector::GetDevices();
			$aDeviceNames = array();
			
			if (file_exists($sPath.'/poller_reindex_hosts.php'))
			{
				$sReindexCommand =  sprintf( '%s %s/%s', PHP_BINARY, $sPath, 'poller_reindex_hosts.php --id=%d --qid=%d');
			}
			
			// Process devices and reindex queries
			foreach ($aDevices as $aDevice)
			{
				$aDeviceNames[$aDevice['primary_key']] = $aDevice['name'];
				if (isset($sReindexCommand)) {
					
					Utils::Log(LOG_DEBUG, sprintf('Reindexing %s...', $aDevice['name']));
					foreach ($aDevice['query_ids'] as $iDataQuery) {
						$sReturn = exec(sprintf($sReindexCommand, $aDevice['primary_key'], $iDataQuery));
						if (Utils::$iConsoleLogLevel >= LOG_INFO) echo $sReturn;
					}
					if (Utils::$iConsoleLogLevel >= LOG_DEBUG) echo PHP_EOL;
				}
			}
			if (Utils::$iConsoleLogLevel == LOG_INFO) echo PHP_EOL;
			
			if ($oResult = $oDB->query($sQuery))
			{
				while ($oInterface = $oResult->fetch_object())
				{
					if (!in_array($oInterface->connectableci_id, $aDeviceNames)) continue;
					
					$aInterface = array(
						'primary_key' => $oInterface->primary_key,
						'name'        => $oInterface->name,
						'comment'     => $oInterface->comment,
						'speed'       => $oInterface->speed,
						'macaddress'  => ($oInterface->macaddress != '00:00:00:00:00:00') ? $oInterface->macaddress : '',
						'connectableci_id' => $oInterface->connectableci_id,
					);

					// Process extra comment fields
					$aComments = array();
					if (!empty($oInterface->comment)) $aComments[] = $oInterface->comment;
					foreach (get_object_vars($oInterface) as $sField => $sValue)
					{
						if (!isset($aInterface[$sField]) && !empty($sValue) && $sValue != $aInterface['name'])
						{
							$aComments[] = sprintf('%s: %s', $sField, $sValue);
						}
					}
					$aInterface['comment'] = implode(PHP_EOL, $aComments);

					$this->aInterfaces[] = $aInterface;
				}
			}
			else
			{
				throw new mysqli_sql_exception($oDB->error, $oDB->errno);
			}
		}
		
		$this->idx = 0;
		return true;
	}

	/**
	 * @return array|bool
	 */
	public function Fetch()
	{
		if ($this->idx < count($this->aInterfaces))
		{
			return $this->aInterfaces[$this->idx++];
		}
		return false;
	}
}
