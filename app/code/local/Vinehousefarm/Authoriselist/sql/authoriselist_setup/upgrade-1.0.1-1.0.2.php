<?php
/**
 * @package Vine-House-Farm.
 * @author: A.A.Treitjak <a.treitjak@gmail.com>
 * @copyright: 2012 - 2015 BelVG.com
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

$this->_conn->addColumn($this->getTable('sales_flat_quote_item'), 'shipping_method', 'text');
$this->_conn->addColumn($this->getTable('sales_flat_order_item'), 'shipping_method', 'text');

$installer->endSetup();