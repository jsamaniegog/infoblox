<?php
/**
 * @package PluginInfoblox
 */
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

/**
 * Description of server
 * @package PluginInfoblox
 * @author Javier Samaniego García <jsamaniegog@gmail.com>
 */
class PluginInfobloxServer extends CommonDBTM {

    static $rightname = 'config';
    public $dohistory = false;

    //public $fields = array("address");

    static function getTypeName($nb = 0) {
        return _n('Infoblox Server', 'Infoblox Servers', $nb, 'infoblox');
    }

    /**
     * @return array
     */
    function getSearchOptions($nb = 2) {

        $tab = array();

        $tab['common'] = _n('Infoblox Server', 'Infoblox Servers', $nb, 'infoblox');

        $tab[1]['table'] = $this->getTable();
        $tab[1]['field'] = 'name';
        $tab[1]['name'] = __('Server name');
        $tab[1]['datatype'] = 'itemlink';
        $tab[1]['itemlink_type'] = $this->getType();

        $tab[2]['table'] = $this->getTable();
        $tab[2]['field'] = 'address';
        $tab[2]['name'] = __('FQDN or IP Address');

        return $tab;
    }

    /**
     * Return id of the selected states (status field of GLPI).
     * @global type $DB
     * @return boolean|array Return an array of id's with the states. If don't exist return false.
     */
    static public function getStateIds($id) {
        global $DB;

        $query = "SELECT state_ids FROM `glpi_plugin_infoblox_servers` WHERE id = '$id'";

        if ($result = $DB->query($query)) {
            $row = $result->fetch_assoc(); // cogemos el primero
            if ($row['state_ids'] == null) {
                return array();
            }
            return explode(",", $row['state_ids']);
        }

        return false;
    }
    
