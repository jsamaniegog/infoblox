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
 * Manage the part of nebackup in network equipments.
 * 
 * @author Javier Samaniego García <jsamaniegog@gmail.com>
 */
class PluginInfobloxNetworkName extends CommonDBTM {

    /**
     * Rights.
     * @var string 
     */
    static $rightname = 'networking';

    /**
     * Get name of this type
     *
     * @return text name of this type by language of the user connected
     *
     * */
    static function getTypeName($nb = 0) {
        return __('Infoblox', 'infoblox');
    }

    /**
     * Get Tab Name used for itemtype.
     *
     * NB : Only called for existing object
     *      Must check right on what will be displayed + template
     *
     * @since version 0.83
     * @param CommonGLPI $item CommonDBTM object for which the tab need to be displayed
     * @param bool $withtemplate If is a template object or not (default 0)
     * @return string tab name
     */
    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        $array_ret = array();

        if ($item->getID() > -1) {
            if (Session::haveRight("networking", READ)) {
                $array_ret[0] = self::createTabEntry(self::getTypeName());
            }
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
            $subitem = new self();
            $subitem->showForm($item);
        }

        return true;
    }

    /**
     * Display form for service configuration
     *
     * @param CommonDBTM $item CommonDBTM object.
     * @param array $options Array of options.
     *
     * @return bool true if form is ok
     */
    function showForm(CommonDBTM $item, $options = array()) {

        global $CFG_GLPI;
        
        if (GLPI_USE_CSRF_CHECK) {
            $_glpi_csrf_token = Session::getNewCSRFToken();
        }

        echo "<form 
            name='plugin_infoblox_form' id='plugin_infoblox_form' method='post'
            action='" . PluginInfobloxNetworkName::getFormURL() . "'>";

        echo '<table class="tab_cadre_fixe" width="100%">';
        echo '<tr>';
        echo '<th>' . __('Infoblox', 'infoblox') . '</th>';
        echo '</tr>';

        echo '<tr class="tab_bg_1"><td>';
        echo __('Infoblox server: ', 'infoblox');

        $server = new PluginInfobloxServer();
        $server->getFromDBByQuery("ORDER BY id LIMIT 1");
        $rand = $server->dropdown(array(
            'display_emptychoice' => false,
            'value' => $server->getID()
        ));
        
        Ajax::updateItemOnSelectEvent(
            "dropdown_plugin_infoblox_servers_id$rand",
            "ipnetwork_ref",
            $CFG_GLPI["root_doc"] . '/plugins/infoblox/ajax/dropdownNetworks.php',
            array('plugin_infoblox_server_id' => '__VALUE__')
        );
        
        echo '<tr class="tab_bg_1"><td>';
        echo __('IP Network: ', 'infoblox');
        
        echo "<span id='ipnetwork_ref'>";
        $server->showNetworksDropdown();
        echo "</span>\n";
        
        
        echo Html::hidden("_glpi_csrf_token", array('value' => Session::getNewCSRFToken()));
        echo Html::hidden("networknameid", array('value' => $item->getID()));

        echo '</td></tr><tr class="tab_bg_1"><td>';

        echo Html::submit(
            __('Get next available ip', 'infoblox'), array(
            'name' => 'next_available_ip',
            'title' => __('This button assign a free ip address (in Infoblox) to this network name.', 'infoblox')
            )
        );

        echo '</td></tr></table>';

        echo '</form>';
    }

    /**
     * Search for next available IP address in the network passed and assign to
     * the networkname indicated.
     * @param int $ipNetworkId IP Network ID in which to search.
     * @param int $networkNameId Network name ID to assing the IP address.
     * @return string|bool Return the next available IP address. Return false if 
     * network is not defined in infoblox or the conection fails.
     */
    public function getNextAvailableIp($serverInfobloxId, $ipNetwork_ref) {
        $server = new PluginInfobloxServer();
        $server->getFromDB($serverInfobloxId);
        
        $infoblox = new InfobloxWapiQuery(
            $server->getField("address"),
            $server->getField("user"),
            $server->getField("password"),
            $server->getField("wapi_version")
            );
        
        $result = $infoblox->query(
            $ipNetwork_ref, array(), "next_available_ip", "POST"
        );
        
        if (!$result) {
            return false;
        }
        
        $ip = $result['ips'][0];
        
        return $ip;
    }

    /**
     * Convert mask to cidr.
     * @param type $mask
     * @return type
     */
    static private function mask2cidr($mask) {
        $long = ip2long($mask);
        $base = ip2long('255.255.255.255');
        return 32 - log(($long ^ $base) + 1, 2);

        /* xor-ing will give you the inverse mask,
          log base 2 of that +1 will return the number
          of bits that are off in the mask and subtracting
          from 32 gets you the cidr notation */
    }

    /**
     * Set an IP address.
     * @param string $ip IP Address.
     */
    public function setIp($ip, $networkNameId) {
        $nn = new NetworkName();
        $input['id'] = $networkNameId;
        $input['_ipaddresses'] = array(-1 => $ip);
        $nn->update($input);
    }
}
