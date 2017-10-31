<?php

/*
 * Copyright (C) 2017 Javier Samaniego García <jsamaniegog@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

require_once GLPI_ROOT . '/plugins/infoblox/lib/InfobloxWapiQuery.php';

/**
 * Description of PluginInfobloxCron
 * @package PluginInfoblox
 * @author Javier Samaniego García <jsamaniegog@gmail.com>
 */
class PluginInfobloxCron extends CommonDBTM {

    static function getTypeName($nb = 0) {
        return __('Infoblox Cron', 'infoblox');
    }

    private static function getServersAndEntities() {
        // get infoblox servers and its entities
        $server = new PluginInfobloxServer();
        foreach ($server->find() as $server) {
            $server['affected_entities'] = array();

            $server['affected_entities'][] = $server['entities_id'];

            if ($server['is_recursive'] == 1) {
                $sons = getSonsOf("glpi_entities", $server['entities_id']);
                foreach ($sons as $value) {
                    $server['affected_entities'][] = $value;
                }
            }

            $infoblox_servers[] = $server;
        }

        return $infoblox_servers;
    }

    /**
     * This function searchs in GLPI all types of host configured and add or
     * update the infoblox database.
     * @param CronTask $task A CronTask object.
     * @param InfobloxWapiQuery $infoblox
     * @param array $affected_entities
     * @param array $state_ids Array of state_id
     * @param bool $configure_for_dns 
     * @param bool $configure_for_dhcp
     * @param string $logPrefixError 
     * @param array $assets
     * @param bool $ipam If you want add hosts to manage the IP addresses.
     */
    private static function addAllHostToInfobloxHosts(CronTask $task, 
        InfobloxWapiQuery $infoblox, array $affected_entities, array $state_ids, 
        $fqdns_id, $configure_for_dns = true, $configure_for_dhcp = true, 
        $logPrefixError = "Error: ", 
        $assets = array('Computer', 'Phone', 'Peripheral', 'NetworkEquipment', 'Printer'),
        $ipam = true, $dns = true, $dhcp = true, $create_ptr = true
    ) {
        $toReturn = true;

        $state_ids[] = "null";
        $state_ids[] = "0";

        foreach ($assets as $asset) {
            $item = new $asset();

            $andFqdns = ($configure_for_dns and $fqdns_id != 0) ? "and fqdns_id = " . $fqdns_id : "" ;
            
            // search for items that has a networkport and networkname
            $records = $item->find(
                "entities_id in (" . implode(",", $affected_entities) . ") "
                . "and states_id in (" . implode(",", $state_ids) . ") "
                . "and id in (select DISTINCT np.items_id "
                . " FROM glpi_networkports np, glpi_networknames nn, glpi_ipaddresses ip "
                . " WHERE np.entities_id in (" . implode(",", $affected_entities) . ") "
                . " and np.itemtype = '$asset' "
                . " and nn.itemtype = 'NetworkPort' and np.id = nn.items_id " . str_replace("fqdns_id", "nn.fqdns_id", $andFqdns)
                . " and ip.itemtype = 'NetworkName' and nn.id = ip.items_id) "
                . "and id not in (select items_id from glpi_plugin_infoblox_syncs where synchronized = 1 and itemtype = '$asset')"
                //. " and name = 'hcuve05589'"
            );

            // search for ip and mac address
            foreach ($records as $id_asset => $record) {
                // device information
                $itemType = $asset . "Type";
                $itemType = new $itemType();
                $itemType->getFromDB($record[strtolower($asset) . 'types_id']);
                $record['device_type'] = $itemType->getField("name");
                
                $itemManufacturer = new Manufacturer();
                $itemManufacturer->getFromDB($record['manufacturers_id']);
                $record['device_vendor'] = trim($itemManufacturer->getField('name'));
                
                $itemLocation = new Location();
                $itemLocation->getFromDB($record['locations_id']);
                $record['device_location'] = trim($itemLocation->getField('completename'));
                
                $record['device_description'] = $record['comment'];
                
                $np = new NetworkPort();
                $nps = $np->find("is_deleted = 0 and itemtype = '$asset' and items_id = " . $id_asset);

                // initialize arrays (must be here, before NetworkPorts loop)
                $ipv4addrs = array();
                $ipv6addrs = array();
                
                foreach ($nps as $id_np => $np) {
                    $mac = $np['mac'];

                    // only ports with mac address can be added
                    if (empty($mac)) {
                        continue;
                    }

                    $nn = new NetworkName();
                    $nns = $nn->find("is_deleted = 0 and itemtype = 'NetworkPort' and items_id = " . $id_np . " $andFqdns");

                    // to use after
                    $originalName = $record['name'];
                    
                    foreach ($nns as $id_nn => $nn) {
                        
                        $networkName = $originalName;
                        
                        if ($nn['name'] != "") {
                            $networkName = $nn['name'];
                        }
                        
                        // set name based on fqdn
                        if ($configure_for_dns and $fqdns_id != 0) {
                            $fqdns = new FQDN();
                            if ($fqdns->getFromDB($fqdns_id)) {
                                $record['name'] = $networkName . "." . $fqdns->getField("fqdn");
                            }
                        }
                        
                        // set aliases
                        $aliases = new NetworkAlias();
                        $aliases = $aliases->find("networknames_id = $id_nn $andFqdns");
                        foreach ($aliases as $alias) {
                            $fqdns = new FQDN();
                            if ($fqdns->getFromDB($alias["fqdns_id"])) {
                                $alias['name'] .= "." . $fqdns->getField("fqdn");
                            }
                            
                            $record['aliases'][] = $alias['name'];
                            
                            $record['aliases'] = array_values(array_unique($record['aliases']));
                        }
                        
                        // set ip's
                        $ipv4addrs = array_merge($ipv4addrs, self::getIpsArrayForInfobloxQuery($id_nn, $mac, $configure_for_dhcp, '4'));
                        $ipv6addrs = array_merge($ipv6addrs, self::getIpsArrayForInfobloxQuery($id_nn, $mac, $configure_for_dhcp, '6'));
                        
                        if (!empty($ipv4addrs)) {
                            $record['ipv4addrs'] = $ipv4addrs;
                        }
                        if (!empty($ipv6addrs)) {
                            $record['ipv6addrs'] = $ipv6addrs;
                        }

                        // add or udpate the host
                        if (!empty($ipv4addrs) or ! empty($ipv6addrs)) {
                            // add hosts
                            if ($ipam == true){
                                $result = self::addInfobloxHost(
                                        $infoblox, $record, $configure_for_dns
                                );
                                
                                $toReturn = self::checkResult($result, $task, $infoblox, $logPrefixError, $record['name'], $id_asset, $asset);
                            }
                            
                            // add A record to dns (only if it hasn't do it by host addition)
                            if ($toReturn != false and (($ipam == false and $dns == true) 
                                or ($ipam == true and $configure_for_dns == false and $dns == true))) {
                                
                                // search for fqdn associated with the name
                                // if there isn't we don't add records
                                if ($nn['fqdns_id'] != '0') {
                                    $fqdns = new FQDN();
                                    $fqdns->getFromDB($nn['fqdns_id']);
                                    $record['name'] = $networkName . "." . $fqdns->getField("fqdn");
                                    
                                    // ipv4
                                    $result = self::addInfobloxA(
                                            $infoblox, $record
                                    );
                                    
                                    $toReturn = self::checkResult($result, $task, $infoblox, $logPrefixError, $record['name'], $id_asset, $asset);
                                    
                                    // ipv6
                                    if ($toReturn != false) {
                                        $result = self::addInfobloxAAAA(
                                                $infoblox, $record
                                        );

                                        $toReturn = self::checkResult($result, $task, $infoblox, $logPrefixError, $record['name'], $id_asset, $asset);
                                    }
                                    
                                    // cnames
                                    if ($toReturn != false) {
                                        $result = self::addInfobloxCNAME(
                                                $infoblox, $record
                                        );

                                        $toReturn = self::checkResult($result, $task, $infoblox, $logPrefixError, $record['name'], $id_asset, $asset);
                                    }
                                    
                                    // ptr
                                    if ($toReturn != false and $create_ptr) {
                                        $result = self::addInfobloxPTR(
                                                $infoblox, $record
                                        );

                                        $toReturn = self::checkResult($result, $task, $infoblox, $logPrefixError, $record['name'], $id_asset, $asset);
                                    }
                                }
                                
                            }
                            
                            // add dhcp record
                            if ($toReturn != false and (($ipam == false and $dhcp == true) 
                                or ($ipam == true and $configure_for_dhcp == false and $dhcp == true))) {
                                $result = self::addInfobloxFixedaddress(
                                        $infoblox, $record
                                );
                                
                                $toReturn = self::checkResult($result, $task, $infoblox, $logPrefixError, $record['name'], $id_asset, $asset);
                            }
                        }

                        //$toReturn = self::checkResult($result, $task, $infoblox, $logPrefixError, $record['name'], $id_asset, $asset);

                        // todo: eliminar estas líneas
                        if (isset($result)) {
                            $count = (!isset($count)) ? 0 : $count + 1;
                            if ($count >= 1) {
                                return $toReturn;
                            }
                        }
                    }
                }
            }
        }

        return $toReturn;
    }
    
