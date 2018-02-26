<?php

namespace Drupal\enewsletters\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Removes fields and data used by Simplenews.
 */
class PrepareUninstallForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'enewsletters_admin_settings_prepare_uninstall';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['newsletter'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Prepare uninstall'),
      '#description' => $this->t('When clicked all Enewsletters data (content, fields) will be removed.'),
    );
    $form['newsletter']['prepare_uninstall'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Delete Enewsletters data'),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch = [
      'title' => t('Deleting newsletters fields'),
      'operations' => [
        [
          [__CLASS__, 'removeFields'], [],
        ],
        [
          [__CLASS__, 'purgeFieldData'], [],
        ],
      ],
      'progress_message' => static::t('Deleting Enewsletters data... Completed @percentage% (@current of @total).'),
    ];
    batch_set($batch);
    drupal_set_message($this->t('Enewsletters data has been deleted.'));
  }

  /**
   * Removes Enewsletters fields.
   */
  public static function removeFields() {
    $newsletter_fields_ids = \Drupal::entityQuery('field_config')->condition('field_type', 'newsletter_', 'STARTS_WITH')->execute();
    $newsletter_fields = \Drupal::entityManager()->getStorage('field_config')->loadMultiple($newsletter_fields_ids);
    $field_config_storage = \Drupal::entityManager()->getStorage('field_config');
    $field_config_storage->delete($newsletter_fields);
  }

  /**
   * Purges a field data.
   */
  public static function purgeFieldData() {
    do {
      field_purge_batch(1000);
      $properties = array(
        'deleted' => TRUE,
        'include_deleted' => TRUE,
      );
      $fields = entity_load_multiple_by_properties('field_config', $properties);
    } while ($fields);
  }
}
