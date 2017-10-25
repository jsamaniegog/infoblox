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

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Configuration class.
 * @package PluginInfoblox
 * @author Javier Samaniego García <jsamaniegog@gmail.com>
 */
class PluginInfobloxConfig extends CommonDBTM {

    /**
     * For debug
     * @var bool Debug switcher.
     */
    const DEBUG_INFOBLOX = false;

    static function getTypeName($nb = 0) {
        return __("Infoblox", "infoblox");
    }

    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        if (!$withtemplate) {
            if ($item->getType() == 'Config') {
                return __('Infoblox plugin', 'infoblox');
            }
        }
        return '';
    }

    function showForm() {
        global $CFG_GLPI;
        if (!Session::haveRight("config", UPDATE)) {
            return false;
        }

        echo "<form name='form' action=\"" . Toolbox::getItemTypeFormURL('PluginInfobloxConfig') . "\" method='post'>";
        echo "<div class='center' id='tabsbody'>";
        echo "<table class='tab_cadre_fixe'>";
        
        // page title
        echo "<tr><th colspan='4'>" . __('Infoblox Setup', 'infoblox') . "</th></tr>";
        
        echo "<tr class='tab_bg_2'><td>";
        
        echo Html::link(__("Servers", "infoblox"), PluginInfobloxServer::getSearchURL(true));
        
        echo "</td></tr>";
        echo "</table></div>";
        
        Html::closeForm();
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item->getType() == 'Config') {
            $config = new self();
            $config->showForm();
        }
    }
}