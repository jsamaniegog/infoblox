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

$server = new PluginInfobloxServer();

if (isset($_POST['update']) or isset($_POST['add']) or isset($_POST['purge'])) {
    try {
        $config = new PluginInfobloxConfig();

        if (isset($_POST['update'])) {
            $server->update($_POST);
        }
        if (isset($_POST['add'])) {
            $server->add($_POST);
        }
        if (isset($_POST['purge'])) {
            $server->delete($_POST);
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

$server->display(array('id' => $_GET["id"]));

Html::footer();
