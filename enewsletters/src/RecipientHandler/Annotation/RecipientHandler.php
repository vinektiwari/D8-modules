<?php

namespace Drupal\enewsletters\RecipientHandler\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an recipient handler annotation object.
 *
 * Plugin Namespace: Plugin\RecipientHandler
 *
 *
 * @see \Drupal\enewsletters\RecipientHandler\RecipientHandlerManager
 * @see \Drupal\enewsletters\RecipientHandler\RecipientHandlerInterface
 * @see plugin_api
 *
 * @Annotation
 */
class RecipientHandler extends Plugin {

  /**
   * The archiver plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the recipient handler plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $title;

  /**
   * The description of the recipient handler plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

}
