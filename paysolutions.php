<?php

defined ('_JEXEC') or die('Restricted access');

if (!class_exists ('vmPSPlugin')) {
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmPaymentPaysolutions extends vmPSPlugin {

	function __construct (& $subject, $config) {

		parent::__construct ($subject, $config);

		$this->_loggable = TRUE;
		$this->tableFields = array_keys ($this->getTableSQLFields ());
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$varsToPush = $this->getVarsToPush ();
		$this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);

	}

	public function getVmPluginCreateTableSQL() {

		return $this->createTableSQL ('Paysolutions Table');
	}

	/**
	 * @return array
	 */
	function getTableSQLFields() {

		$SQLfields = array(
			'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'         => 'int(1) UNSIGNED',
			'demo'         				  => 'char(1)',
			'paysolutions_account'        => 'varchar(5000)'
		);

		return $SQLfields;
	}

	/**
	 * @param $cart
	 * @param $order
	 * @return bool|null
	 */
	function plgVmConfirmedOrder ($cart, $order) {

		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return FALSE;
		}
		
		VmConfig::loadJLang('com_virtuemart',true);
		VmConfig::loadJLang('com_virtuemart_orders', TRUE);
		
		$session = JFactory::getSession();
		$return_context = $session->getId();
		$paysolutions_server = $method->demo;
		$this->logInfo('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');

		if (!class_exists('VirtueMartModelOrders')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}
		if (!class_exists('VirtueMartModelCurrency')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'currency.php');
		}

		$address = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);

		if (!class_exists('TableVendors')) {
			require(VMPATH_ADMIN . DS . 'tables' . DS . 'vendors.php');
		}
		$vendorModel = VmModel::getModel('Vendor');
		$vendorModel->setId(1);
		$vendor = $vendorModel->getVendor();
		$vendorModel->addImages($vendor, 1);
		$this->getPaymentCurrency($method);
		$email_currency = $this->getEmailCurrency($method);
		$currency_code_3 = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');

		$totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total,$method->payment_currency);
		$cd = CurrencyDisplay::getInstance($cart->pricesCurrency);
		if ($totalInPaymentCurrency <= 0) {
			vmInfo(JText::_('Incorrect payment amount'));
			return FALSE;
		}

		$quantity = 0;
		foreach ($cart->products as $key => $product) {
			$quantity = $quantity + $product->quantity;
		}
		
		//check currency
		switch($currency_code_3){
		case 'THB':
			$cur = 764;
			break;
		case 'AUD':
			$cur = 036;
			break;		
		case 'GBP':
			$cur = 826;
			break;	
		case 'EUR':
			$cur = 978;
			break;		
		case 'HKD':
			$cur = 344;
			break;		
		case 'JPY':
			$cur = 392;
			break;		
		case 'NZD':
			$cur = 554;
			break;
		case 'SGD':
			$cur = 702;
			break;	
		case 'CHF':
			$cur = 756;
			break;	
		case 'USD':
			$cur = 840;
			break;	
		default:
			$cur = 764;
		}
		
		//switch server
		switch($paysolutions_server){
		case '0':
			$payso_server = "https://www.thaiepay.com/epaylink/payment.aspx";
			break;
		case '1':
			$payso_server = "https://www.thaiepay.com/epaylink/payment.aspx";
			break;
		default:
			$payso_server = "https://www.thaiepay.com/epaylink/payment.aspx";
		}
		

		// add spin image
		$html = '<form action="'.$payso_server.'" method="post" name="vm_paysolutions_form"  accept-charset="UTF-8">';
		$html .= '<input type="submit"  value="' . JText::_('Redirecting to PAYSOLUTIONS payment gateway...') . '" />';
		$html .= '<input type="hidden" name="payso" value="payso" />
<input type="hidden" name="merchantid" value="'.$method->paysolutions_account.'" />
<input type="hidden" name="refno" value="'.$order['details']['BT']->virtuemart_order_id.'" />
<input type="hidden" name="customeremail" value="'.$order['email']['BT']->order_number.'" />
<input type="hidden" name="productdetail" value="'.JText::_('Payment for order') . ': ' . $order['details']['BT']->order_number. '">
<input type="hidden" name="total" value="'.$totalInPaymentCurrency['value'].'" />
<input type="hidden" name="currencyCode" value="'.$cur.'" />
<input type="hidden" name="postURL" value="'. substr(JURI::root(false,''),0,-1) . JROUTE::_( 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . JRequest::getInt('Itemid'), false).'" />
<input type="hidden" name="reqURL" value="'.substr(JURI::root(false,''),0,-1) . JROUTE::_('index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component', false).'" />

