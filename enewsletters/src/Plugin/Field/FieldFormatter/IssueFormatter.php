<?php

namespace Drupal\enewsletters\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;

/**
 * Formatter that displays a newsletter subscription with the status.
 *
 * @FieldFormatter(
 *   id = "newsletter_issue_formatter",
 *   label = @Translation("Select list"),
 *   field_types = {
 *     "newsletter_issue"
 *   }
 * )
 */
class IssueFormatter extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = array();

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      $label = $entity->label();

      // Do not explicitly display the status for confirmed subscriptions.
      $output = $label;

      // Add status label for the unconfirmed subscriptions.
      if ($items[$delta]->status == NEWSLETTER_SUBSCRIPTION_STATUS_UNCONFIRMED) {
        $output = $this->t('@label (Unconfirmed)', array('@label' => $label));
      }

      // Add status label for the unsubscribed subscriptions.
      if ($items[$delta]->status == NEWSLETTER_SUBSCRIPTION_STATUS_UNSUBSCRIBED) {
        $output = $this->t('@label (Unsubscribed)', array('@label' => $label));
      }

      // Add the label.
      $elements[$delta]['#markup'] = $output;
    }
    return $elements;
  }
}
