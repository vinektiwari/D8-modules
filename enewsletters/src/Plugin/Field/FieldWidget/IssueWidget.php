<?php

namespace Drupal\enewsletters\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsButtonsWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\enewsletters\IssueWidgetInterface;

/**
 * Plugin implementation of the 'newsletter_subscription_select' widget.
 *
 * @FieldWidget(
 *   id = "newsletter_issue_widget",
 *   label = @Translation("Select list"),
 *   field_types = {
 *     "newsletter_issue"
 *   },
 *   multiple_values = FALSE
 * )
 */

class IssueWidget extends OptionsButtonsWidget implements IssueWidgetInterface {

  /**
   * IDs of the newsletters available for selection.
   *
   * @var string[]
   */
  protected $newsletterIds;

  /**
   * @var bool
   */
  protected $hidden;

  /**
   * {@inheritdoc}
   */
  public function setAvailableNewsletterIds(array $newsletter_ids) {
    $this->newsletterIds = $newsletter_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function setHidden($set = TRUE) {
    $this->hidden = (bool) $set;
  }

  /**
   * {@inheritdoc}
   */
  public function isHidden() {
    return $this->hidden;
  }

  /**
   * {@inheritdoc}
   */
  protected function getOptions(FieldableEntityInterface $entity) {
    $options = parent::getOptions($entity);
    if (isset($this->newsletterIds)) {
      $options = array_intersect_key($options, array_flip($this->newsletterIds));
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSelectedOptions(FieldItemListInterface $items, $delta = 0) {
    // Copy parent behavior but also check the status property.
    $flat_options = OptGroup::flattenOptions($this->getOptions($items->getEntity()));
    $selected_options = array();
    foreach ($items as $item) {
      $value = $item->{$this->column};
      // Keep the value if it actually is in the list of options (needs to be
      // checked against the flat list).
      if ($item->status == NEWSLETTER_SUBSCRIPTION_STATUS_SUBSCRIBED && isset($flat_options[$value])) {
        $selected_options[] = $value;
      }
    }
    return $selected_options;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    if ($this->isHidden()) {
      // "Hide" the element with #type => 'value' and a structure like a normal
      // element.
      foreach ($this->newsletterIds as $newsletter_id) {
        $element[] = array(
          'target_id' => array(
            '#type' => 'value',
            '#value' => $newsletter_id,
          ),
        );
      }
    } else {
      $element = parent::formElement($items, $delta, $element, $form, $form_state);
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractNewsletterIds($form_state_value, $selected = TRUE) {
    $selected_ids = array_map(function($item) {
      return $item['target_id'];
    }, $form_state_value);
    return $selected ? $selected_ids : array_diff($this->newsletterIds, $selected_ids);
  }
}