    private static function checkResult($result, $task, $infoblox, $logPrefixError, $assetName, $id_asset, $asset) {
        $toReturn = true;
        
        // check the result, if it has an error we log it
        if (isset($result) and $result === false) {
            $toReturn = false;

            // log error for crontask
            $task->log($logPrefixError . Html::link(
                    $assetName, 
                    $asset::getFormURLWithID($id_asset, true)
                    ) . " - " . $infoblox->getError()); 
            // sync log table of infoblox plugin
            self::syncLog($id_asset, $asset, false, $logPrefixError . $infoblox->getError());

        } else{
            self::syncLog($id_asset, $asset, true);
        }
        
        return $toReturn;
    }
    
    /**
     * Return the method and asign the reference to the object if exists.
     * @param InfobloxWapiQuery $infoblox
     * @param string $object Infoblox object (example: record:host)
     * @param string $value Value to search in the (unique) field
     * @param string $name Name of the (unique) field to search
     * @return string
     * @throws Exception
     */
    private static function getMethodToSend(InfobloxWapiQuery $infoblox, &$object, $value, $field = "name") {
        // set the method to add
        $method = "POST";

        if (!isset($value) or $value == "") {
            throw new Exception(__METHOD__ . ": Arguments error");
        }
        
        // search for host to update
        $result = $infoblox->query(
            $object, array(
            $field => strtolower($value)
            )
        );

        // set the object to add or update
        if ($result) {
            // set the method to update
            $method = "PUT";
            // and set the host reference to update
            $object = $result[0]['_ref'];
        }
        
        return $method;
    }
    
