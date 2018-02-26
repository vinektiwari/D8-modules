<?php

/**
* @file
* Contains \Drupal\advertserve_ads\Plugin\Block\AdvertserveAdBlock.
*/

namespace Drupal\advertserve_ads\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Advertserve Ad' block.
 *
 * @Block(
 *   id = "advertserve_ads_block",
 *   deriver = "Drupal\advertserve_ads\Plugin\Block\Derivative\AdZoneBlock"
 * )
 */
class AdvertserveAdBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $block = [];
    $vars = array(
      '#account_id' => \Drupal::state()->get('advertserve_ads_account_id'),
      '#zone' => 1,
      '#pid' => 0
    );

    if (!empty($vars['#account_id'])) {
      $block['#subject'] = '';
      $block['#content'] = \Drupal::service('renderer')->render($vars);
    }

    return $block;
  }
}
