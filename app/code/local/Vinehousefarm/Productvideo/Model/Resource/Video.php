<?php
/**
 * @package Vine-House-Farm.
 * @author: A.A.Treitjak <a.treitjak@gmail.com>
 * @copyright: 2012 - 2015 BelVG.com
 */

class Vinehousefarm_Productvideo_Model_Resource_Video extends Mage_Core_Model_Resource_Db_Abstract
{
    public function _construct()
    {
        $this->_init('productvideo/video', 'entity_id');
    }
}