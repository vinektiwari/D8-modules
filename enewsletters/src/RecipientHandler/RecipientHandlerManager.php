<?php

/**
 * Contains \Drupal\enewsletters\RecipientHandler\RecipientHandlerManager.
 */

namespace Drupal\enewsletters\RecipientHandler;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides an recipient handler plugin manager.
 *
 * @see \Drupal\enewsletters\RecipientHandler\Annotations\RecipientHandler
 * @see \Drupal\enewsletters\RecipientHandler\RecipientHandlerInterface
 * @see plugin_api
 */
class RecipientHandlerManager extends DefaultPluginManager {

  /**
   * Constructs a RecipientHandlerManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/enewsletters/RecipientHandler', $namespaces, $module_handler, 'Drupal\enewsletters\RecipientHandler\RecipientHandlerInterface', 'Drupal\enewsletters\RecipientHandler\Annotation\RecipientHandler');
    $this->alterInfo('enewsletters_recipient_handler_info');
    $this->setCacheBackend($cache_backend, 'enewsletters_recipient_handler_info_plugins');
  }

  /**
   * Returns the array of recipient handler labels.
   * @todo documentation
   */
  public function getOptions() {
    $handlers = $this->getDefinitions();

    $allowed_values = array();
    foreach ($handlers as $handler => $settings) {
      $allowed_values[$handler] = Xss::filter($settings['title']);
    }
    return $allowed_values;
  }
}
