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
 * Sync class.
 * @package PluginInfoblox
 * @author Javier Samaniego García <jsamaniegog@gmail.com>
 */
class PluginInfobloxSync extends CommonDBTM {
    
    /**
     * Set a record as unsyncronized.
     * @param string $itemtype
     * @param int $items_id
     */
    public function setUnsync($itemtype, $items_id) {
        $this->getFromDBByCrit(array(
            'itemtype' => $itemtype,
            'items_id' => $items_id
        ));
        
        $this->fields['synchronized'] = 0;
        $this->updateInDB(array('synchronized'));
    }
}