    private static function addOrUpdate(InfobloxWapiQuery $infoblox, $object, $fields, $method = "GET", $function = null) {
        $fields = self::trimArray($fields);
        return $infoblox->query(
                $object, $fields, $function, $method
        );
    }
    
    /**
     * Generic add or update infoblox.
     * @param InfobloxWapiQuery $infoblox
     * @param string $object
     * @param array $hostData
     * @param string $fieldToSearch (Unique) field in which to search
     * @return type
     */
    private static function addInfobloxRecord(InfobloxWapiQuery $infoblox, $object, $fields, $fieldToSearch = "name") {
        $method = self::getMethodToSend($infoblox, $object, strtolower($fields[$fieldToSearch]), $fieldToSearch);

        return self::addOrUpdate($infoblox, $object, $fields, $method);
    }
    
    /**
     * Add or update a single host to infoblox.
     * @param InfobloxWapiQuery $infoblox
     * @param array $hostData
     * @param bool $configure_for_dns
     */
    private static function addInfobloxHost(
        InfobloxWapiQuery $infoblox, 
        array $hostData, 
        $configure_for_dns = true
    ) {
        // fields to add or update
        $fields = array(
            "name" => strtolower($hostData['name']),
            "aliases" => $hostData['aliases'],
            "configure_for_dns" => $configure_for_dns,
            "device_type" => trim($hostData['device_type']),
            "device_vendor" => trim($hostData['device_vendor']),
            "device_location" => trim($hostData['device_location']),
            "device_description" => trim($hostData['device_comment'])
        );
        
        // the arrays of ip's for add or update
        if (isset($hostData['ipv4addrs'])) {
            $fields['ipv4addrs'] = $hostData['ipv4addrs'];
        }
        if (isset($hostData['ipv6addrs'])) {
            $fields['ipv6addrs'] = $hostData['ipv6addrs'];
        }
        
        return self::addInfobloxRecord($infoblox, "record:host", $fields);
    }

