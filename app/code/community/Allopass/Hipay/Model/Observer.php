<?php
class Allopass_Hipay_Model_Observer
{
	/**
	 * Cancel orders stayed in pending because customer not validated payment form
	 */
	public function cancelOrdersInPending()
	{
		
		$methodCodes = array('hipay_cc'=>'hipay/method_cc','hipay_hosted'=>'hipay/method_hosted');
		foreach ($methodCodes as $methodCode=>$model)
		{
			if(!Mage::getStoreConfig('payment/'.$methodCode."/cancel_pending_order"))
				continue;
			
			$limitedTime = 30;
				
			$date = new Zend_Date();//Mage::app()->getLocale()->date();
			
			/* @var $collection Mage_Sales_Model_Resource_Order_Collection */
			$collection = Mage::getResourceModel('sales/order_collection');
			$collection->addFieldToSelect(array('entity_id','state'))
				
			->addAttributeToFilter('created_at', array('to' => ($date->subMinute($limitedTime)->toString('Y-MM-dd HH:mm:ss'))))
			;
			
			
			/* @var $order Mage_Sales_Model_Order */
			foreach ($collection as $order)
			{
	
				if($order->getPayment()->getMethod() == $methodCode)
				{
					if($order->canCancel() && $order->getState() == Mage_Sales_Model_Order::STATE_NEW)
					{
						try {
							$order->cancel();
							$order
							->addStatusToHistory($order->getStatus(),
									// keep order status/state
									Mage::helper('hipay')->__("Order canceled automatically by cron because order is pending since %d minutes",$limitedTime));
	
							$order->save();
						} catch (Exception $e) {
							Mage::logException($e);
						}
					}
				}
			}
		}
		return $this;
	}
	
	public function manageOrdersInPendingCapture()
	{
		$methods = array('hipay_cc','hipay_hosted');
		/* @var $collection Mage_Sales_Model_Resource_Order_Collection */
		$collection = Mage::getResourceModel('sales/order_collection');
		$collection->addFieldToFilter('status','pending_capture');
		
		/* @var $order Mage_Sales_Model_Order */
		foreach ($collection as $order)
		{
			if(!in_array($order->getPayment()->getMethod(), $methods))
				continue;
			
			$orderDate = "";
		}
		
	}
	
	public function displaySectionCheckoutIframe($observer)
	{
		$payment = Mage::getSingleton('checkout/session')->getQuote()->getPayment();
		if($payment->getAdditionalInformation('use_oneclick'))
			return $this;
		/* @var $controller Mage_Checkout_OnepageController */
		$controller = $observer->getControllerAction();
		
		$result = Mage::helper('core')->jsonDecode($controller->getResponse()->getBody());
		
		//TODO check if payment method is hosted and iframe active and is success
		$methodInstance =  $payment->getMethodInstance(); 
		if($result['success'] 
				&& $methodInstance->getCode() == 'hipay_hosted' 
				&& $methodInstance->getConfigData('display_iframe'))
		{
			$result['iframeUrl'] = $result['redirect']; 
		}
		
		$controller->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
		
		return $this;
		
	}
}