<?php

/**
 * @file
 * Contains \Drupal\netauth\Controller\SoapCallController.
 */

namespace Drupal\netauth\Controller;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Controller\ControllerBase;

/**
 * Netauth settings form.
 */
class SoapCallController extends ControllerBase {

	/**
	 * Setting up header for SOAP call method
	 *
	 * @param $token
	 * @param $namespace
	 * @return soap header
	 */
	private function setupSoapHeader(){
        $token = null;
        if ($this->response == null || !isset($this->response['AuthorizationToken']) || !isset($this->response['AuthorizationToken']->Token)){
            $token = $this->Authenticate();
        } else {
            $token = $this->response['AuthorizationToken']->Token;
        }
        return new SoapHeader($this->namespace, "AuthorizationToken", array("Token" => $token));
    }

    /**
	 * Callback SOAP API with it's header
	 *
	 * @param $header
	 * @param $funcName
	 * @param $params
	 * @return soap call globally for all
	 */
    public static function soapCall($wsdlurl,$funcName, $params){
        $header = $this->setupSoapHeader();
        $this->response = array();
        $client = new SoapClient($wsdlurl, array('trace' => 1));
        return $this->soapClient->__soapCall($funcName, $params, null, $header, $this->response);
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

	/**
	 *
	 * @param $username
	 * @param $password
	 * @return string user_cst key | bool false
	 */
	public static function _netforum_cstKey($user,$pass,$ssoToken) {
		$authToken = _netforum_ssoauth($user,$pass);
		$client = new SoapClient("https://netforum.avectra.com/xWeb/Signon.asmx?WSDL", array('trace' => 1));
		$result = $client->__soapCall("GetCstKeyFromSignOnToken", array("params"=>array("AuthToken"=>$authToken,"szEncryptedSingOnToken"=>$ssoToken)), null, null, $response);
		return $result->GetCstKeyFromSignOnTokenResult;
	}

	/**
	 * Make authorize to user via netFORUM console
	 *
	 * @param $user
	 * @param $pass
	 */
	public static function _netforum_authenticate($wsdlurl,$user,$pass) {
		$response = "";
		$client = new SoapClient($wsdlurl, array('trace' => 1));
		$result = $client->__soapCall("Authenticate", array("params"=>array("userName"=>$user,"password"=>$pass)), null, null, $response);
		$getResult = $result->AuthenticateResult;
		return $response['AuthorizationToken']->Token;
	}

	/**
	 *
	 * @param $username
	 * @param $password
	 * @return string user_cst key | bool false
	 */
	public static function _netforum_getCustByKey($wsdlurl,$user,$pass,$cstKey) {
		$response = array();
		$client = new SoapClient($wsdlurl, array('trace' => 1));
		$result = $client->__soapCall("Authenticate", array("params"=>array("userName"=>$user,"password"=>$pass)),null,null,$response);
		$getResult = $result->AuthenticateResult;
		$token = $response['AuthorizationToken']->Token;

		// Making of soapHeader
		if ($response != null || isset($response['AuthorizationToken']) || isset($response['AuthorizationToken']->Token)){
			$token = $response['AuthorizationToken']->Token;
		}
		$header = new SoapHeader($getResult, "AuthorizationToken", array("Token" => $token));
		$result2 = $client->__soapCall("GetCustomerByKey",array("GetCustomerByKey"=>array("szCstKey"=>$cstKey)),null,$header,$inforesponse);
		$cust_info = new SimpleXMLElement($result2->GetCustomerByKeyResult->any);
		$array = json_decode(json_encode((array)$cust_info), TRUE);
		return $array['Result'];
	}

	/**
	 *
	 * @param $username
	 * @param $password
	 * @return string user_mail id
	 */
	public static function _netforum_getCustByMail($wsdlurl,$user,$pass,$email) {
		$response = array();
		$client = new SoapClient($wsdlurl, array('trace' => 1));
		$result = $client->__soapCall("Authenticate", array("params"=>array("userName"=>$user,"password"=>$pass)),null,null,$response);
		$getResult = $result->AuthenticateResult;
		$token = $response['AuthorizationToken']->Token;

		// Making of soapHeader
		if ($response != null || isset($response['AuthorizationToken']) || isset($response['AuthorizationToken']->Token)){
			$token = $response['AuthorizationToken']->Token;
		}
		$array['Result'] = array();
		$header = new SoapHeader($getResult, "AuthorizationToken", array("Token" => $token));
		$result2 = $client->__soapCall("GetCustomerByEmail",array("GetCustomerByEmail"=>array("szEmailAddress"=>$email)),null,$header,$inforesponse);
		$cust_info = new SimpleXMLElement($result2->GetCustomerByEmailResult->any);
		$array = json_decode(json_encode((array)$cust_info), TRUE);
		return (!$array['Result']) ? "" : $array['Result'];
	}

	/**
	 *
	 * @param $username
	 * @param $password
	 * @return string user_mail id
	 * @return string user_mail id
	 */
	public static function _netforum_setPassword($wsdlurl,$user,$pass,$key,$node) {
		$response = array();
		$client = new SoapClient($wsdlurl, array('trace' => 1));
		$result = $client->__soapCall("Authenticate", array("params"=>array("userName"=>$user,"password"=>$pass)),null,null,$response);
		$getResult = $result->AuthenticateResult;
		$token = $response['AuthorizationToken']->Token;

		// Making of soapHeader
		if ($response != null || isset($response['AuthorizationToken']) || isset($response['AuthorizationToken']->Token)){
			$token = $response['AuthorizationToken']->Token;
		}
		$header = new SoapHeader($getResult, "AuthorizationToken", array("Token" => $token));
		$result2 = $client->__soapCall("SetIndividualInformation",array("SetIndividualInformation"=>array("IndividualKey"=>$key,"oUpdateNode"=>$node)),null,$header,$inforesponse);
		return $setupinfo = $result2->GetCustomerByEmailResult->any;
	}

}	
