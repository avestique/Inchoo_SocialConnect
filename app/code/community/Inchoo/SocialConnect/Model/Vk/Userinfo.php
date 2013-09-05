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

class Inchoo_SocialConnect_Model_Vk_Userinfo
{
    protected $client = null;
    protected $userInfo = null;

    public function __construct() {
        if(!Mage::getSingleton('customer/session')->isLoggedIn())
            return;

        $this->client = Mage::getSingleton('inchoo_socialconnect/vk_client');
        if(!($this->client->isEnabled())) {
            return;
        }

        $customer = Mage::getSingleton('customer/session')->getCustomer();

        if(($socialconnectVid = $customer->getInchooSocialconnectVid()) &&
                ($socialconnectVtoken = $customer->getInchooSocialconnectVtoken())) {
            $helper = Mage::helper('inchoo_socialconnect/vk');

            try{
                $this->client->setAccessToken($socialconnectVtoken);
                $userInfo = $this->client->api(
                    '/method/users.get',
                    'GET',
                    array(
                        'fields' =>
                        'nickname,screen_name,sex,bdate,city,country,timezone,photo_50,photo_100,photo_200_orig,has_mobile,contacts,education,online,counters,relation,last_seen,status,can_write_private_message,can_see_all_posts,can_see_audio,can_post,universities,schools'
                    )
                );

                if ($userInfo->response && isset($userInfo->response[0]))
                {
                    $this->userInfo = current($userInfo->response);
                }
                else
                {
                    $message = Mage::helper('inchoo_socialconnect')->__('Unspecified OAuth error occurred.');
                    throw new Inchoo_SocialConnect_VkOAuthException($message);
                }
            } catch(VkOAuthException $e) {
                $helper->disconnect($customer);
                Mage::getSingleton('core/session')->addNotice($e->getMessage());
            } catch(Exception $e) {
                $helper->disconnect($customer);
                Mage::getSingleton('core/session')->addError($e->getMessage());
            }

        }
    }

    public function getUserInfo()
    {
        return $this->userInfo;
    }
}