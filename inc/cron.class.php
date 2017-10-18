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
 *
 * @author Javier Samaniego García <jsamaniegog@gmail.com>
 */
class PluginInfobloxCron extends CommonDBTM {

    static function getTypeName($nb = 0) {
        return __('Infoblox Cron', 'infoblox');
    }

    /**
     * Executed by cron. This task search the name for each IP address and save 
     * it in database.
     */
    static function cronInfoblox(CronTask $task) {
        global $DB;
        
        $entities_with_server = array();
        
        // get infoblox servers and its entities
        $server = new PluginInfobloxServer();
        foreach ($server->find() as $server) {
            
            $entities_with_server[] = $server['entities_id'];
                
            if ($server['is_recursive'] == 1) {
                $sons = getSonsOf("glpi_entities", $server['entities_id']);
                foreach ($sons as $value) {
                    $entities_with_server[] = $value;
                }
            }
            
            $infoblox_servers[] = $server;
        }
        
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
            if ($server['devices'] == '0' 
                and $server['dhcp'] == '0' 
                and $server['dns'] == '0'
            ) {
                $task->log($logPrefix . __('Nothing to do', 'infoblox'));
                continue;
            }
            
            $infoblox = new InfobloxWapiQuery(
                $server['address'], 
                $server['user'], 
                $server['password'], 
                $server['wapi_version']
            );
            
            // import devices
            if ($server['devices'] == '1') {
                
            }
            
            // import dns
            if ($server['dns'] == '1') {
                
            }
            
            // import dhcp
            if ($server['dhcp'] == '1') {
                
                $result = $infoblox->query("record:a", array("name" => ".*hcuv"));
                
                if (is_array($result) and empty($result)) {
                    $task->log($logPrefixError . $infoblox->getError());
                    
                } elseif (!$result) {
                    $task->log($logPrefixError . $infoblox->getError());
                }
            }
        }
        
        return $toReturn;
    }

}
