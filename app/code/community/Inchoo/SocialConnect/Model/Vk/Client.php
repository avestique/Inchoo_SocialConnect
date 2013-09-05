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

class Inchoo_SocialConnect_Model_Vk_Client
{
    const REDIRECT_URI_ROUTE = 'socialconnect/vk/connect';

    const XML_PATH_ENABLED = 'customer/inchoo_socialconnect_vk/enabled';
    const XML_PATH_CLIENT_ID = 'customer/inchoo_socialconnect_vk/client_id';
    const XML_PATH_CLIENT_SECRET = 'customer/inchoo_socialconnect_vk/client_secret';
    const XML_PATH_CLIENT_USE_EMAIL = 'customer/inchoo_socialconnect_vk/use_email';
    const XML_PATH_CLIENT_EMAIL_SERVICE = 'customer/inchoo_socialconnect_vk/email_service';

    //const KEY_GEN = 'this_is_unique_key_for_email_creation.You can change it only _once_!!!';

    const OAUTH2_SERVICE_URI = 'https://api.vk.com';
    const OAUTH2_AUTH_URI = 'https://oauth.vk.com/authorize';
    const OAUTH2_TOKEN_URI = 'https://oauth.vk.com/access_token';

    protected $clientId = null;
    protected $clientSecret = null;
    protected $redirectUri = null;
    protected $state = '';
    protected $scope = array(); // http://vk.com/dev/permissions

    protected $token = null;

    public function __construct($params = array())
    {
        if(($this->isEnabled = $this->_isEnabled())) {
            $this->clientId = $this->_getClientId();
            $this->clientSecret = $this->_getClientSecret();
            $this->redirectUri = Mage::getModel('core/url')->sessionUrlVar(
                Mage::getUrl(self::REDIRECT_URI_ROUTE)
            );

            if(!empty($params['scope'])) {
                $this->scope = $params['scope'];
            }

            if(!empty($params['state'])) {
                $this->state = $params['state'];
            }
        }
    }

    public function enableAutoEmail()
    {
        return $this->_getStoreConfig(self::XML_PATH_CLIENT_USE_EMAIL);
    }

    public function isEnabled()
    {
        return (bool) $this->isEnabled;
    }

    public function getClientId()
    {
        return $this->clientId;
    }

    public function getClientSecret()
    {
        return $this->clientSecret;
    }

    public function getRedirectUri()
    {
        return $this->redirectUri;
    }

    public function getScope()
    {
        return $this->scope;
    }

    public function getState()
    {
        return $this->state;
    }

    public function setState($state)
    {
        $this->state = $state;
    }

    public function setAccessToken($token)
    {
        $this->token = json_decode($token);
    }

    public function getAccessToken()
    {
        if(empty($this->token)) {
            $this->fetchAccessToken();
        }

        return json_encode($this->token);
    }

    public function createAuthUrl()
    {
        $url =
        self::OAUTH2_AUTH_URI.'?'.
            http_build_query(
                array(
                    'client_id' => $this->clientId,
                    'redirect_uri' => $this->redirectUri,
                    'scope' => implode(',', $this->scope),
                    'state' => $this->state,
                    'response_type' => 'code'
                    )
            );
        return $url;
    }

    public function api($endpoint, $method = 'GET', $params = array())
    {
        if(empty($this->token)) {
            $this->fetchAccessToken();
        }

        $url = self::OAUTH2_SERVICE_URI.$endpoint;

        $method = strtoupper($method);

        $params = array_merge(array(
            'access_token' => $this->token->access_token
        ), $params);

        $response = $this->_httpRequest($url, $method, $params);

        return $response;
    }

    protected function fetchAccessToken()
    {
        if(empty($_REQUEST['code'])) {
            throw new Exception(
                Mage::helper('inchoo_socialconnect')
                    ->__('Unable to retrieve access code.')
            );
        }

        $response = $this->_httpRequest(
            self::OAUTH2_TOKEN_URI,
            'POST',
            array(
                'code' => $_REQUEST['code'],
                'redirect_uri' => $this->redirectUri,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret
            )
        );

        $this->token = $response;
    }

    protected function _httpRequest($url, $method = 'GET', $params = array())
    {

        $client = new Zend_Http_Client($url, array('timeout' => 60));

        switch ($method) {
            case 'GET':
                $client->setParameterGet($params);
                break;
            case 'POST':
                $client->setParameterPost($params);
                break;
            case 'DELETE':
                $client->setParameterGet($params);
                break;
            default:
                throw new Exception(
                    Mage::helper('inchoo_socialconnect')
                        ->__('Required HTTP method is not supported.')
                );
        }

        $response = $client->request($method);

        Mage::log($response->getStatus().' - '. $response->getBody());

        $decoded_response = json_decode($response->getBody());

        /*
         * Per http://tools.ietf.org/html/draft-ietf-oauth-v2-27#section-5.1
         * Facebook should return data using the "application/json" media type.
         * Facebook violates OAuth2 specification and returns string. If this
         * ever gets fixed, following condition will not be used anymore.
         */
        if(empty($decoded_response)) {
            $parsed_response = array();
            parse_str($response->getBody(), $parsed_response);

            $decoded_response = json_decode(json_encode($parsed_response));
        }

        if($response->isError()) {
            $status = $response->getStatus();
            if(($status == 400 || $status == 401)) {
                if($response->getMessage()) {
                    $message = $response->getMessage();
                } else {
                    $message = Mage::helper('inchoo_socialconnect')
                        ->__('Unspecified OAuth error occurred.');
                }

                throw new Inchoo_SocialConnect_VkOAuthException($message);
            } else {
                $message = sprintf(
                    Mage::helper('inchoo_socialconnect')
                        ->__('HTTP error %d occurred while issuing request.'),
                    $status
                );

                throw new Exception($message);
            }
        }

        return $decoded_response;
    }

    protected function _isEnabled()
    {
        return $this->_getStoreConfig(self::XML_PATH_ENABLED);
    }

    protected function _getClientId()
    {
        return $this->_getStoreConfig(self::XML_PATH_CLIENT_ID);
    }

    protected function _getClientSecret()
    {
        return $this->_getStoreConfig(self::XML_PATH_CLIENT_SECRET);
    }

    protected function _getStoreConfig($xmlPath)
    {
        return Mage::getStoreConfig($xmlPath, Mage::app()->getStore()->getId());
    }

    public function getEmail($nickName)
    {
        if ($nickName)
        {
            if ($this->_getStoreConfig(self::XML_PATH_CLIENT_USE_EMAIL))
            {
                return $nickName . '_' . substr(MD5(uniqid(rand(), TRUE)), 0, 3) . substr(MD5(uniqid(rand(), TRUE)), 12, 3) . "@" . $this->_getStoreConfig(self::XML_PATH_CLIENT_EMAIL_SERVICE);
            }

            return $nickName . '@' . $_SERVER['HTTP_HOST'];
        }

        return NULL;
    }

}

class Inchoo_SocialConnect_VkOAuthException extends Exception
{}