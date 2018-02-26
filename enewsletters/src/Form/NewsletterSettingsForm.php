<?php

namespace Drupal\enewsletters\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure enewsletters newsletter settings.
 */
class NewsletterSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'enewsletters_admin_settings_newsletter';
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
    $form['enewsletters_default_options'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Default newsletter options'),
      '#collapsible' => FALSE,
      '#description' => $this->t('These options will be the defaults for new newsletters, but can be overridden in the newsletter editing form.'),
    );
    $links = array(':mime_mail_url' => 'http://drupal.org/project/mimemail', ':html_url' => 'http://drupal.org/project/htmlmail');
    $description = $this->t('Default newsletter format. Install <a href=":mime_mail_url">Mime Mail</a> module or <a href=":html_url">HTML Mail</a> module to send newsletters in HTML format.', $links);
    $form['enewsletters_default_options']['enewsletters_format'] = array(
      '#type' => 'select',
      '#title' => $this->t('Format'),
      '#options' => enewsletters_format_options(),
      '#description' => $description,
      '#default_value' => $config->get('newsletter.format'),
    );
    // @todo Do we need these master defaults for 'priority' and 'receipt'?
    $form['enewsletters_default_options']['enewsletters_priority'] = array(
      '#type' => 'select',
      '#title' => $this->t('Priority'),
      '#options' => enewsletters_get_priority(),
      '#description' => $this->t('Note that email priority is ignored by a lot of email programs.'),
      '#default_value' => $config->get('newsletter.priority'),
    );
    $form['enewsletters_sender_info'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Sender information'),
      '#collapsible' => FALSE,
      '#description' => $this->t('Default sender address that will only be used for confirmation emails. You can specify sender information for each enewsletters separately on the enewsletters\'s settings page.'),
    );
    $form['enewsletters_sender_info']['enewsletters_from_name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('From name'),
      '#size' => 60,
      '#maxlength' => 128,
      '#default_value' => $config->get('enewsletters.from_name'),
    );
    $form['enewsletters_sender_info']['enewsletters_from_address'] = array(
      '#type' => 'email',
      '#title' => $this->t('From email address'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#default_value' => $config->get('enewsletters.from_address'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('enewsletters.settings')
      ->set('enewsletters.format', $form_state->getValue('enewsletters_format'))
      ->set('newsletter.priority', $form_state->getValue('enewsletters_priority'))
      ->set('enewsletters.receipt', $form_state->getValue('enewsletters_receipt'))
      ->set('enewsletters.from_name', $form_state->getValue('enewsletters_from_name'))
      ->set('enewsletters.from_address', $form_state->getValue('enewsletters_from_address'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
