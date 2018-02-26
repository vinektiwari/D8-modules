<?php

namespace Drupal\enewsletters\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\taxonomy\Entity\Term;

/**
 * Base form for category edit forms.
 */
class NewsletterForm extends EntityForm {

  /**
   * Overrides Drupal\Core\Entity\EntityForm::form().
   */
  public function form(array $form, FormStateInterface $form_state) {
    $config = $this->config('enewsletters.settings');
    $form = parent::form($form, $form_state);
    $newsletter = $this->entity;
    
    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => t('Name'),
      '#maxlength' => 255,
      '#default_value' => $newsletter->label(),
      '#description' => t("The newsletter name."),
      '#required' => TRUE,
    );
    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $newsletter->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#machine_name' => array(
        'exists' => 'enewsletters_newsletter_load',
        'source' => array('name'),
      ),
      '#disabled' => !$newsletter->isNew(),
    );
    $form['description'] = array(
      '#type' => 'textarea',
      '#title' => t('Description'),
      '#default_value' => $newsletter->description,
      '#description' => t("A description of the newsletter."),
    );
    
    // Configuration settings goes here below
    $form['overview'] = array(
      '#markup' => t('Manage configurations settings for e-newsletters'),
      '#prefix' => '<br><a><h3>',
      '#suffix' => '</h3></a>',
      '#open' => TRUE,
    );

    // Frequency settings
    $form['newsletters_frequency'] = array(
      '#type' => 'fieldset',
      '#title' => t('Frequency settings'),
    );
    $form['newsletters_frequency']['week_days'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Day of the week'),
      '#options' => enewsletters_week_days(),
      '#default_value' => $newsletter->week_days,
    );
    $timeToGet = date('Y-m-d H:i:s T',$newsletter->time)." ".date_default_timezone_get();
    $form['newsletters_frequency']['time'] = array(
      '#title' => t('Time'),
      '#type' => 'datetime',
      '#date_date_element' => 'none',
      '#date_time_element' => 'time',
      '#default_value' => new DrupalDateTime(),
    );
    $form['newsletters_frequency']['stop_sending'] = array(
      '#type' => 'radios',
      '#title' => t('Stop sending'),
      '#default_value' => $newsletter->stop_sending,
      '#options' => enewsletters_stop_options(),
    );
    $form['newsletters_frequency']['edition_count'] = array(
      '#type' => 'textfield',
      '#description' => t("The maximum number of editions which should be sent."),
      '#size' => 5,
      '#default_value' => $newsletter->edition_count,
      '#states' => array(
        'visible' => array(
          ':input[name="stop_sending"]' => array('value' => 'num_of_edition'),
        ),
        'required' => array(
          ':input[name="stop_sending"]' => array('value' => 'num_of_edition'),
        ),
      ),
    );
    $form['newsletters_frequency']['stop_sending_on'] = array(
      '#title' => t('Stop sending on'),
      '#type' => 'date',
      '#default_value' => $newsletter->stop_sending_on,
      '#date_date_element' => 'date',
      '#date_time_element' => 'none',
      '#states' => array(
        'visible' => array(
          ':input[name="stop_sending"]' => array('value' => 'on_given_date'),
        ),
        'required' => array(
          ':input[name="stop_sending"]' => array('value' => 'on_given_date'),
        ),
      ),
    );
    $form['newsletters_frequency']['repeat_after'] = array(
      '#type' => 'select',
      '#title' => t('Repeat after'),
      '#default_value' => $newsletter->repeat_after,
      '#options' => enewsletters_repeat_after(),
    );
    
    // Email settings
    $form['email'] = array(
      '#type' => 'fieldset',
      '#title' => t('Email settings'),
    );
    
    $format_options = enewsletters_format_options();
    $format = $config->get('newsletter.format');
    if (count($format_options) > 1) {
      $form['email']['format'] = array(
        '#type' => 'select',
        '#title' => t('Format'),
        '#default_value' => $newsletter->format,
        '#options' => $format_options,
      );
    }
    $form['email']['priority'] = array(
      '#type' => 'select',
      '#title' => t('Priority'),
      '#default_value' => $newsletter->priority,
      '#options' => enewsletters_get_priority(),
    );
    
    // Email sender informations
    $form['newsletters_sender_info'] = array(
      '#type' => 'fieldset',
      '#title' => t('Sender info'),
    );
    $form['newsletters_sender_info']['from_name'] = array(
      '#type' => 'textfield',
      '#title' => t('From name'),
      '#size' => 60,
      '#maxlength' => 128,
      '#default_value' => $newsletter->from_name,
    );
    $form['newsletters_sender_info']['from_address'] = array(
      '#type' => 'email',
      '#title' => t('From email address'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#default_value' => $newsletter->from_address,
    );

    // Domain informations goes here
    $form['domain_info'] = array(
      '#type' => 'fieldset',
      '#title' => t('Domain Settings'),
    );
    $form['domain_info']['domains'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Domain access'),
      '#options' => enewsletters_load_domain_options(),
      '#default_value' => $newsletter->domains,
    );

    // All action configuration goes here
    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save configuration'),
      '#weight' => 50,
    );

    if ($newsletter->id) {
      $form['actions']['delete'] = array(
        '#type' => 'submit',
        '#value' => t('Delete'),
        '#weight' => 55,
      );
    }
    return $form;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityForm::save().
   */
  public function save(array $form, FormStateInterface $form_state) {
    $newsletter = $this->entity;
    $time = strtotime($newsletter->time);
    $newsletter->set('time', $time);
    $status = $newsletter->save();

    $vocabulary_name = 'newsletter_category'; // Vocabulary machine name
    $term_name = [$newsletter->name]; // List of test terms
    foreach ($term_name as $category) {
      $term = Term::create(array(
        'parent' => array(),
        'name' => $term_name,
        'vid' => $vocabulary_name,
      ))->save();
    }
    $id = enewsletters_get_entity_type_id($newsletter->id());
    if ($id == FALSE) {
      \Drupal::database()->insert('enewsletter')->fields(['name', 'machine_name', 'status'])->values([$newsletter->label(), $newsletter->id(), '1',])->execute();
    } else {
      db_update('enewsletter')->fields(['name' => $newsletter->label()])->condition('machine_name', $newsletter->id())->execute();
    }
    
    $edit_link = \Drupal::linkGenerator()->generate($this->t('Edit'), $this->entity->urlInfo());
    if ($status == SAVED_UPDATED) {
      drupal_set_message(t('Newsletter %label has been updated.', array('%label' => $newsletter->label())));
      \Drupal::logger('enewsletters')->notice('Newsletter %label has been updated.', array('%label' => $newsletter->label(), 'link' => $edit_link));
    } else {
      drupal_set_message(t('Newsletter %label has been added.', array('%label' => $newsletter->label())));
      \Drupal::logger('enewsletters')->notice('Newsletter %label has been added.', array('%label' => $newsletter->label(), 'link' => $edit_link));
    }
    $form_state->setRedirect('enewsletters.newsletter_list');
  }
}
