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
 * Init the hooks of the plugins -Needed
 * @global array $PLUGIN_HOOKS
 * @glpbal array $CFG_GLPI
 */
function plugin_init_infoblox() {
    global $PLUGIN_HOOKS, $CFG_GLPI;

    Plugin::registerClass('PluginInfobloxConfig', array('addtabon' => 'Config'));
    Plugin::registerClass('PluginInfobloxServer');

    // Config page (muestra el acceso en el menu superior, en la parte de configuración)
    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['infoblox'] = 'front/config.php';
        $PLUGIN_HOOKS['menu_toadd']['infoblox'] = array(
            'config' => 'PluginInfobloxConfig',
            'config' => 'PluginInfobloxServer'
        );
    }
    
    $PLUGIN_HOOKS['csrf_compliant']['infoblox'] = true;
}

/**
 * Fonction de définition de la version du plugin
 * @return type
 */
function plugin_version_infoblox() {
    return array('name' => __('Infoblox', 'infoblox'),
        'version' => '0.1.0',
        'author' => 'Javier Samaniego García',
        'license' => 'AGPLv3+',
        'homepage' => 'https://github.com/jsamaniegog/infoblox',
        'minGlpiVersion' => '9.1');
}

/**
 * Fonction de vérification des prérequis
 * @return boolean
 */
function plugin_infoblox_check_prerequisites() {
    if (PHP_OS !== 'Linux') {
        echo __('This plugin requires Linux OS.', 'infoblox');
        return false;
    }
    
    if (version_compare(GLPI_VERSION, '9.1', 'lt')) {
        echo __('This plugin requires GLPI >= 9.1', 'infoblox');
        return false;
    }

    return true;
}

/**
 * Fonction de vérification de la configuration initiale
 * Uninstall process for plugin : need to return true if succeeded
 * may display messages or add to message after redirect.
 * @param type $verbose
 * @return boolean
 */
function plugin_infoblox_check_config($verbose = false) {
    // check here
    if (true) {
        return true;
    }

    if ($verbose) {
        echo __('Installed / not configured', 'infoblox');
    }

    return false;
}?>
