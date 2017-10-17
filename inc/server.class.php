<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of server
 *
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
        echo '&nbsp;';
        echo __('Example: 2.6.1 (Go to https://your.infoblox.server/wapidoc to know the version)', 'infoblox');
        
        echo "</td></tr><tr><td colspan='2'>";
        
        echo __('Import options', 'infoblox') . "</td><td colspan='2'>";
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
        
        echo "</td></tr>";

        $this->showFormButtons($options);

        return true;
    }
}
