<?php

/*
 * Copyright (C) 2016 Javier Samaniego García <jsamaniegog@gmail.com>
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

/**
 * Hook called on profile change
 * Good place to evaluate the user right on this plugin
 * And to save it in the session
 */
function plugin_change_profile_infoblox() {
    
}

/**
 * Fonction d'installation du plugin
 * @return boolean
 */
function plugin_infoblox_install() {
    global $DB;

    if (!TableExists("glpi_plugin_infoblox_servers")) {
        $DB->runFile(GLPI_ROOT . "/plugins/infoblox/sql/0.1.0.sql");
    }

    // register a cron for task execution
    CronTask::Register(
        "PluginInfobloxDNS", "InfobloxDNS", $time_in_seconds, array(
        'comment' => __('Infoblox DNS syncronization.', 'infoblox'),
        'mode' => CronTask::MODE_EXTERNAL
        )
    );

    return true;
}

/**
 * Fonction de désinstallation du plugin
 * @return boolean
 */
function plugin_infoblox_uninstall() {
    global $DB;

    $DB->runFile(GLPI_ROOT . "/plugins/infoblox/sql/uninstall-0.1.0.sql");

    return true;
}

?>