    /**
     * Add or update a single A record to infoblox.
     * @param InfobloxWapiQuery $infoblox
     * @param array $hostData
     */
    private static function addInfobloxA(InfobloxWapiQuery $infoblox, array $hostData) {
        $toReturn = true;
        
        foreach ($hostData['ipv4addrs'] as $ipv4addr) {
            // fields to add or update
            $fields = array(
                "name" => strtolower($hostData['name']),
                "ipv4addr" => $ipv4addr['ipv4addr']
            );
            
            $toReturn = self::addInfobloxRecord($infoblox, "record:a", $fields);
            
            if (!$toReturn) {
                return $toReturn;
            }
        }
        
        return $toReturn;
    }
    
    /**
     * Add or update a single CNAME record to infoblox.
     * @param InfobloxWapiQuery $infoblox
     * @param array $hostData
     */
    private static function addInfobloxCNAME(InfobloxWapiQuery $infoblox, array $hostData) {
        $toReturn = true;
        
        
        // fields to add or update
        foreach ($hostData['aliases'] as $alias) {
            $fields = array(
                "name" => strtolower($alias),
                "canonical" => strtolower($hostData['name'])
            );
            
            $toReturn = self::addInfobloxRecord($infoblox, "record:cname", $fields);
            
            if (!$toReturn) {
                return $toReturn;
            }
        }
        
        return $toReturn;
    }
    
    /**
     * Add or update a single PTR record to infoblox.
     * @param InfobloxWapiQuery $infoblox
     * @param array $hostData
     */
    private static function addInfobloxPTR(InfobloxWapiQuery $infoblox, array $hostData) {
        $toReturn = true;
        
        list(,$domain) = explode(".", $hostData['name'], 2);
        
        foreach ($hostData['ipv4addrs'] as $ipv4addr) {
            //list($a, $b, $c, $d) = explode(".", $ipv4addr['ipv4addr']);
            
            // fields to add or update
            $fields = array(
                //"name" => "$d.$c.$b.$a",
                "ipv4addr" => $ipv4addr['ipv4addr'],
                "ptrdname" => $domain
            );
            
            $toReturn = self::addInfobloxRecord($infoblox, "record:ptr", $fields, "ipv4addr");
            
            if (!$toReturn) {
                return $toReturn;
            }
        }
        
        foreach ($hostData['ipv6addrs'] as $ipv4addr) {
            //list($a, $b, $c, $d) = explode(".", $ipv4addr['ipv6addr']);
            
            // fields to add or update
            $fields = array(
                //"name" => "$d.$c.$b.$a",
                "ipv6addr" => $ipv4addr['ipv6addr'],
                "ptrdname" => $domain
            );
            
            $toReturn = self::addInfobloxRecord($infoblox, "record:ptr", $fields, "ipv6addr");
            
            if (!$toReturn) {
                return $toReturn;
            }
        }
        
        return $toReturn;
    }
    
