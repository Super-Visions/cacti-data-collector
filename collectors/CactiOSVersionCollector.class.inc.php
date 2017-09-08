<?php

/**
 * Class CactiOSVersionCollector
 *
 * @author Thomas Casteleyn <thomas.casteleyn@super-visions.com>
 * @license http://opensource.org/licenses/AGPL-3.0
 */
class CactiOSVersionCollector extends CactiCollector
{
	protected $aOSVersion;
	
	public function Prepare()
	{
		$bRet = parent::Prepare();
		$this->idx = 0;
		
		$aDevices = CactiDeviceCollector::GetDevices();
		$aTmp = array();
		
		foreach ($aDevices as $aDevice)
		{
			$sPrimaryKey = sprintf('%s %s', $aDevice['brand_id'], $aDevice['iosversion_id']);
			if (!isset($aTmp[$sPrimaryKey]) && !empty($aDevice['brand_id']) && !empty($aDevice['iosversion_id']))
				$aTmp[$sPrimaryKey] = array(
					'primary_key' => $sPrimaryKey,
					'name' => $aDevice['iosversion_id'],
					'brand_id' => $aDevice['brand_id'],
				);
		}
		
		// Build a zero-based array
		$this->aOSVersion = array_values($aTmp);
		return $bRet;
	}
	
	public function Fetch()
	{
		if ($this->idx < count($this->aOSVersion))
		{
			return $this->aOSVersion[$this->idx++];
		}
		return false;
	}
}
