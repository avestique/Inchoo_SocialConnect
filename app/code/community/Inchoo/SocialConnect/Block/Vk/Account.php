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

class Inchoo_SocialConnect_Block_VK_Account extends Mage_Core_Block_Template
{
    protected $client = null;
    protected $userInfo = null;

    protected function _construct() {
        parent::_construct();

        $this->client = Mage::getSingleton('inchoo_socialconnect/vk_client');
        if(!($this->client->isEnabled())) {
            return;
        }

        $this->userInfo = Mage::registry('inchoo_socialconnect_vk_userinfo');

        $this->setTemplate('inchoo/socialconnect/vk/account.phtml');
    }

    protected function _enableEmailService()
    {
        return $this->client->enableEmailService();
    }

    protected function _hasUserInfo()
    {
        return (bool) $this->userInfo;
    }

    protected function _getVKId()
    {
        return $this->userInfo->uid;
    }

    protected function _getStatus()
    {
        if(!empty($this->userInfo->screen_name)) {
            $link = '<a href="http://vk.com/'.$this->userInfo->screen_name.'" target="_blank">'.
                    $this->htmlEscape($this->_getName()).'</a>';
        } else {
            $link = $this->_getName();
        }

        return $link;
    }

    protected function _getEmail()
    {
        $customer = Mage::getSingleton('customer/session')->getCustomer();

        return $customer->getInchooSocialconnectVemail() ? $customer->getEmail() : NULL;
    }

    protected function _getPicture()
    {
        if(!empty($this->userInfo->photo_200_orig)) {
            return Mage::helper('inchoo_socialconnect/vk')
                    ->getProperDimensionsPictureUrl($this->userInfo->uid,
                            $this->userInfo->photo_200_orig);
        }

        return null;
    }

    protected function _getName()
    {
        return $this->userInfo->first_name . ' ' . $this->userInfo->last_name;
    }

    protected function _getGender()
    {
        if(!empty($this->userInfo->sex)) {
            return $this->userInfo->sex ==  2 ? $this->__('Male') :  $this->__('Female');
        }

        return null;
    }

    protected function _getBirthday()
    {
        if(!empty($this->userInfo->bdate)) {
            $birthday = date('F j, Y', strtotime($this->userInfo->bdate));
            return $birthday;
        }

        return null;
    }

}