    /**
     * Add or update a single PTR record to infoblox.
     * @param InfobloxWapiQuery $infoblox
     * @param array $hostData
     */
    private static function addInfobloxFixedaddress(InfobloxWapiQuery $infoblox, array $hostData) {
        $toReturn = true;
        
        foreach ($hostData['ipv4addrs'] as $ipv4addr) {
            $fields = array(
                "name" => strtolower($hostData['name']),
                "ipv4addr" => $ipv4addr['ipv4addr'],
                "mac" => $ipv4addr['mac']
            );
            
            $toReturn = self::addInfobloxRecord($infoblox, "fixedaddress", $fields, "ipv4addr");
            
            if (!$toReturn) {
                return $toReturn;
            }
        }
        
        foreach ($hostData['ipv6addrs'] as $ipv6addr) {
            $fields = array(
                "name" => strtolower($hostData['name']),
                "ipv6addr" => $ipv6addr['ipv6addr'],
                "duid" => $ipv6addr['duid']
            );
            
            $toReturn = self::addInfobloxRecord($infoblox, "ipv6fixedaddress", $fields, "ipv6addr");
            
            if (!$toReturn) {
                return $toReturn;
            }
        }
        
        return $toReturn;
    }
    
    /**
     * Add or update a single AAAA record to infoblox.
     * @param InfobloxWapiQuery $infoblox
     * @param array $hostData
     */
    private static function addInfobloxAAAA(InfobloxWapiQuery $infoblox, array $hostData) {
        $toReturn = true;
        
        foreach ($hostData['ipv6addrs'] as $ipv6addr) {
            // fields to add or update
            $fields = array(
                "name" => strtolower($hostData['name']),
                "ipv6addr" => $ipv6addr['ipv6addr']
            );
            
            $toReturn = self::addInfobloxRecord($infoblox, "record:aaaa", $fields);
            
            if (!$toReturn) {
                return $toReturn;
            }
        }
        
        return $toReturn;
    }
    
    /**
     * Add or update sync log.
     * @param int $id_asset
     * @param string $asset
     * @param bool $syncronized
     * @param string $error
     */
    private static function syncLog($id_asset, $asset, $syncronized, $error = null) {
        if ($error == null) {
            $error = 'NULL';
        }
        
        $sync = new PluginInfobloxSync();
        if ($sync->getFromDBByCrit(array(
            "items_id" => $id_asset,
            "itemtype" => $asset
        ))) {
            $sync->fields['synchronized'] = $syncronized;
            $sync->fields['datetime'] = date("Y-m-d H:i:s");
            $sync->fields['error'] = $error;
            $sync->updateInDB(array(
                'synchronized',
                'datetime',
                'error'
            ));
        } else {
            $sync->add(array(
                'items_id'     => $id_asset,
                'itemtype'     => $asset,
                'synchronized' => $syncronized,
                'datetime'     => date("Y-m-d H:i:s"),
                'error'        => $error
            ));
        }
    }

    /**
     * Return array of IPs ready for infoblox query.
     * @param int $id_nn The ID of networkname
     * @param string $mac
     * @param bool $configure_for_dhcp
     * @param string $version 4 or 6
     * @return array
     */
    private static function getIpsArrayForInfobloxQuery($id_nn, $mac, $configure_for_dhcp, $version = '4') {
        $ip = new IPAddress();

        $toReturn = array();

        $ips = $ip->find("is_deleted = 0 and itemtype = 'NetworkName' and items_id = $id_nn and version = $version");
        foreach ($ips as $ip) {

            if ($ip['name'] == '0.0.0.0'
                or $ip['name'] == '127.0.0.1'
                or trim($ip['name']) == ""
            ) {
                continue;
            }

            if ($version == '4') {
                $toReturn[] = array(
                    "ipv" . $version . "addr" => $ip['name'],
                    "mac" => $mac,
                    // be careful, this options generate a new record if is changed, by default is true
                    "configure_for_dhcp" => $configure_for_dhcp
                );
            } elseif ($version == '6') {
                $toReturn[] = array(
                    "ipv" . $version . "addr" => $ip['name'],
                    "duid" => $mac,
                    // be careful, this options generate a new record if is changed, by default is true
                    "configure_for_dhcp" => $configure_for_dhcp
                );
            }
        }

        return $toReturn;
    }
    
