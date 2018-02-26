<?php

namespace Drupal\advertserve_ads\Plugin\Block\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;

class AdZoneBlock extends DeriverBase {
  /**
   * @param array $base_plugin_definition
   * @return array
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // The core block integration can be disabled.
    if (\Drupal::state()->get('advertserve_ads_blocks', TRUE)) {
      // Load all of the zones.
      $zones = \Drupal::state()->get('advertserve_ads_zones', array());
      // Add a block for each zone.
      if (!empty($zones) && is_array($zones)) {
        foreach ($zones as $zone_id => $label) {
          $this->derivatives[$zone_id] = $base_plugin_definition;
          $this->derivatives[$zone_id]['admin_label'] = $this->advertserveAdsZoneLabel($zone_id);
        }
      }
    }
    return $this->derivatives;
  }

  /**
   * @param $zone_id
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   * @uses Format a standard label for the ad zones.
   */
  public function advertserveAdsZoneLabel($zone_id) {
    $zones = \Drupal::state()->get('advertserve_ads_zones', array());
    $label = '';

    if (isset($zones[$zone_id])) {
      $label = $zones[$zone_id];
      if (is_numeric($label)) {
        $label = '#' . $label;
      }
      $label = t('AdvertServe Ad: ' . $label);
    } else {
      $label = t('Unknown zone '. $zone_id);
    }
    return $label;
  }

}
