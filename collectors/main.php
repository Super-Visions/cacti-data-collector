<?php
/**
 * main collector file to load and register the collectors
 *
 * @author Thomas Casteleyn <thomas.casteleyn@super-visions.com>
 * @license http://opensource.org/licenses/AGPL-3.0
 */

require_once(APPROOT.'collectors/CactiCollector.class.inc.php');
require_once(APPROOT.'collectors/CactiDeviceCollector.class.inc.php');
require_once(APPROOT.'collectors/CactiInterfaceCollector.class.inc.php');

// Register the collectors (one collector class per data synchro task to run)
// and tell the orchestrator in which order to run them

$iRank = 1;
Orchestrator::AddCollector($iRank++, 'CactiDeviceCollector');
Orchestrator::AddCollector($iRank++, 'CactiInterfaceCollector');

Orchestrator::AddRequirement('0.1', 'snmp');