    /**
     * 
     * @param array $array
     * @param bool $filter if the empty values must be filtered(unset) of the array.
     * @return array
     * @throws Exception
     */
    private static function trimArray($array, $filter = true) {
        if (!is_array($array)) {
            throw new Exception(__METHOD__ . ": Expected an array.");
        }

        foreach ($array as $key => $value) {
            if (is_null($value)) {
                unset($array[$key]);
                continue;
            }
            
            if (is_array($value)) {
                if (empty($value)) {
                    unset($array[$key]);
                } else {
                    $array[$key] = self::trimArray($value);
                }
            } elseif (is_string($value)) {
                $array[$key] = trim($value);

                if ($array[$key] == "") {
                    unset($array[$key]);
                }
            }
        }

        return $array;
    }

    /**
     * Check if there is anything to do with a server.
     * @param array $server
     * @return boolean
     */
    private static function isThereAnythingToDo(array $server) {
        if ($server['ipam'] == '0'
            and $server['devices'] == '0'
            and $server['dhcp'] == '0'
            and $server['dns'] == '0'
        ) {
            return false;
        }

        return true;
    }

    /**
     * Executed by cron. This task search the name for each IP address and save 
     * it in database.
     * @param CronTask $task A CronTask object.
     */
    static function cronInfoblox(CronTask $task) {
        $infoblox_servers = self::getServersAndEntities();

        // if no servers finish task
        if (empty($infoblox_servers)) {
            $task->log(__("No Infoblox servers configured.", "infoblox"));
            return false;
        }

        $toReturn = true;

        foreach ($infoblox_servers as $server) {
            // for logs
            $logPrefix = "[" . $server['name'] . "] ";

            $task->addVolume(1);

            // if import options are not activated
            if (!self::isThereAnythingToDo($server)) {
                $task->log($logPrefix . __('Nothing to do', 'infoblox'));
                continue;
            }

            $infoblox = new InfobloxWapiQuery(
                $server['address'], $server['user'], $server['password'], $server['wapi_version']
            );

            // NOTE: Microsoft servers cannot be updated when you add a host 
            // you must do it independently. For that reason is the option 
            // "is_ad_dns_zone".
            $configure_for_dhcp = ($server['dhcp'] == '1' and $server['is_ad_dhcp'] == '0') ?
                true :
                false;

            $configure_for_dns = ($server['dns'] == '1' and $server['is_ad_dns_zone'] == '0') ?
                true :
                false;

            $ipam = ($server['ipam'] == '1') ? true : false;
            $dns = ($server['dns'] == '1') ? true : false;
            $dhcp = ($server['dhcp'] == '1') ? true : false;
            $create_ptr = ($server['create_ptr'] == '1') ? true : false;
            
            // hosts
            $result = self::addAllHostToInfobloxHosts(
                $task, 
                $infoblox, 
                $server['affected_entities'], 
                PluginInfobloxServer::getStateIds($server['id']), 
                $server['fqdns_id'],
                $configure_for_dns, 
                $configure_for_dhcp, 
                $logPrefix,
                self::getAssetsToSync($server),
                $ipam, $dns, $dhcp, $create_ptr
            );

            if (!$result) {
                $toReturn = false;
            }

            // devices
            if ($server['devices'] == '1') {
                // todo: sync network devices
            }
        }

        return $toReturn;
    }

    /**
     * Return configured assets to sync
     * @param array $server Asoc array record of infoblox server table.
     */
    static private function getAssetsToSync(array $server) {
        $assets = array();
        
        if ($server['computers']) {
            $assets[] = 'Computer';
        }
        if ($server['phones']) {
            $assets[] = 'Phone';
        }
        if ($server['peripherals']) {
            $assets[] = 'Peripheral';
        }
        if ($server['networkequipments']) {
            $assets[] = 'NetworkEquipment';
        }
        if ($server['printers']) {
            $assets[] = 'Printer';
        }
        
        return $assets;
    }
    
}
