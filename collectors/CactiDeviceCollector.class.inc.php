<?php

/**
 * Class CactiDeviceCollector
 *
 * @author Thomas Casteleyn <thomas.casteleyn@super-visions.com>
 * @license http://opensource.org/licenses/AGPL-3.0
 */
class CactiDeviceCollector extends CactiCollector
{
	/**
	 * @var array
	 */
	protected static $aDevices;
	/**
	 * @var LookupTable
	 */
	protected $oOSVersionLookup;
	/**
	 * @var LookupTable
	 */
	protected $oModelLookup;
	
	/**
	 * @return array
	 * @throws Exception
	 */
	public static function GetDevices()
	{
		if (is_null(static::$aDevices))
		{
			$oNetworkDeviceTypeMappings = new MappingTable('network_device_type_mapping');
			$oBrandMappings = new MappingTable('brand_mapping');
			$oModelMappings = new MappingTable('model_mapping');
			$oOSVersionMappings = new MappingTable('os_version_mapping');
			$aOIDs = array(
				'1.3.6.1.2.1.1.1.0', // sysDescr
				'1.3.6.1.2.1.1.4.0', // sysContact
				'1.3.6.1.2.1.1.5.0', // sysName
				'1.3.6.1.2.1.1.6.0', // sysLocation
			);

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
  h.snmp_port,
  h.snmp_version,
  h.snmp_community,
  h.snmp_timeout,
  h.snmp_auth_protocol,
  h.snmp_username,
  h.snmp_password,
  h.snmp_context,
  h.snmp_priv_protocol,
  h.snmp_priv_passphrase,
  group_concat(snmp_query_id) AS query_ids
FROM `host` AS h
LEFT JOIN host_template AS ht
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
					
					// Prepare values
					$aComments = array();
					if (!empty($oHost->notes)) $aComments[] = $oHost->notes;
					$sBrand = null;
					$sModel = null;
					$sIOSVersion = null;
					$sIP = filter_var($oHost->hostname, FILTER_VALIDATE_IP) ? $oHost->hostname : gethostbyname($oHost->hostname);
					
					// Start SNMP session
					$sHostname = sprintf('%s:%d', $oHost->hostname, $oHost->snmp_port);
					switch ($oHost->snmp_version)
					{
						case 1:
							$oSession = new SNMP(SNMP::VERSION_1, $sHostname, $oHost->snmp_community, $oHost->snmp_timeout*1000);
							break;
						case 2:
							$oSession = new SNMP(SNMP::VERSION_2c, $sHostname, $oHost->snmp_community, $oHost->snmp_timeout*1000);
							break;
						case 3:
							$oSession = new SNMP(SNMP::VERSION_3, $sHostname, $oHost->snmp_username, $oHost->snmp_timeout*1000);
							if ($oHost->snmp_priv_protocol == '[None]') $oSession->setSecurity('authNoPriv', $oHost->snmp_auth_protocol, $oHost->snmp_password, null, null, $oHost->snmp_context);
							else $oSession->setSecurity('authPriv', $oHost->snmp_auth_protocol, $oHost->snmp_password, $oHost->snmp_priv_protocol, $oHost->snmp_priv_passphrase, $oHost->snmp_context);
							break;
					}
					
					// Get additional system info
					if (isset($oSession))
					{
						$oSession->exceptions_enabled = SNMP::ERRNO_ANY;
						$oSession->valueretrieval = SNMP_VALUE_PLAIN;
						try {
							list($sSysDescr, $sSysContact, $sSysName, $sSysLocation) = array_values($oSession->get($aOIDs));
							
							// Add additional info to description field
							if ($sSysName != $oHost->description) $aComments[] = sprintf('sysName: %s', $sSysName);
							if (!empty($sSysContact)) $aComments[] = sprintf('sysContact: %s', $sSysContact);
							if (!empty($sSysLocation)) $aComments[] = sprintf('sysLocation: %s', $sSysLocation);
							$aComments[] = sprintf('sysDescr: %s', $sSysDescr);
							
							// Map Brand, Model and IOS Version
							$sBrand = $oBrandMappings->MapValue($sSysDescr);
							$sModel = $oModelMappings->MapValue($sSysDescr);
							$sIOSVersion = $oOSVersionMappings->MapValue($sSysDescr);
						}
						catch (SNMPException $oEx)
						{
							Utils::Log(LOG_WARNING, sprintf('SNMP error for %s: %s', $oHost->description, $oEx->getMessage()));
						}
						
						// Stop SNMP session
						$oSession->close();
						unset($oSession);
					}
					else Utils::Log(LOG_INFO, sprintf('No SNMP lookup for %s.', $oHost->description));

					static::$aDevices[] = array(
						'primary_key' => $oHost->id,
						'name' => $oHost->description,
						'org_id' => $sDefaultOrg,
						'networkdevicetype_id' => $oNetworkDeviceTypeMappings->MapValue($oHost->template_name, 'Other'),
						'description' => implode(PHP_EOL, $aComments),
						'brand_id' => $sBrand,
						'model_id' => $sModel,
						'iosversion_id' => $sIOSVersion,
						'managementip_id' => filter_var($sIP, FILTER_VALIDATE_IP) ? $sIP : null,
						'query_ids' => empty($oHost->query_ids) ? array() : explode(',', $oHost->query_ids),
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
			$aDevice = static::$aDevices[$this->idx++];
			unset($aDevice['query_ids']);
			return $aDevice;
		}
		return false;
	}
	
	protected function MustProcessBeforeSynchro()
	{
		// We must reprocess the CSV data obtained from vSphere
		// to lookup the Brand/Model and OSFamily/OSVersion in iTop
		return true;
	}
	
	protected function InitProcessBeforeSynchro()
	{
		// Retrieve the identifiers of the IOSVersion since we must do a lookup based on two fields: Brand + Version
		// which is not supported by the iTop Data Synchro... so let's do the job of an ETL
		$this->oOSVersionLookup = new LookupTable('SELECT IOSVersion', array('brand_id_friendlyname', 'name'));
		
		// Retrieve the identifiers of the Model since we must do a lookup based on two fields: Brand + Model
		// which is not supported by the iTop Data Synchro... so let's do the job of an ETL
		$this->oModelLookup = new LookupTable('SELECT Model', array('brand_id_friendlyname', 'name'));
	}
	
	protected function ProcessLineBeforeSynchro(&$aLineData, $iLineIndex)
	{
		// Process iosversion_id only if brand and version is set (to reduce warnings)
		if (empty($aLineData[5]) || empty($aLineData[7])) $aLineData[7] = null;
		else $this->oOSVersionLookup->Lookup($aLineData, array('brand_id', 'iosversion_id'), 'iosversion_id', $iLineIndex);
		
		// Process model_id only if brand and model is set (to reduce warnings)
		if (empty($aLineData[5]) || empty($aLineData[6])) $aLineData[6] = null;
		else $this->oModelLookup->Lookup($aLineData, array('brand_id', 'model_id'), 'model_id', $iLineIndex);
	}
	
	public function AttributeIsOptional($sAttCode)
	{
		// san_list comes from the 'Advanced Storage Management' module
		if ($sAttCode == 'san_list') return true;
		
		return parent::AttributeIsOptional($sAttCode);
	}
}
