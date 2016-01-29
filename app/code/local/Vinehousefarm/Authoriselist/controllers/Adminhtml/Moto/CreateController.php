<?php
/**
 * @package Vine-House-Farm.
 * @author A.Treitjak <a.treitjak@gmail.com>
 * @copyright 2012 - 2015 BelVG.com
 */

class Vinehousefarm_Authoriselist_Adminhtml_Moto_CreateController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Additional initialization
     *
     */
    protected function _construct()
    {
        $this->setUsedModuleName('Vinehousefarm_Authoriselist');

        // During order creation in the backend admin has ability to add any products to order
        Mage::helper('catalog/product')->setSkipSaleableCheck(true);
    }

    /**
     * Retrieve session object
     *
     * @return Mage_Adminhtml_Model_Session_Quote
     */
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session_quote');
    }

    /**
     * Retrieve quote object
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function _getQuote()
    {
        return $this->_getSession()->getQuote();
    }

    /**
     * Retrieve order create model
     *
     * @return Mage_Adminhtml_Model_Sales_Order_Create
     */
    protected function _getOrderCreateModel()
    {
        return Mage::getSingleton('adminhtml/sales_order_create');
    }

    /**
     * Retrieve gift message save model
     *
     * @return Mage_Adminhtml_Model_Giftmessage_Save
     */
    protected function _getGiftmessageSaveModel()
    {
        return Mage::getSingleton('adminhtml/giftmessage_save');
    }

    /**
     * Initialize order creation session data
     *
     * @return Mage_Adminhtml_Sales_Order_CreateController
     */
    protected function _initSession()
    {
        /**
         * Identify customer
         */
        if ($customerId = $this->getRequest()->getParam('customer_id')) {
            $this->_getSession()->setCustomerId((int) $customerId);
        }

        /**
         * Identify store
         */
        if ($storeId = $this->getRequest()->getParam('store_id')) {
            $this->_getSession()->setStoreId((int) $storeId);
        }

        /**
         * Identify currency
         */
        if ($currencyId = $this->getRequest()->getParam('currency_id')) {
            $this->_getSession()->setCurrencyId((string) $currencyId);
            $this->_getOrderCreateModel()->setRecollect(true);
        }

        //Notify other modules about the session quote
        Mage::dispatchEvent('create_order_session_quote_initialized',
            array('session_quote' => $this->_getSession()));

        //Notify other modules about the session quote
        Mage::dispatchEvent('moto_order_session_quote_initialized',
            array('session_quote' => $this->_getSession()));


        return $this;
    }

    /**
     * Processing request data
     *
     * @return Mage_Adminhtml_Sales_Order_CreateController
     */
    protected function _processData()
    {
        return $this->_processActionData();
    }

    /**
     * Process request data with additional logic for saving quote and creating order
     *
     * @param string $action
     * @return Mage_Adminhtml_Sales_Order_CreateController
     */
    protected function _processActionData($action = null)
    {
        $eventData = array(
            'order_create_model' => $this->_getOrderCreateModel(),
            'request_model'      => $this->getRequest(),
            'session'            => $this->_getSession(),
        );

        Mage::dispatchEvent('adminhtml_sales_order_create_process_data_before', $eventData);

        /**
         * Saving order data
         */
        if ($data = $this->getRequest()->getPost('order')) {
            $this->_getOrderCreateModel()->importPostData($data);
        }

        /**
         * Initialize catalog rule data
         */
        $this->_getOrderCreateModel()->initRuleData();

        /**
         * init first billing address, need for virtual products
         */
        $this->_getOrderCreateModel()->getBillingAddress();

        /**
         * Flag for using billing address for shipping
         */
        if (!$this->_getOrderCreateModel()->getQuote()->isVirtual()) {
            $syncFlag = $this->getRequest()->getPost('shipping_as_billing');
            $shippingMethod = $this->_getOrderCreateModel()->getShippingAddress()->getShippingMethod();
            if (is_null($syncFlag)
                && $this->_getOrderCreateModel()->getShippingAddress()->getSameAsBilling()
                && empty($shippingMethod)
            ) {
                $this->_getOrderCreateModel()->setShippingAsBilling(1);
            } else {
                $this->_getOrderCreateModel()->setShippingAsBilling((int)$syncFlag);
            }
        }

        /**
         * Change shipping address flag
         */
        if (!$this->_getOrderCreateModel()->getQuote()->isVirtual() && $this->getRequest()->getPost('reset_shipping')) {
            $this->_getOrderCreateModel()->resetShippingMethod(true);
        }

        /**
         * Collecting shipping rates
         */
        if (!$this->_getOrderCreateModel()->getQuote()->isVirtual() &&
            $this->getRequest()->getPost('collect_shipping_rates')
        ) {
            $this->_getOrderCreateModel()->collectShippingRates();
        }


        /**
         * Apply mass changes from sidebar
         */
        if ($data = $this->getRequest()->getPost('sidebar')) {
            $this->_getOrderCreateModel()->applySidebarData($data);
        }

        /**
         * Adding product to quote from shopping cart, wishlist etc.
         */
        if ($productId = (int) $this->getRequest()->getPost('add_product')) {
            $this->_getOrderCreateModel()->addProduct($productId, $this->getRequest()->getPost());
        }

        /**
         * Adding products to quote from special grid
         */
        if ($this->getRequest()->has('item') && !$this->getRequest()->getPost('update_items') && !($action == 'save')) {
            $items = $this->getRequest()->getPost('item');
            $items = $this->_processFiles($items);
            $this->_getOrderCreateModel()->addProducts($items);
        }

        /**
         * Update quote items
         */
        if ($this->getRequest()->getPost('update_items')) {
            $items = $this->getRequest()->getPost('item', array());
            $items = $this->_processFiles($items);
            $this->_getOrderCreateModel()->updateQuoteItems($items);
        }

        /**
         * Remove quote item
         */
        $removeItemId = (int) $this->getRequest()->getPost('remove_item');
        $removeFrom = (string) $this->getRequest()->getPost('from');
        if ($removeItemId && $removeFrom) {
            $this->_getOrderCreateModel()->removeItem($removeItemId, $removeFrom);
        }

        /**
         * Move quote item
         */
        $moveItemId = (int) $this->getRequest()->getPost('move_item');
        $moveTo = (string) $this->getRequest()->getPost('to');
        if ($moveItemId && $moveTo) {
            $this->_getOrderCreateModel()->moveQuoteItem($moveItemId, $moveTo);
        }

        if ($paymentData = $this->getRequest()->getPost('payment')) {
            $this->_getOrderCreateModel()->getQuote()->getPayment()->addData($paymentData);
        }

        $eventData = array(
            'order_create_model' => $this->_getOrderCreateModel(),
            'request'            => $this->getRequest()->getPost(),
        );

        Mage::dispatchEvent('adminhtml_sales_order_create_process_data', $eventData);

        $this->_getOrderCreateModel()
            ->saveQuote();

        if ($paymentData = $this->getRequest()->getPost('payment')) {
            $this->_getOrderCreateModel()->getQuote()->getPayment()->addData($paymentData);
        }

        /**
         * Saving of giftmessages
         */
        $giftmessages = $this->getRequest()->getPost('giftmessage');
        if ($giftmessages) {
            $this->_getGiftmessageSaveModel()->setGiftmessages($giftmessages)
                ->saveAllInQuote();
        }

        /**
         * Importing gift message allow items from specific product grid
         */
        if ($data = $this->getRequest()->getPost('add_products')) {
            $this->_getGiftmessageSaveModel()
                ->importAllowQuoteItemsFromProducts(Mage::helper('core')->jsonDecode($data));
        }

        /**
         * Importing gift message allow items on update quote items
         */
        if ($this->getRequest()->getPost('update_items')) {
            $items = $this->getRequest()->getPost('item', array());
            $this->_getGiftmessageSaveModel()->importAllowQuoteItemsFromItems($items);
        }

        $data = $this->getRequest()->getPost('order');
        $couponCode = '';
        if (isset($data) && isset($data['coupon']['code'])) {
            $couponCode = trim($data['coupon']['code']);
        }
        if (!empty($couponCode)) {
            if ($this->_getQuote()->getCouponCode() !== $couponCode) {
                $this->_getSession()->addError(
                    $this->__('"%s" coupon code is not valid.', $this->_getHelper()->escapeHtml($couponCode)));
            } else {
                $this->_getSession()->addSuccess($this->__('The coupon code has been accepted.'));
            }
        }

        return $this;
    }

    /**
     * Process buyRequest file options of items
     *
     * @param array $items
     * @return array
     */
    protected function _processFiles($items)
    {
        /* @var $productHelper Mage_Catalog_Helper_Product */
        $productHelper = Mage::helper('catalog/product');
        foreach ($items as $id => $item) {
            $buyRequest = new Varien_Object($item);
            $params = array('files_prefix' => 'item_' . $id . '_');
            $buyRequest = $productHelper->addParamsToBuyRequest($buyRequest, $params);
            if ($buyRequest->hasData()) {
                $items[$id] = $buyRequest->toArray();
            }
        }
        return $items;
    }

    /**
     * Index page
     */
    public function indexAction()
    {
        $this->_title($this->__('Sales'))->_title($this->__('Orders'))->_title($this->__('New Order'));
        $this->_initSession();
        $this->loadLayout();

        $this->_setActiveMenu('moto/order')
            ->renderLayout();
    }


    public function reorderAction()
    {
        $this->_getSession()->clear();
        $orderId = $this->getRequest()->getParam('order_id');
        $order = Mage::getModel('sales/order')->load($orderId);
        if (!Mage::helper('sales/reorder')->canReorder($order)) {
            return $this->_forward('noRoute');
        }

        if ($order->getId()) {
            $order->setReordered(true);
            $this->_getSession()->setUseOldShippingMethod(true);
            $this->_getOrderCreateModel()->initFromOrder($order);

            $this->_redirect('*/*');
        }
        else {
            $this->_redirect('*/sales_order/');
        }
    }

    protected function _reloadQuote()
    {
        $id = $this->_getQuote()->getId();
        $this->_getQuote()->load($id);
        return $this;
    }

    /**
     * Loading page block
     */
    public function loadBlockAction()
    {
        $request = $this->getRequest();

        //TODO need move
        $_POST['collect_shipping_rates'] = 1;

        try {
            $this->_initSession()
                ->_processData();
        }
        catch (Mage_Core_Exception $e){
            $this->_reloadQuote();
            $this->_getSession()->addError($e->getMessage());
        }
        catch (Exception $e){
            $this->_reloadQuote();
            $this->_getSession()->addException($e, $e->getMessage());
        }


        $asJson= $request->getParam('json');
        $block = $request->getParam('block');

        $update = $this->getLayout()->getUpdate();
        if ($asJson) {
            $update->addHandle('adminhtml_moto_create_load_block_json');
        } else {
            $update->addHandle('adminhtml_moto_create_load_block_plain');
        }

        if ($block) {
            $blocks = explode(',', $block);
            if ($asJson && !in_array('message', $blocks)) {
                $blocks[] = 'message';
            }

            foreach ($blocks as $block) {
                $update->addHandle('adminhtml_moto_create_load_block_' . $block);
            }
        }
        $this->loadLayoutUpdates()->generateLayoutXml()->generateLayoutBlocks();
        $result = $this->getLayout()->getBlock('content')->toHtml();
        if ($request->getParam('as_js_varname')) {
            Mage::getSingleton('adminhtml/session')->setUpdateResult($result);
            $this->_redirect('*/*/showUpdateResult');
        } else {
            $this->getResponse()->setBody($result);
        }
    }

    /**
     * Adds configured product to quote
     */
    public function addConfiguredAction()
    {
        $errorMessage = null;
        try {
            $this->_initSession()
                ->_processData();
        }
        catch (Exception $e){
            $this->_reloadQuote();
            $errorMessage = $e->getMessage();
        }

        // Form result for client javascript
        $updateResult = new Varien_Object();
        if ($errorMessage) {
            $updateResult->setError(true);
            $updateResult->setMessage($errorMessage);
        } else {
            $updateResult->setOk(true);
        }

        $updateResult->setJsVarName($this->getRequest()->getParam('as_js_varname'));
        Mage::getSingleton('adminhtml/session')->setCompositeProductResult($updateResult);
        $this->_redirect('*/catalog_product/showUpdateResult');
    }

    /**
     * Start order create action
     */
    public function startAction()
    {
        $this->_getSession()->clear();
        $this->_redirect('*/*', array('customer_id' => $this->getRequest()->getParam('customer_id')));
    }

    /**
     * Cancel order create
     */
    public function cancelAction()
    {
        if ($orderId = $this->_getSession()->getReordered()) {
            $this->_getSession()->clear();
            $this->_redirect('*/sales_order/view', array(
                'order_id'=>$orderId
            ));
        } else {
            $this->_getSession()->clear();
            $this->_redirect('*/*');
        }

    }

    public function getDirectModel()
    {
        return Mage::getModel('sagepaysuite/sagePayDirectProMoto');
    }

    /**
     * Saving quote and create order
     */
    public function saveAction()
    {
        try {
            $this->_processData();

            $paymentData = $this->getRequest()->getPost('payment');

            if ($paymentData) {
                $paymentData['checks'] = Mage_Payment_Model_Method_Abstract::CHECK_USE_INTERNAL
                    | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY
                    | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY
                    | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX
                    | Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL;
                $this->_getOrderCreateModel()->setPaymentData($paymentData);
                $this->_getOrderCreateModel()->getQuote()->getPayment()->addData($paymentData);
            }

            $orderData = $this->getRequest()->getPost('order');

            $orderData['shipping_method'] = Mage::helper('vinehousefarm_common')->getDefaultShippingMethod();

            $paymentData = $this->getRequest()->getPost('payment');

            if($paymentData && $paymentData['method'] == 'sagepaydirectpro_moto'){
                $result = $this->getDirectModel()->registerTransaction($this->getRequest()->getPost());
            }

            $order = $this->_getOrderCreateModel()
                ->setIsValidate(true)
                ->importPostData($orderData)
                ->createOrder();

            $this->_getSession()->clear();
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('The order has been created.'));
            if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/view')) {
                $this->_redirect('*/sales_order/view', array('order_id' => $order->getId()));
            } else {
                $this->_redirect('*/sales_order/index');
            }
        } catch (Mage_Payment_Model_Info_Exception $e) {
            $this->_getOrderCreateModel()->saveQuote();
            $message = $e->getMessage();
            if( !empty($message) ) {
                $this->_getSession()->addError($message);
            }
            $this->_redirect('*/*/');
        } catch (Mage_Core_Exception $e){
            $message = $e->getMessage();
            if( !empty($message) ) {
                $this->_getSession()->addError($message);
            }
            $this->_redirect('*/*/');
        }
        catch (Exception $e){
            $this->_getSession()->addException($e, $this->__('Order saving error: %s', $e->getMessage()));
            $this->_redirect('*/*/');
        }
    }

    /**
     * Acl check for admin
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed($this->_getAclResourse());
    }

    /**
     * Get acl resource
     *
     * @return string
     */
    protected function _getAclResourse()
    {
        $action = strtolower($this->getRequest()->getActionName());
        if (in_array($action, array('index', 'save')) && $this->_getSession()->getReordered()) {
            $action = 'reorder';
        }
        switch ($action) {
            case 'index':
            case 'save':
                $aclResource = 'moto/create/actions/create';
                break;
            case 'reorder':
                $aclResource = 'moto/create/actions/reorder';
                break;
            case 'cancel':
                $aclResource = 'moto/create/actions/cancel';
                break;
            default:
                $aclResource = 'moto/create/actions';
                break;
        }
        return $aclResource;
    }

    /*
     * Ajax handler to response configuration fieldset of composite product in order
     *
     * @return Mage_Adminhtml_Sales_Order_CreateController
     */
    public function configureProductToAddAction()
    {
        // Prepare data
        $productId  = (int) $this->getRequest()->getParam('id');

        $configureResult = new Varien_Object();
        $configureResult->setOk(true);
        $configureResult->setProductId($productId);
        $sessionQuote = Mage::getSingleton('adminhtml/session_quote');
        $configureResult->setCurrentStoreId($sessionQuote->getStore()->getId());
        $configureResult->setCurrentCustomerId($sessionQuote->getCustomerId());

        // Render page
        /* @var $helper Mage_Adminhtml_Helper_Catalog_Product_Composite */
        $helper = Mage::helper('adminhtml/catalog_product_composite');
        $helper->renderConfigureResult($this, $configureResult);

        return $this;
    }

    /*
     * Ajax handler to response configuration fieldset of composite product in quote items
     *
     * @return Mage_Adminhtml_Sales_Order_CreateController
     */
    public function configureQuoteItemsAction()
    {
        // Prepare data
        $configureResult = new Varien_Object();
        try {
            $quoteItemId = (int) $this->getRequest()->getParam('id');
            if (!$quoteItemId) {
                Mage::throwException($this->__('Quote item id is not received.'));
            }

            $quoteItem = Mage::getModel('sales/quote_item')->load($quoteItemId);
            if (!$quoteItem->getId()) {
                Mage::throwException($this->__('Quote item is not loaded.'));
            }

            $configureResult->setOk(true);
            $optionCollection = Mage::getModel('sales/quote_item_option')->getCollection()
                ->addItemFilter(array($quoteItemId));
            $quoteItem->setOptions($optionCollection->getOptionsByItem($quoteItem));

            $configureResult->setBuyRequest($quoteItem->getBuyRequest());
            $configureResult->setCurrentStoreId($quoteItem->getStoreId());
            $configureResult->setProductId($quoteItem->getProductId());
            $sessionQuote = Mage::getSingleton('adminhtml/session_quote');
            $configureResult->setCurrentCustomerId($sessionQuote->getCustomerId());

        } catch (Exception $e) {
            $configureResult->setError(true);
            $configureResult->setMessage($e->getMessage());
        }

        // Render page
        /* @var $helper Mage_Adminhtml_Helper_Catalog_Product_Composite */
        $helper = Mage::helper('adminhtml/catalog_product_composite');
        $helper->renderConfigureResult($this, $configureResult);

        return $this;
    }


    /**
     * Show item update result from loadBlockAction
     * to prevent popup alert with resend data question
     *
     */
    public function showUpdateResultAction()
    {
        $session = Mage::getSingleton('adminhtml/session');
        if ($session->hasUpdateResult() && is_scalar($session->getUpdateResult())){
            $this->getResponse()->setBody($session->getUpdateResult());
            $session->unsUpdateResult();
        } else {
            $session->unsUpdateResult();
            return false;
        }
    }

    /**
     * Process data and display index page
     */
    public function processDataAction()
    {
        $this->_initSession();
        $this->_processData();
        $this->_forward('index');
    }
}