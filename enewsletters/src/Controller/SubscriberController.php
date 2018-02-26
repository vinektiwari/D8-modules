<?php

/**
 * @file
 * Contains \Drupal\enewsletters\Controller\SubscriberController.
 */

namespace Drupal\enewsletters\Controller;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\enewsletters\SubscriberInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides route responses for the Enewsletters module.
 */
class SubscriberController extends ControllerBase {
    /**
     * Returns a page content.
     *
     * @return array
     *   A simple renderable array.
     */
    public function content() {
        $request = \Drupal::request();
        if ($route = $request->attributes->get(\Symfony\Cmf\Component\Routing\RouteObjectInterface::ROUTE_OBJECT)) {
            $route->setDefault('_title', "Subscriber List");
        }

        $subscriberList = enewsletters_load_subscribers();
        return array(
            '#theme' => 'subscribers',
            '#subscriberlist' => $subscriberList
        );
    }

    /**
     * Perform ajax cllback for unsubscribing the subscriber.
     */
    public function ajaxUnsubscribe() {
        $subscriberId = \Drupal::request()->request->get('subid');
        \Drupal::database()->update('enewsletter_subscriber')->fields(['status' => '0'])->condition('id', $subscriberId)->execute();
        \Drupal::database()->delete('enewsletter_subscriber_subscription')->condition('sub_id', $subscriberId)->execute();

        $message = 'Subscriber just now turned into un-subscriber.';
        $ajaxResponse = new AjaxResponse();
        $ajaxResponse->addCommand(new HtmlCommand('#result', $message));
        return $ajaxResponse;
    }
}
