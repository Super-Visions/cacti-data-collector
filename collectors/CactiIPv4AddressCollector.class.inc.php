<?php

/**
 * Class CactiIPv4AddressCollector
 *
 * @author Thomas Casteleyn <thomas.casteleyn@super-visions.com>
 * @license http://opensource.org/licenses/AGPL-3.0
 */
class CactiIPv4AddressCollector extends CactiCollector
{
	/**
	 * @var array
	 */
	protected $aAddress;
	/**
	 * @var RestClient
	 */
	protected $oRestClient;
	
	public function Prepare()
	{
		$bRet = parent::Prepare();
		if (!$bRet) return false;
		
		if (is_null($this->aAddress))
		{
			$sDefaultOrg = Utils::GetConfigurationValue('default_org_id');
			
			$aDevices = CactiDeviceCollector::GetDevices();
			$aTmp = array();
			
			foreach ($aDevices as $aDevice) if (filter_var($aDevice['managementip_id'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
			{
				$sIP = $aDevice['managementip_id'];
				$sShortName = $sDomainName = null;
				$sFQDN = gethostbyaddr($sIP);
				if (!filter_var($sFQDN, FILTER_VALIDATE_IP))
				{
					list($sShortName, $sDomainName) = explode('.', $sFQDN, 2);
					$sDomainName .= '.';
				}
				
				if (!isset($aTmp[$sIP]))
					$aTmp[$sIP] = array(
						'primary_key' => $sIP,
						'ip' => $sIP,
						'org_id' => $sDefaultOrg,
						'subnet_id' => null,
						'short_name' => $sShortName,
						'domain_id' => $sDomainName,
					);
			}
			
			// Build a zero-based array
			$this->aAddress = array_values($aTmp);
		}
		
		$this->idx = 0;
		return true;
	}
	
	public function Fetch()
	{
		if ($this->idx < count($this->aAddress))
		{
			return $this->aAddress[$this->idx++];
		}
		return false;
	}
	
	protected function MustProcessBeforeSynchro()
	{
		// We must reprocess the CSV data obtained from Cacti
		// to lookup the Organization in iTop
		return true;
	}
	
	protected function InitProcessBeforeSynchro()
	{
		$this->oRestClient = new RestClient();
	}
	
	protected function ProcessLineBeforeSynchro(&$aLineData, $iLineIndex)
	{
		if ($iLineIndex > 0)
		{
			$aRes = $this->oRestClient->Get('IPv4Subnet', sprintf('SELECT IPv4Subnet WHERE INET_ATON(ip) <= INET_ATON("%1$s") AND INET_ATON(broadcastip) > INET_ATON("%1$s")', $aLineData[1]), 'org_name');
			if ($aRes['code'] == 0 && count($aRes['objects']) > 0)
			{
				$aObject = current($aRes['objects']);
				$aLineData[2] = $aObject['fields']['org_name'];
				$aLineData[3] = $aObject['key'];
			}
		}
	}
}