    /**
     * Show DNS server form to add or edit.
     * @param type $id
     * @return boolean
     */
    function showForm($id = null, $options = array()) {
        if (!Session::haveRight("config", UPDATE)) {
            return false;
        }

        // get server data
        $this->getFromDB($id);

        $this->showFormHeader($options);

        // HTML
        // fields
        echo "<tr><td colspan='2'>";

        // hidden id
        echo Html::hidden("id", array('value' => $this->fields['id']));

        echo __('Name') . "</td><td colspan='2'>";
        echo Html::input("name", array('value' => $this->fields['name']));

        echo "</td></tr><tr><td colspan='2'>";

        echo __('FQDN or IP Address', 'infoblox') . "</td><td colspan='2'>";
        echo Html::input("address", array('value' => $this->fields['address']));
        
        echo "</td></tr><tr><td colspan='2'>";
        
        echo __('User', 'infoblox') . "</td><td colspan='2'>";
        echo Html::input("user", array('value' => $this->fields['user']));
        
        echo "</td></tr><tr><td colspan='2'>";
        
        echo __('Password', 'infoblox') . "</td><td colspan='2'>";
        echo Html::input("password", array('value' => $this->fields['password']));

        echo "</td></tr><tr><td colspan='2'>";
        
        echo __('WAPI version', 'infoblox') . "</td><td colspan='2'>";
        echo Html::input("wapi_version", array('value' => $this->fields['wapi_version']));
        echo "<b style='color: LightGrey;'>&nbsp;" . __('Example: 2.6.1 (Go to https://your.infoblox.server/wapidoc to know the version)', 'infoblox') . "</b>";
        
        echo "</td></tr><td colspan='4'><hr width='100%'>";
        echo "</td></tr><tr><td colspan='2'>";
        
        // states
        echo "<tr class='tab_bg_2'>";
        echo "<td>" . __('Select the different states to search (empty value "', 'infoblox') . Dropdown::EMPTY_VALUE . __('" is alwais searched)', 'infoblox') . "</td>";
        echo "<td colspan='3'>";
        $state = new State();
        $states = $state->find();
        foreach ($states as $key => $state) {
            $states[$key] = $state['name'];
        }
        Dropdown::showFromArray(
            'state_ids', $states, array(
            'values' => $this->getStateIds($id),
            'multiple' => true
            )
        );
        echo "</td></tr><tr><td>";
        
        // Zone options
        echo __(' Zone Name / DNS Sufix', 'infoblox') . "</td><td colspan='2'>";
        $fqdns = new FQDN();
        $fqdns->dropdown(array('name' => 'fqdns_id', 'value' => $this->fields['fqdns_id']));
        echo "<b style='color: LightGrey;'>&nbsp;" . __('If you select', 'infoblox') . ' "' . Dropdown::EMPTY_VALUE . '" ' . __('option, all will be synchronized.', 'infoblox') . "</b>";
        
        echo "</td></tr><tr><td colspan='2'>";
        
        // AD options
        echo __('Active Directory options', 'infoblox') . "</td><td colspan='2'>";
        $rand = Dropdown::showYesNo('is_ad_dns_zone', $this->fields['is_ad_dns_zone'], -1, array('use_checkbox'=>true));
        echo "<label ";
        echo "for='dropdown_is_ad_dns_zone$rand' title='If the zone is an A.D. zone and you do not check this box you will get an error by the server'>";
        echo __(' It is an Active Directory zone', 'infoblox') . '<br><br>';
        echo "</label>";
        
        echo "</td></tr><td colspan='4'><hr width='100%'>";
        echo "</td></tr><tr><td colspan='2'>";
        
        // import and synchronizations options
        echo __('Import and synchronization options', 'infoblox') . "</td><td colspan='2'>";
        $rand = Dropdown::showYesNo('ipam', $this->fields['ipam'], -1, array('use_checkbox'=>true));
        echo "<label ";
        echo "for='dropdown_ipam$rand'>";
        echo ' IPAM<br><br>';
        echo "</label>";
        $rand = Dropdown::showYesNo('devices', $this->fields['devices'], -1, array('use_checkbox'=>true));
        echo "<label ";
        echo "for='dropdown_devices$rand'>";
        echo __(' Devices<br><br>', 'infoblox');
        echo "</label>";
        $rand = Dropdown::showYesNo('dhcp', $this->fields['dhcp'], -1, array('use_checkbox'=>true));
        echo "<label ";
        echo "for='dropdown_dhcp$rand'>";
        echo ' DHCP<br><br>';
        echo "</label>";
        $rand = Dropdown::showYesNo('dns', $this->fields['dns'], -1, array('use_checkbox'=>true));
        echo "<label ";
        echo "for='dropdown_dns$rand'>";
        echo ' DNS';
        echo "</label>";
        
        echo "</td></tr><td colspan='4'><hr width='100%'>";
        echo "</td></tr><td colspan='2'>";
        
        // asset options
        echo __('Asset synchronization options', 'infoblox') . "</td><td colspan='2'>";
        $rand = Dropdown::showYesNo('computers', $this->fields['computers'], -1, array('use_checkbox'=>true));
        echo "<label ";
        echo "for='dropdown_computers$rand'>";
        echo ' ' . _n('Computer', 'Computers', 2) . '<br><br>';
        echo "</label>";
        $rand = Dropdown::showYesNo('printers', $this->fields['printers'], -1, array('use_checkbox'=>true));
        echo "<label ";
        echo "for='dropdown_printers$rand'>";
        echo __(' ' . _n('Printer', 'Printers', 2) . '<br><br>', 'infoblox');
        echo "</label>";
        $rand = Dropdown::showYesNo('peripherals', $this->fields['peripherals'], -1, array('use_checkbox'=>true));
        echo "<label ";
        echo "for='dropdown_peripherals$rand'>";
        echo ' ' . _n('Device', 'Devicess', 2) . '<br><br>';
        echo "</label>";
        $rand = Dropdown::showYesNo('phones', $this->fields['phones'], -1, array('use_checkbox'=>true));
        echo "<label ";
        echo "for='dropdown_phones$rand'>";
        echo ' ' . _n('Phone', 'Phones', 2) . '<br><br>';
        echo "</label>";
        $rand = Dropdown::showYesNo('networkequipments', $this->fields['networkequipments'], -1, array('use_checkbox'=>true));
        echo "<label ";
        echo "for='dropdown_networkequipments$rand'>";
        echo ' ' . _n('Network device', 'Network devices', 2) . '';
        echo "</label>";
        
        echo "</td></tr>";

        $this->showFormButtons($options);

        return true;
    }
}
