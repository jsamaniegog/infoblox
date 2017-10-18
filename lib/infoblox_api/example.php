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

// Arguments. Fill this variables to test it.
$address  = "infoblox.midominio.es";
$user     = "admin";
$password = "micontraseña";

// Create an infoblox object
$infoblox = new Infoblox($address, $user, $password);

// Search for a DNS host entry by name
$infoblox->search("Infoblox::DNS::Record::A", "name", "^midominio.*\.es$");

// Get array of results
if (!$outputs = $infoblox->getResults()) {
    // if there is an error...
    $errors = $infoblox->getErrors();
    
    foreach ($errors as $error) {
        echo $error;
    }
}

print_r($outputs);