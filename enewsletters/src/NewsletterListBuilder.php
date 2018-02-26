<?php

namespace Drupal\enewsletters;

use Drupal\Core\Url;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;

/**
 * Defines a class to build a listing of enewsletters newsletter entities.
 *
 * @see \Drupal\enewsletters\Entity\Newsletter
 */
class NewsletterListBuilder extends ConfigEntityListBuilder {
  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = t('Newsletters name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['name'] = $this->getLabel($entity);
    return $row + parent::buildRow($entity);
  }
}
