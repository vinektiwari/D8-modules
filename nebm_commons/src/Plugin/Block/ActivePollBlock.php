<?php

namespace Drupal\nebm_commons\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'ActivePollBlock' block.
 *
 * @Block(
 *  id = "active_poll_block",
 *  admin_label = @Translation("Active poll block"),
 * )
 */
class ActivePollBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['#cache']['max-age'] = 0;
    $loader = \Drupal::service('domain.negotiator');
    $current_domain = $loader->getActiveDomain();
    if ($current_domain && $current_domain->id()) {
      $activepollid = get_active_poll_id($current_domain->id());
      if ($activepollid) {
        $pid = (int)$activepollid;
        $entity_type = 'poll';
        $view_mode = 'default';
        $view_builder = \Drupal::entityTypeManager()->getViewBuilder($entity_type);
        $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
        $poll = $storage->load($pid);
        $built = $view_builder->view($poll, $view_mode);
        $output = render($built);
        $build['active_poll_block']['#markup'] = $output;
      }
      else {
        $build['active_poll_block']['#markup'] = t('There are no active polls currently.');
      }
    }
    return $build;
  }

}

/**
 * Provides recent poll for each site/domain.
 */
function get_active_poll_id($current_domain_id) {
	// Query.
	$query = \Drupal::database()->select('poll_field_data', 'pfd');
	$query->addField('pfd', 'id');
	$query->join('poll__field_poll_domain', 'fd', 'fd.entity_id = pfd.id');
	$query->condition('fd.field_poll_domain_target_id', $current_domain_id);
	$query->condition('pfd.status', 1);
	$query->orderBy('pfd.created', DESC);
	$query->range(0, 1);
	$pid = $query->execute()->fetchField();
	return $pid;
}