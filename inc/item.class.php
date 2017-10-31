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

/**
 * Infoblox for Network Equipments.
 * 
 * @author Javier Samaniego García <jsamaniegog@gmail.com>
 * @package PluginInfoblox
 */
class PluginInfobloxItem extends CommonDBTM {

    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

        $array_ret = array();
        if ($item->getID() > -1) {
            $count = null;
            if ($_SESSION['glpishow_count_on_tabs']) {
                $count = self::count("itemtype='" . get_class($item) . "' AND items_id=" . $item->getID());
            }
            $array_ret[0] = self::createTabEntry('Infoblox', $count);
        }
        return $array_ret;
    }

    /**
     * Display the content of the tab
     *
     * @param object $item
     * @param integer $tabnum number of the tab to display
     * @param integer $withtemplate 1 if is a template form
     * @return boolean
     */
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        
        if ($item->getID() > -1) {
            $pmEntity = new PluginInfobloxItem();
            $pmEntity->showForm($item);
        }
        
        return true;
    }

    /**
     * 
     * @global type $DB
     * @param type $condition
     * @return type
     */
    static function count($condition = "") {
        
        global $DB;
        
        $query = "SELECT count(*) as count FROM " . PluginInfobloxSync::getTable();

        if ($condition != "") {
            $query .= " WHERE " . $condition;
        }

        if ($result = $DB->query($query)) {
            return $result->fetch_assoc()['count'];
        } else {
            return 0;
        }
    }
    
    /**
     * Print the form.
     * @param CommonGLPI $item Object.
     * @param array $options Options array.
     */
    function showForm(CommonDBTM $item, array $options = array()) {
        echo '<table class="tab_cadre_fixe" width="100%">';
        echo '<tr>';
        echo '<th colspan=2>Infoblox</th>';
        echo '</tr>';
        echo '<tr class="tab_bg_1">';
        echo '<td style="text-align:right">';

        $sync = new PluginInfobloxSync();
        $sync->getFromDBByCrit(array(
            "items_id" => $item->getField("id"), 
            "itemtype" => get_class($item)
        ));
        
        echo __('Synchronized', 'infoblox') . ": ";
        echo "</td><td>";
        echo (isset($sync->fields) and $sync->getField('synchronized') == '1') ? 
            __('Yes') : 
            __('No');
        
        echo '</tr><tr><td style="text-align:right">';
        
        echo __('Date') . ": ";
        echo "</td><td>";
        echo (isset($sync->fields)) ? 
            $sync->getField('datetime') : 
            __('Not registered', 'infoblox') ;
        
        echo '</tr><tr><td style="text-align:right">';
        
        echo __('Error') . ": ";
        echo "</td><td><b style='color:red;'>";
        echo (isset($sync->fields) and $sync->getField('error') != "") ? 
            $sync->getField('error') : 
            __('No error', 'infoblox') ;
        
        echo '</b></td></tr></table>';
    }
}
