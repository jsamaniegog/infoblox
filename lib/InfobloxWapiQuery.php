<?php

/**
 * @package InfobloxWapiQuery
 * @version 0.1.0
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
 * 
 */

/**
 * This class if to query the REST API of Infoblox named WAPI.
 *
 * @author Javier Samaniego García <jsamaniegog@gmail.com>
 * @package InfobloxWapiQuery
 */
class InfobloxWapiQuery {

    /**
     * Stores a cURL handle returned by curl_init() function or false if it fails.
     * @var resource|bool cURL handle or false.
     */
    private $curl;
    
    /**
     * WAPI versión. To know the version go to: https://your.infoblox.server/wapidoc.
     * @var string WAPI version.
     */
    private $version;

    /**
     * The username.
     * @var string Username.
     */
    private $user;

    /**
     * The password for the username.
     * @var string Password.
     */
    private $password;

    /**
     * FQDN or IP Address to conect with infoblox.
     * @var string FQDN or IP Address.
     */
    private $address;

    /**
     * URL to query.
     * @var string WAPI URL.
     */
    private $url = "";

    /**
     * Can be: GET, POST, PUT or DELETE. See: https://your.infoblox.server/wapidoc.
     * @var type 
     */
    private $method = "GET";

    /**
     * Object we request. See: https://your.infoblox.server/wapidoc.
     * @var string WAPI Object.
     */
    private $object;

    /**
     * Fields and values of the object that we request.
     * @var array Fields and values of the object.
     */
    private $fields = array();

    /**
     * Stores result of the query.
     * @var json 
     */
    private $result;

    /**
     * Stores the last error ocurred.
     * @var string 
     */
    private $error;

    /**
     * Stores the last http code returned by the web service. Useful for detect
     * an error.
     * @var string 
     */
    private $httpCode;

    /**
     * Option for enable paging for GET method. If it's false the maximum number 
     * of results is 1000, in case that this limit will be exceeded you'll get 
     * an error.
     * @var bool true means enabled and false disabled
     */
    private $_paging = false;

    /**
     * Option for enable paging for GET method. If set to 1, a results object 
     * will be returned. This option must be enabled if $_paging is enabled.
     * @var bool true means enabled and false disabled
     */
    //private $_return_as_object = true;

    /**
     * Option for GET method. It indicates the maximum number of results to be 
     * returned. Required if $_paging is enabled.
     * @var int 
     */
    private $_max_results = 5000;

    /**
     * Stores the function to call. 
     * @var string 
     */
    private $_function;

    /**
     * Create an InfobloxWapiQuery object.
     * @param string $address FQDN or IP Address of the Infoblox server.
     * @param string $user Username.
     * @param string $password Password.
     * @param string $version Description
     * @return InfobloxWapiQuery Object InfobloxWapiQuery.
     */
    public function __construct($address, $user, $password, $version) {
        $this->setAddress($address);
        $this->setUser($user);
        $this->setPassword($password);
        $this->setVersion($version);
        $this->setURL("https://$address/wapi/v$version/");
    }

    /**
     * Query to web service.
     * @param string $object Object we request. See: https://your.infoblox.server/wapidoc.
     * @param array $fields Array of fields and values.
     * @param string $method Can be: GET, POST, PUT or DELETE. See: 
     * https://your.infoblox.server/wapidoc.
     * @return array Array result set.
     */
    public function query($object, $fields = array(), $function = null, $method = "GET") {
        // sets previous send query
        $this->setObject($object);
        $this->setFields($fields);
        $this->setFuction($function);
        $this->setMethod($method);

        // send query to infoblox server
        $this->sendQueryByCurl();

        // return the results
        return $this->getResult();
    }

    /**
     * Set the object to request. Example: "record:a", "record:ptr", "network"...
     * @param string $object
     */
    private function setObject($object) {
        $this->object = $object;
    }

    /**
     * Get the object to request.
     */
    private function getObject() {
        return $this->object;
    }

    /**
     * Set the URL.
     * @param string $url
     */
    private function setURL($url) {
        $this->url = $url;
    }

    /**
     * Set the version.
     * @param string $version
     */
    private function setVersion($version) {
        $this->version = $version;
    }

    /**
     * Get the URL.
     */
    private function getURL() {
        return $this->url;
    }

    /**
     * Set the address.
     * @param string $address
     */
    private function setAddress($address) {
        $this->address = $address;
    }

    /**
     * Set the user.
     * @param string $user
     */
    private function setUser($user) {
        $this->user = $user;
    }

    /**
     * Get the user.
     */
    private function getUser() {
        return $this->user;
    }

    /**
     * Set the password.
     * @param string $password
     */
    private function setPassword($password) {
        $this->password = $password;
    }

    /**
     * Get the password.
     * @return string Password.
     */
    private function getPassword() {
        return $this->password;
    }

    /**
     * Set the fields of the object.
     * @param array $fields Array of fields and values.
     */
    private function setFields($fields) {
        $this->fields = $fields;
    }

