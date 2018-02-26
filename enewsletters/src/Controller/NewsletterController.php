<?php

/**
 * @file
 * Contains \Drupal\enewsletters\Controller\NewsletterController.
 */

namespace Drupal\enewsletters\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Provides route responses for the Enewsletters module.
 */
class NewsletterController extends ControllerBase {
    /**
     * Returns a page content.
     *
     * @return array
     *   A simple renderable array.
     */
    public function content($id) {
        $id;
        $bodyContent = "Vinek";
        return array(
            '#theme' => 'node__newsletter_issue',
            '#bodycontent' => $bodyContent
        );
    }
}
