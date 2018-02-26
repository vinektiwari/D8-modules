<?php

/**
 * @file
 * Contains \Drupal\enewsletters\Controller\UnSubscriberController.
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
class UnSubscriberController extends ControllerBase {
    /**
     * Returns a page content.
     *
     * @return array
     *   A simple renderable array.
     */
    public function content() {
        $request = \Drupal::request();
        if ($route = $request->attributes->get(\Symfony\Cmf\Component\Routing\RouteObjectInterface::ROUTE_OBJECT)) {
            $route->setDefault('_title', "Unsubscriber List");
        }

        $unsubscriberList = enewsletters_load_unsubscribers();
        return array(
            '#theme' => 'unsubscribers',
            '#unsubscriberlist' => $unsubscriberList,
        );
    }

    /**
     * Perform ajax cllback for subscribing back the un-subscriber.
     */
    public function ajaxSubscribe() {
        $subscriberId = \Drupal::request()->request->get('subid');
        \Drupal::database()->update('enewsletter_subscriber')->fields(['status' => '1'])->condition('id', $subscriberId)->execute();

        $message = 'Unsubscriber just now turned into subscriber.';
        $ajaxResponse = new AjaxResponse();
        $ajaxResponse->addCommand(new HtmlCommand('#result', $message));
        return $ajaxResponse;
    }

    /**
     * Perform ajax cllback for deleting the un-subscriber.
     */
    public function ajaxDelete() {
        $subscriberId = \Drupal::request()->request->get('subid');
        \Drupal::database()->delete('enewsletter_subscriber')
         ->condition('id' , $subscriberId)
         ->execute();

        $message = 'Unsubscriber deleted successfully.';
        $ajaxResponse = new AjaxResponse();
        $ajaxResponse->addCommand(new HtmlCommand('#result', $message));
        return $ajaxResponse;
    }
}
