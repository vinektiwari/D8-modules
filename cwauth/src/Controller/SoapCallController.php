<?php
/**
 * @file
 * Contains \Drupal\cwauth\Controller\SoapCallController.
 */

namespace Drupal\cwauth\Controller;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Controller\ControllerBase;

/**
 * Netauth settings form.
 */
class SoapCallController extends ControllerBase {
	
	/**
	 * Callback SOAP API call
	 *
	 * @param $wsdlurl
	 * @param $funcName
	 * @param $params
	 * @return soap call globally for all
	 */
    public static function soapCall($wsdlurl, $funcName, $params) {
        $this->response = array();
        $client = new SoapClient($wsdlurl, array('trace' => 1));
        return $this->soapClient->__soapCall($funcName, $params, null, $this->response);
    }

	/**
	 * Attempts to authorize a user via netFORUM
	 *
	 * @param $user
	 * @param $pass
	 * @return string auth Token
	 */
	public static function _netforum_ssoauth($user,$pass) {
		$response = "";
		$client = new SoapClient("https://netforum.avectra.com/xWeb/Signon.asmx?WSDL", array('trace' => 1));
		$result = $client->__soapCall("Authenticate", array("params"=>array("userName"=>$user,"password"=>$pass)), null, null, $response);
		return $result->AuthenticateResult;
	}

	/**
	 * Attempts to authorize a user via netFORUM
	 *
	 * @param $username
	 * @param $password
	 * @return string SSO Token | bool false
	 */
	public static function _netforum_ssoToken($user,$pass,$authToken) {
		try {
			$response = "";
			$client = new SoapClient("https://netforum.avectra.com/xWeb/Signon.asmx?WSDL", array('trace' => 1));
			$result = $client->__soapCall("GetSignOnToken", array("params"=>array("Email"=>$user,"Password"=>$pass,"AuthToken"=>$authToken,"Minutes"=>"45")), null, null, $response);
			return $result->GetSignOnTokenResult;
		} catch (SoapFault $fault) {
			trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);
			return drupal_set_message(t('Invalid credentials! Please contact administrator.'), 'error');
		}
	}
}

