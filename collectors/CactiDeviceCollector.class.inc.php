<?php

/**
 * Class CactiDeviceCollector
 *
 * @author Thomas Casteleyn <thomas.casteleyn@super-visions.com>
 * @license http://opensource.org/licenses/AGPL-3.0
 */
class CactiDeviceCollector extends CactiCollector
{
	protected static $aDevices = null;
	
	/**
	 * @return array
	 * @throws Exception
	 */
	public static function GetDevices()
	{
		if (is_null(static::$aDevices))
		{
			$oDB = static::ConnectDB();

		}
		return static::$aDevices;
	}
	
	/**
	 * @return bool
	 */
	public function Prepare()
	{
		$bRet = parent::Prepare();
		if (!$bRet) return false;
		
		static::GetDevices();
		
		$this->idx = 0;
		return true;
	}

	/**
	 * @return array|bool
	 */
	public function Fetch()
	{
		if ($this->idx < count(static::$aDevices))
		{
			$aDevice = static::$aDevices[$this->idx++];
			return array(
				'primary_key' => $aDevice['primary_key'],
				'name' => $aDevice['name'],
				'networkdevicetype_id' => $aDevice['networkdevicetype'],
			);
		}
		return false;
	}
}
