<?php

/**
 * Class CactiModelCollector
 *
 * @author Thomas Casteleyn <thomas.casteleyn@super-visions.com>
 * @license http://opensource.org/licenses/AGPL-3.0
 */
class CactiModelCollector extends CactiCollector
{
	protected $aModel;
	
	public function Prepare()
	{
		$bRet = parent::Prepare();
		$this->idx = 0;
		
		$aDevices = CactiDeviceCollector::GetDevices();
		$aTmp = array();
		
		foreach ($aDevices as $aDevice)
		{
			$sPrimaryKey = sprintf('%s %s', $aDevice['brand_id'], $aDevice['model_id']);
			if (!isset($aTmp[$sPrimaryKey]) && !empty($aDevice['brand_id']) && !empty($aDevice['model_id']))
				$aTmp[$sPrimaryKey] = array(
					'primary_key' => $sPrimaryKey,
					'name' => $aDevice['model_id'],
					'brand_id' => $aDevice['brand_id'],
					'type' => 'NetworkDevice'
				);
		}
		
		// Build a zero-based array
		$this->aModel = array_values($aTmp);
		return $bRet;
	}
	
	public function Fetch()
	{
		if ($this->idx < count($this->aModel))
		{
			return $this->aModel[$this->idx++];
		}
		return false;
	}
}
