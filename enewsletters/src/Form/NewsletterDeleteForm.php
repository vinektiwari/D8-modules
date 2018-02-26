<?php

namespace Drupal\enewsletters\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Builds the form to delete a contact category.
 */
class NewsletterDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to delete %name?', array('%name' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('enewsletters.newsletter_list');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $entity->delete();

    // Deleting newsletters from custom tables
    \Drupal::database()->delete('enewsletter')->condition('machine_name' , $entity->id())->execute();
    \Drupal::database()->delete('enewsletter_subscriber_subscription')->condition('newsletter_machine_name' , $entity->id())->execute();

    // Deleting newsletters from taxonomy vocabulary
    $nl_id = enewsletters_get_newsletter_id($entity->id());
    \Drupal::database()->delete('taxonomy_term_hierarchy')->condition('tid' , $nl_id)->execute();
    \Drupal::database()->delete('taxonomy_term_field_data')->condition('tid' , $nl_id)->execute();
    \Drupal::database()->delete('taxonomy_term_data')->condition('tid' , $nl_id)->execute();

    drupal_set_message(t('Newsletter %label has been deleted.', array('%label' => $this->entity->label())));
    $form_state->setRedirect('enewsletters.newsletter_list');
  }
}
