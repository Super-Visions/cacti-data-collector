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
			$oNetworkDeviceTypeMappings = new MappingTable('network_device_type_mapping');

			$oDB = static::ConnectDB();

			$sDefaultOrg = Utils::GetConfigurationValue('default_org_id');
			$sDataQueries = Utils::GetConfigurationValue('interface_data_queries', '0');

			if (!preg_match('/^(\d+,)*\d+$/', $sDataQueries)) throw new InvalidArgumentException('interface_data_queries');
			$sQuery = sprintf("SELECT
  h.id,
  h.description,
  h.hostname,
  ht.name AS template_name,
  h.notes,
  group_concat(snmp_query_id) AS query_ids
FROM `host` AS h
JOIN host_template AS ht
  ON (ht.id = h.host_template_id)
LEFT JOIN host_snmp_query AS hsq
  ON (hsq.host_id = h.id AND hsq.snmp_query_id IN(%s))
WHERE disabled != 'on' AND h.status > 1
GROUP BY h.id;", $sDataQueries);

			if ($oResult = $oDB->query($sQuery))
			{
				while ($oHost = $oResult->fetch_object())
				{
					if ($sDataQueries != '0' && empty($oHost->query_ids))
					{
						Utils::Log(LOG_INFO, sprintf('Skipping device %s.', $oHost->description));
						continue;
					}

					static::$aDevices[] = array(
						'primary_key' => $oHost->id,
						'name' => $oHost->description,
						'org_id' => $sDefaultOrg,
						'networkdevicetype_id' => $oNetworkDeviceTypeMappings->MapValue($oHost->template_name, 'Other'),
						'description' => $oHost->notes,
					);
				}
			}
			else
			{
				throw new mysqli_sql_exception($oDB->error, $oDB->errno);
			}
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
			return static::$aDevices[$this->idx++];
		}
		return false;
	}
}