    /**
     * Get _paging variable.
     * @return bool
     */
    private function getPaging() {
        return $this->_paging;
    }

    /**
     * Set _max_results variable. This is to set the maximum results per page.
     * @param int $int Number of máximum results per page.
     */
    public function setMaxResults($int) {
        $this->_max_results = $int;
    }

    /**
     * Get _function variable. This is to set the function to call.
     * @return string The function to call.
     */
    private function getFuction() {
        return $this->_function;
    }

    /**
     * Set _function variable. This is to set the function to call.
     * @param string $function Name of the function. Example: next_available_ip.
     */
    private function setFuction($function) {
        $this->_function = $function;
    }

    /**
     * Get _max_results variable.
     * @return int
     */
    private function getMaxResults() {
        return $this->_max_results;
    }

    /**
     * Return the part of parameters of URL.
     * @return string
     */
    private function getParamsUrlFormat() {
        $urlParams = "";

        if ($this->getMethod() == 'GET') {
            $urlParams .= ($this->getPaging()) ? 
                "?_paging=1" : 
                "";
            $urlParams .= ($this->getPaging()) ? 
                "&_return_as_object=1" : 
                "";
            $urlParams .= ($this->getPaging()) ? 
                "&_max_results=" . $this->getMaxResults() : 
                "";
        }

        if ($this->getMethod() == 'POST') {
            $function = $this->getFuction();
            $urlParams .= (isset($function) and $function != "") ?
                "?_function=" . $this->getFuction() :
                "";
        }

        return $urlParams;
    }

    /**
     * Sets curl options.
     * @param resource $curl cURL resource.
     * @param json $jsonData JSON string to query.
     * @param string $urlParams String with URL params.
     */
    private function setCurlOpts(&$curl, $jsonData, $urlParams) {
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt(
            $curl, CURLOPT_URL, $this->getURL() . $this->getObject() . $urlParams
        );
        curl_setopt($curl, CURLOPT_COOKIESESSION, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->getMethod());
        curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt(
            $curl, CURLOPT_USERPWD, $this->getUser() . ":" . $this->getPassword()
        );
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
            )
        );
    }

    /**
     * Send query by curl.
     */
    private function sendQueryByCurl() {
        $this->curl = curl_init();

        // JSON data request
        $jsonData = $this->getJsonData();

        // some params
        $urlParams = $this->getParamsUrlFormat();

        $this->setCurlOpts($this->curl, $jsonData, $urlParams);
        $this->setResult(curl_exec($this->curl));
        $this->setHttpCode(curl_getinfo($this->curl, CURLINFO_HTTP_CODE));

        curl_close($this->curl);
    }

    /**
     * Return JSON string to query.
     */
    private function getJsonData() {
        if (empty($this->fields)) {
            return json_encode($this->fields, JSON_FORCE_OBJECT);
        } else {
            return json_encode($this->fields);
        }
        
        // Example of returned string:
        // $jsonData = '{"name":"foo.antox.intra","ipv4addr":"192.168.1.2"}'; 
        //$obj = json_decode($jsonData);
        //echo $obj->{'name'};
        //it works, it print "foo.antox.intra"
    }

    /**
     * Sets the result of the query.
     * @param type $result
     */
    private function setResult($result) {
        // possible http code errors
        if (intval($this->getHttpCode()) > 299) {
            $this->setError("HTTP CODE: " . $this->getHttpCode());
            $result_decoded = false;
        }

        $result_decoded = json_decode($result, true);

        // bad json query or authentication fail
        if ($result_decoded === null and is_string($result)) {
            $this->setError($result);
            $result_decoded = false;
        }

        // curl error
        if ($result === null or $result === false) {
            $this->setError(curl_error($this->curl));
        }
        
        // error on the query
        if (isset($result_decoded['Error'])) {
            $this->setError($result_decoded['text']);
            $result_decoded = false;
        }

        $this->result = $result_decoded;
    }

    /**
     * Return the result of the query.
     * @return type
     */
    private function getResult() {
        return $this->result;
    }

    /**
     * Set the last error ocurred.
     * @param type $error
     */
    private function setError($error) {
        $this->error = $error;
    }

    /**
     * Returns the last error.
     * @return string Returns the last error.
     */
    public function getError() {
        return $this->error;
    }

    /**
     * Set the http code returned by the server.
     * @param type $httpCode
     */
    private function setHttpCode($httpCode) {
        $this->httpCode = $httpCode;
    }

    /**
     * Get the http code returned by the server.
     * @return string
     */
    private function getHttpCode() {
        return $this->httpCode;
    }

    /**
     * Set the method.
     * @param type $method
     */
    private function setMethod($method) {
        if (!in_array($method, array("GET", "POST", "PUT", "DELETE"))) {
            throw new Exception("[" . __CLASS__ . "." . __METHOD__ . "]: "
            . "Argument error, only accept: GET, POST, PUT or DELETE.");
        }

        $this->method = $method;
    }

    /**
     * Returns the method.
     * @return string
     */
    private function getMethod() {
        return $this->method;
    }

}
