<?php
/**
 * @file
 * Contains \Drupal\enewsletters\Plugin\Block
 */

namespace Drupal\enewsletters\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

 /**
 * Provides a 'Enewsletters subscription' Block.
 *
 * @Block(
 *   id = "enewsletters_subscription_block",
 *   admin_label = @Translation("Enewsletters subscription"),
 *   category = @Translation("Enewsletters subscription"),
 * )
 */

class NewsletterSubscriptionBlock extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {
    // Render subscription block here in block layout
      $form = \Drupal::formBuilder()->getForm('Drupal\enewsletters\Form\NewsletterSubscriptionForm');
      return $form;
  }
}
