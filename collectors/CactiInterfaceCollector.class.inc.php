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

			$oDB = static::ConnectDB();
			$aDevices = CactiDeviceCollector::GetDevices();
			$aDeviceNames = array();
			
			// Process devices and reindex queries
			foreach ($aDevices as $aDevice)
			{
				$aDeviceNames[$aDevice['primary_key']] = $aDevice['name'];
			}
			
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
