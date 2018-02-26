<?php

/**
 * @file
 * Contains \Drupal\advertserve_ads\Form\AdvertserveAdsForm.
 */

namespace Drupal\advertserve_ads\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class AdvertserveAdsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'advertserve-ads';
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['account_id'] = array(
      '#title' => t('Account ID'),
      '#description' => t('This is used as the subdomain to load adds, e.g. if the ads are loaded from http://example.advertserve.com/ then the account ID is "example".'),
      '#type' => 'textfield',
      '#default_value' => \Drupal::state()->get('advertserve_ads_account_id'),
      '#field_prefix' => 'http://',
      '#field_suffix' => '.advertserve.com/',
      '#required' => TRUE,
    );

    if (empty($form['account_id']['#default_value'])) {
      drupal_set_message(t('The account ID must be set before any zones can be added.'), 'warning', FALSE);
    } else {
      $form['zones'] = array(
        '#type' => 'details',
        '#title' => t('Ad zones'),
        '#open' => TRUE,
        '#description' => t('Add a new zone by filling in the blank row(s). Remove an ad zone by clearing out its zone ID value. Ad zone IDs must be numerical.'),
      );
      $form['zones']['existing'] = array(
        // Theme this part of the form as a table.
        '#header' => array(
          t('Ad zone ID'),
          t('Label for this ad'),
        ),
        '#theme' => 'advertserve_ads_settings_form_table',
        // Pass header information to the theme function.
        '#tree' => TRUE,
        'rows' => array(
          '#tree' => TRUE,
        ),
      );

      $zones = \Drupal::state()->get('advertserve_ads_zones', array());
      ksort($zones);
      // Display each ad zone.
      if (!empty($zones)) {
        // Add fields for each zone.
        foreach ($zones as $zone_id => $label) {
          $form['zones']['existing'][$zone_id] = array(
            'id' => array(
              '#type' => 'textfield',
              '#default_value' => $zone_id,
            ),
            'label' => array(
              '#type' => 'textfield',
              '#default_value' => $label,
            ),
          );
          $form['zones']['existing']['rows'][] = $form['zones']['existing'][$zone_id];
        }
      }

      // Add fields for adding new items.
      $form['zones']['existing']['new1'] = array(
        'id' => array(
          '#type' => 'textfield',
          '#default_value' => '',
        ),
        'label' => array(
          '#type' => 'textfield',
          '#default_value' => '',
        ),
      );
      $form['zones']['existing']['rows'][] = $form['zones']['existing']['new1'];
      $form['zones']['existing']['new2'] = array(
        'id' => array(
          '#type' => 'textfield',
          '#default_value' => '',
        ),
        'label' => array(
          '#type' => 'textfield',
          '#default_value' => '',
        ),
      );
      $form['zones']['existing']['rows'][] = $form['zones']['existing']['new2'];

      $form['advanced'] = array(
        '#type' => 'details',
        '#title' => t('Advanced options'),
        '#open' => FALSE
      );
      $form['advanced']['blocks'] = array(
        '#title' => t('Enable ad blocks'),
        '#type' => 'checkbox',
        '#default_value' => \Drupal::state()->get('advertserve_ads_blocks', TRUE),
        '#description' => t('Uncheck to disable ad display using the core Block system. Should only be disabled when using
          <a href="https://drupal.org/project/panels">Panels</a>,
          <a href="https://drupal.org/project/panelizer">Panelizer</a> or
          <a href="https://drupal.org/project/panels_everywhere">Panels Everywhere</a> modules to position the ads.'
        ),
      );
    }

    // Submit button.
    $form['actions'] = array();
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save the account ID.
    \Drupal::state()->set('advertserve_ads_account_id', $form_state->getValue('account_id'));

    $advertAdBlocks = $form_state->getValue('blocks');

    // Update the 'blocks' option.
    \Drupal::state()->set('advertserve_ads_blocks', !empty($advertAdBlocks));

    $zones = array();
    $existingRows = $form_state->getValue(['existing', 'rows']);
    // Save the ad zone data.
    if (!empty($existingRows)) {
      foreach ($existingRows as $key => $zone) {
        $zone_id = $zone['id'];
        $zone_label = $zone['label'];

        // Only save zones that have an ID, i.e. allow records to be removed by
        // blanking out the ad zone.
        if (!empty($zone_id)) {
          // Default label.
          if (empty($zone_label)) {
            $zone_label = $zone_id;
          }
          $zones[$zone_id] = $zone_label;
        }
      }

      // Sort the ad zones so they're listed by zone ID.
      if (!empty($zones)) {
        ksort($zones);
      }
    }

    // Save whatever zones exist.
    \Drupal::state()->set('advertserve_ads_zones', $zones);
  }
}
