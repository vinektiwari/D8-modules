<?php
/**
 * @file
 * Contains \Drupal\cwauth\Form\CwauthSettingsForm.
 */

namespace Drupal\cwauth\Form;

use Drupal\user\Entity\User;
use Drupal\user\Entity\Role;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AnonymousUserSession;


/**
 * C&Wauth settings form.
 */
class CwauthSettingsForm extends ConfigFormBase {
	/**
	* {@inheritdoc}
	*/
	public function getFormId() {
		return 'cwauth_settings_form';
	}

	/**
	* {@inheritdoc}
	* Implements hook_form()
	*
	* The callback function for settings up the form for 'Cambey & West' auth.
	* This callback function will display the form to the admin side into 'Cambey & West' package
	*
	* @param $node
	* @param $form_state
	* @return
	*/
	public function buildForm(array $form, FormStateInterface $form_state) {
		$config = $this->config('cwauth.settings');
		$form = parent::buildForm($form, $form_state);

		$cw_enable = $config->get('cwauth_enabled');
		$cw_api_uri = $config->get('cw_api_uri');
		$cw_pub_acronym = $config->get('cw_pub_acronym');
		$cw_username = $config->get('cw_username');
		$cw_password = $config->get('cw_password');
		$cw_only_mem_login = $config->get('cw_only_mem_login');
		$cw_members_role = $config->get('cw_members_role');
		$cw_suspended_role = $config->get('cw_suspended_role');
		$cw_inactive_role = $config->get('cw_inactive_role');
		$cw_cancelled_role = $config->get('cw_cancelled_role');

		$form['overview'] = array(
			'#markup' => t('Manage auth settings here'),
			'#prefix' => '<p>',
			'#suffix' => '</p>',
		);

		// Enable/Disable setting
		$form['cwauth_enabled'] = array(
			'#type' => 'checkbox',
			'#title' => t('Enable Cambey & West Authentication'),
			'#description' => t('When enabled, allows users to login/signin using C&W API.'),
			'#required' => TRUE,
			'#default_value' => $cw_enable['cwauth_enabled'],
			'#prefix' => '<div class="">',
            '#suffix' => '</div>',
		);
		// Ends here

		// C&W Auth Settings
		$form['auth'] = array(
			'#type' => 'details',
			'#title' => t('Cambey & West Auth Config Settings'),
			'#open' => TRUE, // Controls the HTML5 'open' attribute. Defaults to FALSE.
		);
		$form['auth']['cw_api_uri'] = array(
			'#type' => 'textfield',
			'#title' => t('API Endpoint URI'),
			'#description' => t('The API uri for Cambey & West, must start with http:// or https://.'),
			'#required' => TRUE,
			'#default_value' => $cw_api_uri['cw_api_uri'],
		);
		$form['auth']['cw_pub_acronym'] = array(
			'#type' => 'textfield',
			'#title' => t('Pub Acronym'),
			'#description' => t('The three digit identifier for your C&W publishing account.'),
			'#required' => TRUE,
			'#default_value' => $cw_pub_acronym['cw_pub_acronym'],
		);
		$form['auth']['cw_username'] = array(
			'#type' => 'textfield',
			'#title' => t('Authorized Username'),
			'#description' => t('The authorized API username for the C&W web service.'),
			'#required' => TRUE,
			'#default_value' => $cw_username['cw_username'],
		);
		$form['auth']['cw_password'] = array(
			'#type' => 'textfield',
			'#title' => t('Authorized Password'),
			'#description' => t('The authorized API password for the C&W web service.'),
			'#required' => TRUE,
			'#default_value' => $cw_password['cw_password'],
		);
		$form['auth']['cw_only_mem_login'] = array(
			'#type' => 'checkbox',
			'#title' => t('Only allow Cambey & West members to log in'),
			'#description' => t('With this option selected, only active member users of the Cambey & West system will be able to login. Inactive members, non-members, and users that only exist locally, will not be able to log in.<br> (Note: An exception is made for User 1 so that this site can still be administered.)'),
			'#default_value' => $cw_only_mem_login['cw_only_mem_login'],
		);
		// Ends here

		// Get all roles other than Anonymous and Authenticated
		$roles = Role::loadMultiple();
		unset( $roles['anonymous'] );
		unset( $roles['authenticated'] );
		
		// Add a NULL option to allow deselection of role.
		$options = array( NULL => t('Choose') );
		foreach ( $roles as $role ) {
			$options[$role->id()] = $role->label();
		}
		// Ends here

		// Non-member login failed message and role based settings
		$form['other'] = array(
			'#type' => 'details',
			'#title' => t('Message and Roles Settings'),
			'#open' => FALSE,
		);
		$form['other']['cw_non_mem_msg'] = array(
			'#type' => 'textarea',
			'#title' => t('User message due to non-subscriber login attempt '),
			'#description' => t('The message to give to users who are not in the subscription system. If blank, the user will receive no information other than normal login failure.'),
			'#default_value' => t('Your account information was not found. Please try again or click here to start a subscription.'),
		);
		$form['other']['cw_members_role'] = array(
			'#type' => 'select',
			'#title' => t('User role to assign to actively subscribed users'),
			'#description' => t('Choose which role to assign to subscribed users on successful login.'),
			'#options' => $options,
			'#default_value' => $cw_members_role['cw_members_role'],
		);
		$form['other']['cw_suspended_role'] = array(
			'#type' => 'select',
			'#title' => t('User role to assign to suspended users'),
			'#description' => t('Choose which role to assign to suspended users on login'),
			'#options' => $options,
			'#default_value' => $cw_suspended_role['cw_suspended_role'],
		);
		$form['other']['cw_inactive_role'] = array(
			'#type' => 'select',
			'#title' => t('User role to assign to inactive users'),
			'#description' => t('Choose which role to assign to inactive users.'),
			'#options' => $options,
			'#default_value' => $cw_inactive_role['cw_inactive_role'],
		);
		$form['other']['cw_cancelled_role'] = array(
			'#type' => 'select',
			'#title' => t('User role to assign to cancelled users'),
			'#description' => t('Choose which role to assign to cancelled users.'),
			'#options' => $options,
			'#default_value' => $cw_cancelled_role['cw_cancelled_role'],
		);
		// Ends here

		return $form;
	}

