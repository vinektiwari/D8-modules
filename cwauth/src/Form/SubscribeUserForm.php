<?php
/**
 * @file
 * Contains \Drupal\cwauth\Form\SubscribeUserForm.
 */

namespace Drupal\cwauth\Form;

use Drupal\user\Entity\User;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * C&Wauth settings form.
 */
class SubscribeUserForm extends ConfigFormBase {
	/**
	* {@inheritdoc}
	*/
	public function getFormId() {
		return 'cwauth_subscrib_users_form';
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
		$config = $this->config('cwauth.subscribe_users');
		$form = parent::buildForm($form, $form_state);

		$form['auth'] = array(
			'#title' => t( 'Cambey & West Auth Config Settings' ),
			'#type' => 'details',
			'#open' => TRUE, // Controls the HTML5 'open' attribute. Defaults to FALSE.
		);
		$form['auth']['cw_pub_acronym'] = array(
			'#title' => t('Pub Acronym'),
			'#description' => t('The three digit identifier for your C&W publishing account.'),
			'#type' => 'textfield',
			'#required' => TRUE,
			'#default_value' => $cw_pub_acronym['cw_pub_acronym'],
		);
		$form['auth']['cw_username'] = array(
			'#title' => t('Authorized Username'),
			'#description' => t('The authorized API username for the C&W web service.'),
			'#type' => 'textfield',
			'#required' => TRUE,
			'#default_value' => $cw_username['cw_username'],
		);
		$form['auth']['cw_password'] = array(
			'#title' => t('Authorized Password'),
			'#description' => t('The authorized API password for the C&W web service.'),
			'#type' => 'textfield',
			'#required' => TRUE,
			'#default_value' => $cw_password['cw_password'],
		);
		return $form;
	}

	
	/**
	* {@inheritdoc}
	*/
	public function submitForm(array &$form, FormStateInterface $form_state) {
		parent::submitForm($form, $form_state);

		$this->config('cwauth.settings')
		->set('cwauth_enabled', $form_state->getValues('cwauth_enabled'))
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
		return ['cwauth.subscribe_users'];
	}
}
?>