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
     * @param type $configure_for_dhcp
     */
    private static function addAllHostToInfobloxHosts(CronTask $task, 
        InfobloxWapiQuery $infoblox, array $affected_entities, array $state_ids, 
        $fqdns_id, $configure_for_dns = true, $configure_for_dhcp = true, 
        $logPrefixError = "Error: "
    ) {
        $toReturn = true;

        $assets = array('Computer', 'Phone', 'Peripheral', 'NetworkEquipment', 'Printer');

        $state_ids[] = "null";
        $state_ids[] = "0";

        foreach ($assets as $asset) {
            $item = new $asset();

            $andFqdns = ($configure_for_dns and $fqdns_id != 0) ? "and fqdns_id = " . $fqdns_id : "" ;
            
            // search for items that has a networkport
            $records = $item->find(
                "entities_id in (" . implode(",", $affected_entities) . ") "
                . "and states_id in (" . implode(",", $state_ids) . ") "
                . "and id in (select DISTINCT np.items_id "
                . " FROM glpi_networkports np, glpi_networknames nn, glpi_ipaddresses ip "
                . " WHERE np.entities_id in (" . implode(",", $affected_entities) . ") "
                . " and np.itemtype = '$asset' "
                . " and nn.itemtype = 'NetworkPort' and np.id = nn.items_id " . str_replace("fqdns_id", "nn.fqdns_id", $andFqdns)
                . " and ip.itemtype = 'NetworkName' and nn.id = ip.items_id)"
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
                $nps = $np->find("itemtype = '$asset' and items_id = " . $id_asset);

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
                    $nns = $nn->find("itemtype = 'NetworkPort' and items_id = " . $id_np . " $andFqdns");

                    // to use after
                    $originalName = $record['name'];
                    
                    foreach ($nns as $id_nn => $nn) {
                        // set name based on fqdn
                        if ($configure_for_dns and $fqdns_id != 0) {
                            $fqdns = new FQDN();
                            if ($fqdns->getFromDB($fqdns_id)) {
                                $record['name'] = $originalName . "." . $fqdns->getField("fqdn");
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
                            $result = self::addInfobloxHost(
                                    $infoblox, $record, $configure_for_dns, $configure_for_dhcp
                            );
                        }

                        if (isset($result) and $result === false) {
                            $toReturn = false;
                            $task->log($logPrefixError . Html::link($record['name'], Computer::getFormURLWithID($id_asset, true)) . " - " . $infoblox->getError());
                        }

                        // todo: eliminar estas líneas
                        if (isset($result)) {
                            $count = (!isset($count)) ? 0 : $count + 1;
                            if ($count > 2)
                                return $toReturn;
                        }
                    }
                }
            }
        }

        return $toReturn;
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

        $ips = $ip->find("itemtype = 'NetworkName' and items_id = $id_nn and version = $version");
        foreach ($ips as $ip) {

            if ($ip['name'] == '0.0.0.0'
                or $ip['name'] == '127.0.0.1'
                or trim($ip['name']) == ""
            ) {
                continue;
            }

            $toReturn[] = array(
                "ipv" . $version . "addr" => $ip['name'],
                "mac" => $mac,
                // be careful, this options generate a new record if is changed, by default is true
                "configure_for_dhcp" => $configure_for_dhcp
            );
        }

        return $toReturn;
    }

    /* private static function arrayReplaceByField($arrayBase, $arrayForReplace, $fieldToCompare) {
      $replace = false;

      foreach ($arrayBase as $indexBase => $valueBase) {
      foreach ($arrayForReplace as $indexForReplace => $valueForReplace) {

      if (is_array($valueForReplace)) {
      $valueBase = self::arrayReplaceByField($valueBase, $valueForReplace, $fieldToCompare);
      }

      if ($valueBase[$fieldToCompare] == $valueForReplace[$fieldToCompare]) {
      $replace = true;
      }

      if ($replace === true) {
      $arrayBase[$indexBase] = $arrayForReplace[$indexForReplace];
      return $arrayBase;
      }
      }
      }

      return $arrayBase;
      } */

    /**
     * Add or update a single host to infoblox.
     * @param InfobloxWapiQuery $infoblox
     * @param array $hostData
     * @param type $configure_for_dhcp
     */
    private static function addInfobloxHost(InfobloxWapiQuery $infoblox, array $hostData, $configure_for_dns = true, $configure_for_dhcp = true
    ) {
        // search for host to update
        $result = $infoblox->query(
            "record:host", array(
            "name" => strtolower($hostData['name'])
            )
        );

        // set the method to add
        $method = "POST";

        // set the object to add or update
        $hostRecord = "record:host";
        if ($result) {
            // set the method to update
            $method = "PUT";
            // and set the host reference to update
            $hostRecord = $result[0]['_ref'];
        }

        // the fields to add or update
        $fields = array(
            "name" => strtolower($hostData['name']),
            "aliases" => $hostData['aliases'],
            "configure_for_dns" => $configure_for_dns,
            "device_type" => trim($hostData['device_type']),
            "device_vendor" => trim($hostData['device_vendor']),
            "device_location" => trim($hostData['device_location']),
            "device_description" => trim($hostData['device_comment'])
        );

        // the arrays of ip's for add
        if (isset($hostData['ipv4addrs'])) {
            $fields['ipv4addrs'] = $hostData['ipv4addrs'];
        }
        if (isset($hostData['ipv6addrs'])) {
            $fields['ipv6addrs'] = $hostData['ipv6addrs'];
        }

        // add or update host
        $fields = self::trimArray($fields);
        return $infoblox->query(
                $hostRecord, $fields, null, $method
        );
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
    private static function IsThereAnythingToDo(array $server) {
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
            $logPrefixError = $logPrefix . "Error: ";

            $task->addVolume(1);

            // if import options are not activated
            if (!self::IsThereAnythingToDo($server)) {
                $task->log($logPrefix . __('Nothing to do', 'infoblox'));
                continue;
            }

            $infoblox = new InfobloxWapiQuery(
                $server['address'], $server['user'], $server['password'], $server['wapi_version']
            );

            $configure_for_dhcp = ($server['dhcp'] == '1') ?
                true :
                false;

            $configure_for_dns = ($server['dns'] == '1' and $server['is_ad_dns_zone'] == '0') ?
                true :
                false;

            // import hosts
            if ($server['ipam'] == '1') {
                $result = self::addAllHostToInfobloxHosts(
                    $task, 
                    $infoblox, 
                    $server['affected_entities'], 
                    PluginInfobloxServer::getStateIds($server['id']), 
                    $server['fqdns_id'],
                    $configure_for_dns, 
                    $configure_for_dhcp, 
                    $logPrefixError
                );

                if (!$result) {
                    $toReturn = false;
                }
            }

            // import devices
            if ($server['devices'] == '1') {
                
            }

            // import dns
            if ($server['dns'] == '1') {
                
            }

            // import dhcp
            if ($server['dhcp'] == '1') {

                /* $result = $infoblox->query("network");

                  if ($result === false) {
                  $task->log($logPrefixError . $infoblox->getError());
                  }

                  foreach ($result as $network) {
                  $network['_ref'];    // referencia al objeto
                  $network['comment']; // nombre de la red
                  $network['network']; // red en formato 0.0.0.0/00
                  // get next available IP
                  // Example to get 5 free IPs:
                  $result = $infoblox->query($network['_ref'], array("num" => 5), "next_available_ip", "POST");

                  //$result = $infoblox->query($network['_ref'], array(), "next_available_ip", "POST");
                  if ($result === false) {
                  $task->log($logPrefixError . $infoblox->getError());
                  }
                  } */

                /* if (is_array($result) and empty($result)) {
                  $task->log($logPrefix . __('No DHCP results returned by server', 'infoblox'));

                  } elseif (!$result) {
                  $task->log($logPrefixError . $infoblox->getError());
                  } */
            }
        }

        return $toReturn;
    }

}
