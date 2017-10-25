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
global $DB, $CFG_GLPI;

include ("../../../inc/includes.php");

if (!Session::haveRight("config", UPDATE)) {
    Session::addMessageAfterRedirect(__("No permission", "infoblox"), false, ERROR);
    HTML::back();
}

$post_values = filter_input_array(INPUT_POST);
$get_values = filter_input_array(INPUT_GET);

$server = new PluginInfobloxServer();

if (isset($post_values['update']) or isset($post_values['add']) or isset($post_values['purge'])) {
    try {
        $config = new PluginInfobloxConfig();

        if (isset($post_values['state_ids'])) {
            $post_values['state_ids'] = implode(",", $post_values['state_ids']);
        } else {
            $post_values['state_ids'] = "";
        }
        
        if (isset($post_values['update'])) {
            $server->update($post_values);
        }
        if (isset($post_values['add'])) {
            $server->add($post_values);
        }
        if (isset($post_values['purge'])) {
            $server->delete($post_values);
            Html::redirect(PluginInfobloxServer::getFormURL());
        }
    } catch (Exception $e) {
        Session::addMessageAfterRedirect(__("Error on save", "infoblox"), false, ERROR);
        HTML::back();
    }

    HTML::back();
}

Html::header(
    PluginInfobloxServer::getTypeName(Session::getPluralNumber()), $_SERVER['PHP_SELF'], "config", "PluginInfobloxServer"
);

$id = (isset($get_values["id"])) ? $get_values["id"] : null;
$server->display(array('id' => $id));

Html::footer();
