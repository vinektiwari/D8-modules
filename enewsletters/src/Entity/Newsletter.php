<?php

namespace Drupal\enewsletters\Entity;

use Drupal\block\Entity\Block;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\enewsletters\NewsletterInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the Newsletter entity.

 * @ingroup enewsletters_newsletter
 *
 * @ConfigEntityType(
 *   id = "enewsletters_newsletter",
 *   label = @Translation("Newsletter"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\enewsletters\NewsletterListBuilder",
 *     "views_data" = "Drupal\Core\Entity\NewsletterEntityViewsData",
 *
 *     "form" = {
 *       "add" = "Drupal\enewsletters\Form\NewsletterForm",
 *       "edit" = "Drupal\enewsletters\Form\NewsletterForm",
 *       "delete" = "Drupal\enewsletters\Form\NewsletterDeleteForm"
 *     },
 *     "access" = "Drupal\enewsletters\NewsletterAccessControlHandler",
 *   },
 *   config_prefix = "enewsletters",
 *   admin_permission = "administer enewsletters entity",
 *   fieldable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "name",
 *     "description",
 *     "format",
 *     "priority",
 *     "from_name",
 *     "from_address",
 *     "subject",
 *     "week_days",
 *     "time",
 *     "stop_sending",
 *     "edition_count",
 *     "stop_sending_on",
 *     "repeat_after",
 *     "domains",
 *   },
 *   links = {
 *     "delete-form" = "/admin/config/services/enewsletters/{enewsletters_newsletter}/delete",
 *     "edit-form" = "/admin/config/services/enewsletters/{enewsletters_newsletter}/edit",
 *   },
 *   field_ui_base_route = "enewsletters.settings",
 * )
 */

class Newsletter extends ConfigEntityBase implements NewsletterInterface {

  /**
   * The primary key.
   *
   * @var string
   */
  public $id;

  /**
   * Name of the newsletter.
   *
   * @var string
   */
  public $name = '';

  /**
   * Description of the newsletter.
   *
   * @var string
   */
  public $description = '';

  /**
   * HTML or plaintext newsletter indicator.
   *
   * @var string
   */
  public $format;

  /**
   * Priority indicator
   *
   * @var int
   */
  public $priority;

  /**
   * Name of the email author.
   *
   * @var string
   */
  public $from_name;

  /**
   * Email author address.
   *
   * @var string
   */
  public $from_address;
  
  /**
   * Configuration settings fields for newsletters
   *
   * @var string
   */
  public $week_days;
  public $time;
  public $stop_sending;
  public $edition_count;
  public $stop_sending_on;
  public $repeat_after;

  /**
   * Subject of newsletter email. May contain tokens.
   *
   * @var string
   */
  public $subject = '[enewsletters-newsletter:name]';

  /**
   * Domain for newsletter.
   */
  public $domains;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    $config = \Drupal::config('enewsletters.settings');
    $values += array(
      'format' => $config->get('enewsletters.format'),
      'priority' => $config->get('enewsletters.priority'),
      'receipt' => $config->get('enewsletters.receipt'),
      'from_name' => $config->get('enewsletters.from_name'),
      'from_address' => $config->get('enewsletters.from_address'),
    );
    parent::preCreate($storage, $values);
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    if (\Drupal::moduleHandler()->moduleExists('block')) {
      // Make sure there are no active blocks for these newsletters.
      $ids = \Drupal::entityQuery('block')
        ->condition('plugin', 'enewsletters_subscription_block')
        ->condition('settings.newsletters.*', array_keys($entities), 'IN')
        ->execute();
      if ($ids) {
        $blocks = Block::loadMultiple($ids);
        foreach ($blocks as $block) {
          $settings = $block->get('settings');
          foreach ($entities as $newsletter) {
            if (in_array($newsletter->id(), $settings['newsletters'])) {
              unset($settings['newsletters'][array_search($newsletter->id(), $settings['newsletters'])]);
            }
          }
          // If there are no enabled newsletters left, delete the block.
          if (empty($settings['newsletters'])) {
            $block->delete();
          } else {
            // otherwise, update the settings and save.
            $block->set('settings', $settings);
            $block->save();
          }
        }
      }
    }
  }
}
