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

/**
 * Class for use of Infoblox API (Perl API). 
 * See: https://your.infoblox.server/api/doc/.
 *
 * @author Javier Samaniego García <jsamaniegog@gmail.com>
 * @version GIT: $Id$ Alpha but functional.
 * @example ./example.php 
 */
class Infoblox {
    
    /**
     * Stores the perl code to execute.
     * @var string 
     */
    private $perlCode = "";
    
    /**
     * Stores the execution output.
     * @var array 
     */
    private $outputs = null;
    
    /**
     * Stores the execution errors.
     * @var array 
     */
    private $errors = null;
    
    /**
     * Check if infoblox API is installed on the system.
     * @return bool
     */
    static public function testAPIInstallation() {
        return file_exists("/usr/local/share/perl5/Infoblox.pm");
    }
    
    /**
     * Infoblox constructor. It starts session.
     * @param type $address FQDN or IP address of the infoblox server
     * @param type $user User.
     * @param type $password Password.
     */
    public function __construct($address, $user, $password) {
        $this->address = $address;
        $this->user = $user;
        $this->password = $password;
        
        $this->initSession();
    }
    
    /**
     * Append perl code.
     */
    private function appendCode($action = "initSession", $args = array()) {
        // get script code
        $this->perlCode .= file_get_contents("$action.pl", FILE_USE_INCLUDE_PATH);
        
        // arguments
        foreach ($args as $i => $v) {
            $this->setArgument($i, $v);
        }
    }
    
    /**
     * Sets an argument over the code appened.
     * @param type $argument
     * @param type $value
     */
    private function setArgument($argument, $value) {
        $this->perlCode = str_replace("ARG_$argument", $value, $this->perlCode);
    }
    
    /**
     * Sets the code for init session on infoblox server.
     */
    private function initSession() {
        $this->appendCode(
            __FUNCTION__, 
            array(
                'address' => $this->address,
                'user' => $this->user,
                'password' => $this->password
            )
        );
    }
    
    /**
     * Use this method to search and retrieve all the matching objects from the Infoblox appliance. 
     * View API for more documentation about the variables. https://your.infoblox.server/api/doc/.
     * @param string $object Required. The Infoblox object to retrieve.
     * @param string $field The name of the field of the object.
     * @param string $pattern Pattern.
     * @param string $method Method to retrive data.
     */
    public function search($object = "Infoblox::DNS::Record::A", $field = "name", 
        $pattern = "^host\.domain\.com$", $method = "name"
    ) {
        
        $this->appendCode(
            __FUNCTION__, 
            array(
                'object'  => $object,
                'field'   => $field,
                'pattern' => $pattern,
                'method'  => $method
            )
        );
        
        $this->runPerl();
        
        $this->parseResult();
    }
    
    private function parseResult() {
        $thereIsAnError = false;
        
        foreach ($this->outputs as $output) {
            if (preg_match("/^.*:[[:space:]][0-9]*:.*$/", $output)) {
                list($output) = explode(" at /tmp/perlCode.pl", $output);
                $this->errors[] = $output;
                $thereIsAnError = true;
            }
        }
        
        if ($thereIsAnError) {
            $this->outputs = false;
        }
    }
    
    private function runPerl() {
        // create file
        $handle = fopen("/tmp/perlCode.pl", "w+");
        fwrite($handle, $this->perlCode);
        fclose($handle);
        
        // execute file
        exec("perl /tmp/perlCode.pl 2>&1", $this->outputs);
        
        // delete file
        unlink("/tmp/perlCode.pl");
        
        // reset code for next execution
        $this->resetCode();
    }
    
    private function resetCode() {
        $this->initSession();
    }
    
    /**
     * Returns the output.
     * @return array|bool If there is an error the function returns null.
     */
    public function getResults() {
        return $this->outputs;
    }
    
    /**
     * Returns the output.
     * @return array|bool If there is an error the function returns null.
     */
    public function getErrors() {
        return $this->errors;
    }
}