</form></div>';
		$html .= ' <script type="text/javascript">';
		$html .= ' document.vm_paysolutions_form.submit();';
		$html .= ' </script>';

		$cart->_confirmDone = FALSE;
		$cart->_dataValidated = FALSE;
		$cart->setCartIntoSession();
		JRequest::setVar('html', $html);
		
	}

	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL;
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return FALSE;
		}
		$this->getPaymentCurrency($method);
		$paymentCurrencyId = $method->payment_currency;
	}

	function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId) {


	}

	function plgVmOnPaymentResponseReceived(&$html) {

		if (!class_exists('VirtueMartCart')) {
			require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
		}
		if (!class_exists('shopFunctionsF')) {
			require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
		}
		if (!class_exists('VirtueMartModelOrders')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}

		$cart = VirtueMartCart::getCart();
		$cart->emptyCart();
		return TRUE;
	}

	function plgVmOnUserPaymentCancel() {

		return TRUE;
	}

	function plgVmOnPaymentNotification() {

		if (!class_exists('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}
		$paysolutions_data = JRequest::get('post');
		if (!isset($paysolutions_data['status'])) {
			return FALSE;
		}
		
		$result = $paysolutions_data['result'];
		$status = $paysolutions_data['status'];
		$order_id = $paysolutions_data['refno'];
		$total = $paysolutions_data['total'];
		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_id))) {
			return FALSE;
		}


			$db = JFactory::getDBO ();
			$q = 'SELECT * FROM `#__virtuemart_orders` '
				. 'WHERE `virtuemart_order_id` = '.$virtuemart_order_id;

			$db->setQuery ($q);
			$payments = $db->loadObject();

			$payments->order_total;
			if($payments->order_total != $total){
				$this->logInfo("STATUS_URL FAIL: REASON: can not load ORDER; POST: ".serialize($paysolutions_data)."; STRING: $string; HASH: $hash", 'message');
			}

		$method = $this->getVmPluginMethod($payments->virtuemart_paymentmethod_id);

		if (!$this->selectedThisElement($method->payment_element)) {
			return FALSE;
		}
		
		$modelOrder = VmModel::getModel('orders');
		$order = array();

		
			$this->logInfo("STATUS_URL SUCCESS: POST: ".serialize($paysolutions_data)."; STRING: $string; HASH: $hash", 'message');
			
			$order['order_status']='C';
			
			$this->logInfo('plgVmOnPaymentNotification return new_status:' . $order['order_status'], 'message');
			
			$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, TRUE);
			
			$this->emptyCart($order_number, $order_number);

	

		die('done');
	}

	function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id) {

		if (!$this->selectedThisByMethodId($payment_method_id)) {
			return NULL;
		}

		if (!($payments = $this->getDatasByOrderId($virtuemart_order_id))) {
			return '';
		}

		$html = '<table class="adminlist" width="50%">' . "\n";
		$html .= $this->getHtmlHeaderBE();
		$code = "paysolutions_response_";
		$first = TRUE;
		foreach ($payments as $payment) {
			$html .= '<tr class="row1"><td>' . JText::_('VMPAYMENT_PAYSOLUTIONS_DATE') . '</td><td align="left">' . $payment->created_on . '</td></tr>';
			if ($first) {
				$html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', $payment->payment_name);
				if ($payment->payment_order_total and  $payment->payment_order_total != 0.00) {
					$html .= $this->getHtmlRowBE('paysolutions_PAYMENT_ORDER_TOTAL', $payment->payment_order_total . " " . shopFunctions::getCurrencyByID($payment->payment_currency, 'currency_code_3'));
				}
				if ($payment->email_currency and  $payment->email_currency != 0) {
					$html .= $this->getHtmlRowBE('PAYSOLUTIONS_PAYMENT_EMAIL_CURRENCY', shopFunctions::getCurrencyByID($payment->email_currency, 'currency_code_3'));
				}
				$first = FALSE;
			}
			foreach ($payment as $key => $value) {
				if ($value) {
					if (substr($key, 0, strlen($code)) == $code) {
						$html .= $this->getHtmlRowBE($key, $value);
					}
				}
			}

		}
		$html .= '</table>' . "\n";
		return $html;
	}

	protected function checkConditions($cart, $method, $cart_prices) {

		return true;
	}

	function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {

		return $this->onStoreInstallPluginTable($jplugin_id);
	}

	public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg) {

		return $this->OnSelectCheck($cart);
	}

	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {

		return $this->displayListFE($cart, $selected, $htmlIn);
	}

	public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {

		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}

	function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {

		return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
	}

	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {

		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}

	function plgVmonShowOrderPrintPayment($order_number, $method_id) {

		return $this->onShowOrderPrint($order_number, $method_id);
	}


	function plgVmDeclarePluginParamsPaymentVM3(&$data) {

		return $this->declarePluginParams('payment',$data);
	}

	function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {

		return $this->setOnTablePluginParams($name, $id, $table);
	}

}