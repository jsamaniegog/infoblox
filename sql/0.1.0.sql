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
        `devices` tinyint(1) NOT NULL default 0,
        `dhcp` tinyint(1) NOT NULL default 0,
        `dns` tinyint(1) NOT NULL default 0
)ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;