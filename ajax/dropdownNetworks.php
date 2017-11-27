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

$AJAX_INCLUDE = 1;
include ('../../../inc/includes.php');

require_once GLPI_ROOT . '/plugins/infoblox/lib/InfobloxWapiQuery.php';

// Send UTF8 Headers
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

$post = filter_input_array(INPUT_POST);

if ($post['plugin_infoblox_server_id'] == 0) {
    echo '';
    die();
}

$datas = array();

$server = new PluginInfobloxServer();
$server->getFromDB($post['plugin_infoblox_server_id']);

$server->showNetworksDropdown();