	/**
	* {@inheritdoc}
	* Implements hook_validate()
	*
	* @param $form
	* @param $form_state
	*/
	public function validateForm(array &$form, FormStateInterface $form_state) {
		$values = $form_state->getValues();

		// Validate pub_acronym filed
		if (!$values['cwauth_enabled']) {
			$form_state->setErrorByName('cwauth_enabled', t('Did you forget to enable C&W external authentication?'));
		}
		if (!preg_match('/^(?:ftp|https?|feed):\/\/?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?(\?WSDL)$/', $values['cw_api_uri'])) {
			$form_state->setErrorByName('cw_api_uri', t('Invalid WSDL Url, must be a complete url starting with http://... or https://'));
		}
		if (!$values['cw_pub_acronym']) {
			$form_state->setErrorByName('cw_pub_acronym', t('Pub Acronym can not be left blank'));
		}
		if (!$values['cw_username']) {
			$form_state->setErrorByName('cw_username', t('Username can not be left blank'));
		}
		if (!$values['cw_password']) {
			$form_state->setErrorByName('cw_password', t('Password can not be left blank'));
		}
		return parent::validateForm($form, $form_state);
	}

	/**
	* {@inheritdoc}
	*/
	public function submitForm(array &$form, FormStateInterface $form_state) {
		parent::submitForm($form, $form_state);

		$this->config('cwauth.settings')
		->set('cwauth_enabled', $form_state->getValues('cwauth_enabled'))
		->set('cw_api_uri', $form_state->getValues('cw_api_uri'))	
		->set('cw_pub_acronym', $form_state->getValues('cw_pub_acronym'))
		->set('cw_username', $form_state->getValues('cw_username'))
		->set('cw_password', $form_state->getValues('cw_password'))
		->set('cw_only_mem_login', $form_state->getValues('cw_only_mem_login'))
		->set('cw_non_mem_msg', $form_state->getValues('cw_non_mem_msg'))
		->set('cw_members_role', $form_state->getValues('cw_members_role'))
		->set('cw_suspended_role', $form_state->getValues('cw_suspended_role'))
		->set('cw_inactive_role', $form_state->getValues('cw_inactive_role'))
		->set('cw_cancelled_role', $form_state->getValues('cw_cancelled_role'))
		->save();
	}

	/**
	* {@inheritdoc}
	*/
	protected function getEditableConfigNames() {
		return ['cwauth.settings'];
	}
}
?>