<?php
/**
 * @file
 * Contains \Drupal\admin_registration\Form\AdminRegistrationForm.
 */

namespace Drupal\admin_registration\Form;

use Drupal\username_check\Controller;
use Drupal\user;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity;
use Drupal\Core\Session;

class AdminRegistrationForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'admin_registration_form';
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param $adminUserId
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state, $adminUserId = NULL) {
    if ($adminUserId) {
      $adminUser = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->load($adminUserId);
      $form['account']['uid'] = $adminUserId;
      $domainAdmin = $this->domainAdmin($adminUser->get('field_domain_access')
        ->getValue());
    } else {
      $form['#validate'][] = 'password_character_limit';
    }
    $config = \Drupal::config('username_check.settings');
    $user = $this->currentUser();
    $admin = $user->hasPermission('administer users');
    $mailmode = $config->get('username_check_mail_mode', 'auto');
    $delay = $config->get('username_check_delay');
    $form['account']['name']['#access'] = FALSE;
    $random = new \Drupal\Component\Utility\Random();
    $form['account']['name']['#default_value'] = $random->name();
    $form['account']['mail'] = array(
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#description' => $this->t('A valid email address. All emails from the system will be sent to this address. The email address is not made public and will only be used if you wish to receive a new password or wish to receive certain news or notifications by email.'),
      '#required' => TRUE,
      '#default_value' => isset($adminUser->mail->value) ? $adminUser->mail->value : ''
    );
    if ($mailmode != 'off') {
      $form['#attached']['library'] = array('username_check/username_check');
      $form['#attached']['drupalSettings']['usermailCheck']['ajaxUrl'] = '/username_check/isuniquemail';
      $form['#attached']['drupalSettings']['usermailCheck']['delay'] = $delay;
      $form['account']['mail']['#field_suffix'] = '<span id="mail-check-informer">&nbsp;</span>';
      $form['account']['mail']['#suffix'] = '<div id="mail-check-message"></div>';
    }

    $form['account']['pass'] = [
      '#type' => 'password_confirm',
      '#size' => 25,
      '#description' => isset($adminUserId) ? $this->t('To change the current user password, enter the new password in both fields.') : $this->t('Provide a password for the new account in both fields.'),
      '#required' => isset($adminUserId) ? FALSE : TRUE,
    ];
    $form['account']['status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Status'),
      '#default_value' => isset($adminUser->status->value) ? $adminUser->status->value : 1,
      '#options' => [$this->t('Blocked'), $this->t('Active')],
      '#access' => $admin,
    ];
    $form['account']['roles'] = [
      '#type' => 'hidden',
      '#title' => $this->t('Roles'),
      '#value' => 'Site Administrator'
    ];
    $form['account']['field_first_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('First Name : '),
      '#default_value' => isset($adminUser->field_first_name->value) ? $adminUser->field_first_name->value : '',
      '#required' => TRUE
    );
    $form['account']['field_last_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Last Name : '),
      '#default_value' => isset($adminUser->field_last_name->value) ? $adminUser->field_last_name->value : '',
      '#required' => TRUE
    );
    $form['account']['field_job_title'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Job Title : '),
      '#default_value' => isset($adminUser->field_job_title->value) ? $adminUser->field_job_title->value : '',
      '#required' => TRUE
    );

    //get the list of domains dynamically
    $domainLoader = \Drupal::service('domain.loader');
    $domains = $domainLoader->loadOptionsList();
    $form['account']['field_domain_access'] = array(
      '#type' => 'checkboxes',
      '#title' => 'Domain Access',
      '#description' => 'Select the affiliate domain(s) for this user',
      '#required' => TRUE,
      '#options' => $domains,
      '#default_value' => isset($domainAdmin) ? array_keys($domainAdmin) : '',
      '#disabled' => $admin ? false : true
    );
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    );
    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form['account']['uid']) {
      $password = $form_state->getValue('pass');
      $adminEntity = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->load($form['account']['uid']);
      $adminEntity->setEmail($form_state->getValue('mail'));
      $adminEntity->setUsername($form_state->getValue('mail'));
      $adminEntity->setPassword(!empty($password) ? $password : $adminEntity->getPassword());
      $adminEntity->set('field_first_name', $form_state->getValue('field_first_name'));
      $adminEntity->set('field_last_name', $form_state->getValue('field_last_name'));
      $adminEntity->set('status', $form_state->getValue('status'));
      $adminEntity->set('field_job_title', $form_state->getValue('field_job_title'));
      $adminEntity->set('roles', 'site_administrator');
      $adminEntity->set('field_domain_access', array_values(array_filter($form_state->getValue('field_domain_access'))));
      $adminEntity->save();
      drupal_set_message($this->t('The changes have been saved.'));
    } else {
      $adminFormValues = array(
        'name' => $form_state->getValue('mail'),
        'mail' => $form_state->getValue('mail'),
        'status' => $form_state->getValue('status'),
        'field_first_name' => $form_state->getValue('field_first_name'),
        'field_last_name' => $form_state->getValue('field_last_name'),
        'field_job_title' => $form_state->getValue('field_job_title'),
        'roles' => 'site_administrator',
        'pass' => $form_state->getValue('pass'),
        'notify' => 1,
        'field_domain_access' => array_values(array_filter($form_state->getValue('field_domain_access')))
      );
      $hostname = $this->fetchHostname($form_state);
      $adminForm = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->create($adminFormValues);
      $adminForm->save();
      $resetUrl = $this->userPassResetUrl($adminForm, $hostname);
      if ($this->sendAdminCreateMail($resetUrl, $form_state->getValue('mail'))) {
        drupal_set_message($this->t('A welcome message with further instructions has been emailed to the new user <a href=":url">%name</a>.', [
          ':url' => $adminForm->url(),
          '%name' => $adminForm->getUsername()
        ]));
      } else {
        drupal_set_message($this->t('Error occurred while creating new user'));
      }
    }
  }

  /**
   * Get the domain names which are assigned to the admin
   * @param $options
   * @return mixed
   */
  public function domainAdmin($options) {
    foreach ($options as $domains) {
      foreach ($domains as $domainKey => $domainValue) {
        $adminDomain[$domainValue] = $domainValue;
      }
    }
    return $adminDomain;
  }

  /**
   * @param $account
   * @param $hostname
   * @param array $options
   * @return \Drupal\Core\GeneratedUrl|string
   */
  public function userPassResetUrl($account, $hostname, $options = []) {
    $timestamp = REQUEST_TIME;
    $langcode = isset($options['langcode']) ? $options['langcode'] : $account->getPreferredLangcode();
    return \Drupal::url('site_admin.reset',
      [
        'uid' => $account->id(),
        'timestamp' => $timestamp,
        'hash' => user_pass_rehash($account, $timestamp),
      ],
      [
        'absolute' => TRUE,
        'language' => \Drupal::languageManager()->getLanguage($langcode),
        'base_url' => $hostname
      ]
    );
  }

  /**
   * @param FormStateInterface $form_state
   * @return mixed
   */
  public function fetchHostname(FormStateInterface $form_state) {
    $loader = \Drupal::service('domain.loader');
    $siteUrl = array_values(array_filter($form_state->getValue('field_domain_access')));
    $siteUrl = $siteUrl[0];
    $domainAccess = $loader->load($siteUrl);
    $hostUrl = $domainAccess->get('hostname');
    $hostObject = $loader->loadByHostname($hostUrl);
    return $hostObject->get('path');
  }

  /**
   * @param $resetUrl
   * @param $adminEmail
   * @return bool
   */
  public function sendAdminCreateMail($resetUrl, $adminEmail) {
    // Get the custom site notification email to use as the from email address
    // if it has been set.
    $site_mail = \Drupal::config('system.site')->get('mail_notification');
    $params = array(
      'url' => $resetUrl,
      'email' => $adminEmail
    );
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    if(\Drupal::service('plugin.manager.mail')->mail('admin_registration', 'admin_reset', $adminEmail, $langcode, $params, $site_mail)){
      return true;
    }
    return false;
  }

}
