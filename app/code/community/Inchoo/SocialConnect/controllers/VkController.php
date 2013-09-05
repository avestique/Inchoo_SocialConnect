<?php
/**
* Inchoo
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@magentocommerce.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Please do not edit or add to this file if you wish to upgrade
* Magento or this extension to newer versions in the future.
** Inchoo *give their best to conform to
* "non-obtrusive, best Magento practices" style of coding.
* However,* Inchoo *guarantee functional accuracy of
* specific extension behavior. Additionally we take no responsibility
* for any possible issue(s) resulting from extension usage.
* We reserve the full right not to provide any kind of support for our free extensions.
* Thank you for your understanding.
*
 * @category Inchoo
 * @package SocialConnect
 * @author Anton Sannikov <developer@avestique.ru>
 * @copyright Copyright (c) Avestique Developer (http://avestique.ru/)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

class Inchoo_SocialConnect_VkController extends Mage_Core_Controller_Front_Action
{
    protected $referer = null;

    public function connectAction()
    {
        try {
            $this->_connectCallback();
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
        }

        if(!empty($this->referer)) {
            $this->_redirectUrl($this->referer);
        } else {
            Mage::helper('inchoo_socialconnect')->redirect404($this);
        }
    }

    public function disconnectAction()
    {
        $customer = Mage::getSingleton('customer/session')->getCustomer();

        try {
            $this->_disconnectCallback($customer);
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($e->getMessage());
        }

        if(!empty($this->referer)) {
            $this->_redirectUrl($this->referer);
        } else {
            Mage::helper('inchoo_socialconnect')->redirect404($this);
        }
    }

    protected function _disconnectCallback(Mage_Customer_Model_Customer $customer) {
        $this->referer = Mage::getUrl('socialconnect/account/vk');  
        
        if ($referer = Mage::helper('inchoo_socialconnect/vk')->disconnect($customer))
            $this->referer = $referer;

        Mage::getSingleton('core/session')
            ->addSuccess(
                $this->__('You have successfully disconnected your Vk account from our store account.')
            );
    }

    protected function _connectCallback() {
        $errorCode = $this->getRequest()->getParam('error');
        $code = $this->getRequest()->getParam('code');
        $state = $this->getRequest()->getParam('state');
        if(!($errorCode || $code) && !$state) {
            // Direct route access - deny
            return;
        }
        
        $this->referer = Mage::getSingleton('core/session')
            ->getVkRedirect();

        if(!$state || $state != Mage::getSingleton('core/session')->getVkCsrf()) {
            return;
        }

        if($errorCode) {
            // Vk API read light - abort
            if($errorCode === 'access_denied') {
                Mage::getSingleton('core/session')
                    ->addNotice(
                        $this->__('VK Connect process aborted.')
                    );

                return;
            }

            throw new Exception(
                sprintf(
                    $this->__('Sorry, "%s" error occured. Please try again.'),
                    $errorCode
                )
            );

            return;
        }

        if ($code) {
            // VK API green light - proceed
            $client = Mage::getSingleton('inchoo_socialconnect/vk_client');

            $userInfo = $client->api('/method/users.get', 'get', array('fields' => 'nickname,screen_name'));
            $token = $client->getAccessToken();

            $userInfo = isset($userInfo->response[0]) ? $userInfo->response[0] : new Varien_Object();

            $customersByVkId = Mage::helper('inchoo_socialconnect/vk')
                ->getCustomersByVkId($userInfo->uid);

            if(Mage::getSingleton('customer/session')->isLoggedIn()) {
                // Logged in user
                if($customersByVkId->count()) {
                    // VK account already connected to other account - deny
                    Mage::getSingleton('core/session')
                        ->addNotice(
                            $this->__('Your VK account is already connected to one of our store accounts.')
                        );

                    return;
                }

                // Connect from account dashboard - attach
                $customer = Mage::getSingleton('customer/session')->getCustomer();

                Mage::helper('inchoo_socialconnect/vk')->connectByVkId(
                    $customer,
                    $userInfo->uid,
                    $token
                );

                Mage::getSingleton('core/session')->addSuccess(
                    $this->__('Your VK account is now connected to your store accout. You can now login using our Vk Connect button or using store account credentials you will receive to your email address.')
                );

                return;
            }

            if($customersByVkId->count()) {
                // Existing connected user - login
                $customer = $customersByVkId->getFirstItem();

                Mage::helper('inchoo_socialconnect/vk')->loginByCustomer($customer);

                Mage::getSingleton('core/session')
                    ->addSuccess(
                        $this->__('You have successfully logged in using your VK account.')
                    );

                return;
            }

            $email = $client->getEmail($userInfo->screen_name);

            /*$customersByEmail = Mage::helper('inchoo_socialconnect/vk')->getCustomersByEmail($email);

            if($customersByEmail->count()) {                
                // Email account already exists - attach, login
                $customer = $customersByEmail->getFirstItem();
                
                Mage::helper('inchoo_socialconnect/vk')->connectByVkId(
                    $customer,
                    $userInfo->uid,
                    $token
                );

                Mage::getSingleton('core/session')->addSuccess(
                    $this->__('We have discovered you already have an account at our store. Your VK account is now connected to your store account.')
                );

                return;
            }*/

            // New connection - create, attach, login
            if(empty($userInfo->first_name)) {
                throw new Exception(
                    $this->__('Sorry, could not retrieve your VK first name. Please try again.')
                );
            }

            if(empty($userInfo->last_name)) {
                throw new Exception(
                    $this->__('Sorry, could not retrieve your VK last name. Please try again.')
                );
            }

            Mage::helper('inchoo_socialconnect/vk')->connectByCreatingAccount(
                $email,
                $userInfo->first_name,
                $userInfo->last_name,
                $userInfo->uid,
                $token
            );

            Mage::getSingleton('core/session')->addSuccess(
                $this->__('Your VK account is now connected to your new user accout at our store. Now you can login using our Vk Connect button or using store account credentials you will receive to your email address.')
            );

            Mage::getSingleton('core/session')->addNotice(
                sprintf($this->__('Since VK doesn\'t support third-party access to your email address, we were unable to send you your store accout credentials. To be able to login using store account credentials you will need to update your email address and password using our <a href="%s">Edit Account Information</a>.'), Mage::getUrl('customer/account/edit'))
            );
        }
    }

}