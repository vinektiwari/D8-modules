<?php

namespace Drupal\enewsletters\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\enewsletters\Spool\SpoolStorageInterface;

/**
 * Configure enewsletters newsletter settings.
 */
class MailSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'enewsletters_admin_settings_mail';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['enewsletters.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('enewsletters.settings');
    $form['enewsletters_mail_backend']['enewsletters_use_cron'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Use cron to send newsletters'),
      '#default_value' => $config->get('mail.use_cron'),
      '#description' => $this->t('When checked cron will be used to send newsletters (recommended). Test newsletters and confirmation emails will be sent immediately. Leave unchecked for testing purposes.'),
    );

    $throttleVal = array(1, 10, 20, 50, 100, 200, 500, 1000, 2000, 5000, 10000, 20000);
    $throttle = array_combine($throttleVal, $throttleVal);
    $throttle[SpoolStorageInterface::UNLIMITED] = $this->t('Unlimited');
    if (function_exists('getrusage')) {
      $description_extra = '<br />' . $this->t('Cron execution must not exceed the PHP maximum execution time of %max seconds. You find the time spend to send emails in the <a href="/admin/reports/dblog">Recent log entries</a>.', array('%max' => ini_get('max_execution_time')));
    } else {
      $description_extra = '<br />' . $this->t('Cron execution must not exceed the PHP maximum execution time of %max seconds.', array('%max' => ini_get('max_execution_time')));
    }
    $form['enewsletters_mail_backend']['enewsletters_throttle'] = array(
      '#type' => 'select',
      '#title' => $this->t('Cron throttle'),
      '#options' => $throttle,
      '#default_value' => $config->get('mail.throttle'),
      '#description' => $this->t('Sets the numbers of newsletters sent per cron run. Failure to send will also be counted.') . $description_extra,
    );
    $form['enewsletters_mail_backend']['enewsletters_spool_expire'] = array(
      '#type' => 'select',
      '#title' => $this->t('Mail spool expiration'),
      '#options' => array(
        0 => $this->t('Immediate'),
        1 => \Drupal::translation()->formatPlural(1, '1 day', '@count days'),
        7 => \Drupal::translation()->formatPlural(1, '1 week', '@count weeks'),
        14 => \Drupal::translation()->formatPlural(2, '1 week', '@count weeks'),
      ),
      '#default_value' => $config->get('mail.spool_expire'),
      '#description' => $this->t('Newsletter mails are spooled. How long must messages be retained in the spool after successful sending. Keeping the message in the spool allows mail statistics (which is not yet implemented). If cron is not used, immediate expiration is advised.'),
    );
    $form['enewsletters_mail_backend']['enewsletters_debug'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Log emails'),
      '#default_value' => $config->get('mail.debug'),
      '#description' => $this->t('When checked all outgoing enewsletters emails are logged in the system log. A logged email does not guarantee that it is send or will be delivered. It only indicates that a message is sent to the PHP mail() function. No status information is available of delivery by the PHP mail() function.'),
    );
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('enewsletters.settings')
      ->set('mail.use_cron', $form_state->getValue('enewsletters_use_cron'))
      ->set('mail.source_cache', $form_state->getValue('enewsletters_source_cache'))
      ->set('mail.throttle', $form_state->getValue('enewsletters_throttle'))
      ->set('mail.spool_expire', $form_state->getValue('enewsletters_spool_expire'))
      ->set('mail.debug', $form_state->getValue('enewsletters_debug'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
