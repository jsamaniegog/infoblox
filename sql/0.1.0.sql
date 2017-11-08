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

CREATE TABLE `glpi_plugin_infoblox_servers` (
        `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
        `name` varchar(32) NOT NULL default '' UNIQUE,
        `entities_id` int(11) NOT NULL default 0, 
        `is_recursive` tinyint(1) NOT NULL default 0,
        `address` varchar(32) NOT NULL default '',
        `user` varchar(32) NOT NULL default 'admin',
        `password` varchar(32) NOT NULL default '',
        `wapi_version` char(5) NOT NULL default '2.6.1',
        `state_ids` varchar(32) NOT NULL default 0 COMMENT 'Coma separated IDs',
        `ipam` tinyint(1) NOT NULL default 0,
        `devices` tinyint(1) NOT NULL default 0,
        `dhcp` tinyint(1) NOT NULL default 0,
        `dns` tinyint(1) NOT NULL default 0,
        `create_ptr` tinyint(1) NOT NULL default 0,
        `fqdns_id` int(11) NOT NULL default 0,
        `is_ad_dns_zone` tinyint(1) NOT NULL default 0,
        `is_ad_dhcp` tinyint(1) NOT NULL default 0,
        `computers` tinyint(1) NOT NULL default 0,
        `printers` tinyint(1) NOT NULL default 0,
        `peripherals` tinyint(1) NOT NULL default 0,
        `networkequipments` tinyint(1) NOT NULL default 0,
        `phones` tinyint(1) NOT NULL default 0,
        `tracking_objects_changes` tynyint(1) NOT NULL default 0,
        `last_sequence_id` varchar(100) default NULL,
        `host_number_to_sync` int(11) NOT NULL default 0 COMMENT '0: sync all',
)ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `glpi_plugin_infoblox_syncs` (
        `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
        `items_id` int(11) NOT,
        `itemtype` varchar(100) NOT NULL,        
        `synchronized` tinyint(1) NOT NULL default 0 COMMENT '0: no sync and no added to infoblox, 1: sync',
        `datetime` datetime NOT NULL,
        `error` varchar(300) default NULL
)ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;