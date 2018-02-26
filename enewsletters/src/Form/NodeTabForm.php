<?php

namespace Drupal\enewsletters\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Utility\Token;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\enewsletters\NewsletterInterface;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\Core\DrupalKernel;

/**
 * Configure enewsletters subscriptions of a user.
 */
class NodeTabForm extends FormBase {
  /**
   * The entity object.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  public $entity;

  /**
   * The newsletter.
   *
   * @var \Drupal\enewsletters\NewsletterInterface
   */
  protected $newsletter;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'newsletter_mails_node_tab';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $config = \Drupal::config('enewsletters.settings');

    $entity = \Drupal::entityTypeManager()->getStorage('enewsletters_newsletter')->loadMultiple();
    $subscriber_count = enewsletters_count_subscriptions($node->newsletter_issue->target_id);
    $status = $node->newsletter_issue->status;

    $form['#title'] = t('<em>Newsletter issue</em> @title', array('@title' => $node->getTitle()));

    // We will need the node.
    $form_state->set('node', $node);
    $nodeExists = enewsletters_edition_id($node->id());
    if ($nodeExists == TRUE) {
      $send_text = t('As you are not in the main/parent <em>Newsletter Issue</em> so you are not allow to view the edition for this content.');
      $form['not_available'] = array(
        '#markup' => $send_text,
      );
    } else {
      // Send test newsletter for three mail ID only
      $form['testmail'] = array(
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => t('Test'),
      );
      $form['testmail']['test_address'] = array(
        '#type' => 'textfield',
        '#title' => t('Test email addresses'),
        '#description' => t('A comma-separated list of email addresses to be used as test addresses. Maximum 3 email addresses will be taken.'),
        '#default_value' => \Drupal::currentUser()->getEmail(),
        '#size' => 60,
        '#maxlength' => 128,
      );
      $form['testmail']['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Send test newsletter'),
        '#name' => 'send_test',
        '#submit' => array('::submitTestMail'),
        '#validate' => array('::validateTestMails'),
      );

      // Show newsletter sending options if newsletter has not been send yet.
      // If send a notification is shown.
      // Send newsletter immediately 
      if ($subscriber_count < 1) {
        $form['send'] = array(
          '#type' => 'details',
          '#open' => TRUE,
          '#title' => t('Send'),
        );
        // Add some text to describe the send situation.
        $form['send']['count'] = array(
          '#type' => 'item',
          '#markup' => t('Newsletter mails will not send as there are no active subscriber for this newsletter type.'), 
        );
      } else {
        if ($status == NEWSLETTER_STATUS_SEND_NOT) {  
          $form['send'] = array(
            '#type' => 'details',
            '#open' => TRUE,
            '#title' => t('Send'),
          );
          // Add some text to describe the send situation.
          $form['send']['count'] = array(
            '#type' => 'item',
            '#markup' => t('Send newsletter issue to @count subscribers.', array('@count' => $subscriber_count)), 
          );

          $send_text = t('Newsletter mails will be sent immediately.');
          $form['send']['method'] = array(
            '#type' => 'item',
            '#markup' => $send_text,
          );
          if ($node->isPublished()) {
            $form['send']['send_now'] = array(
              '#type' => 'submit',
              '#button_type' => 'primary',
              '#value' => t('Send now'),
              '#submit' => array('::submitForm', '::submitSendNow'),
            );
          }
        }
      }
    }  
    return $form;
  }

  // SEND NOW mail settings goes here
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::config('enewsletters.settings');
    $values = $form_state->getValues();

    // Validate recipient handler settings.
    if (!empty($form['recipient_handler_settings'])) {
      $handler = $values['recipient_handler'];
      $handler_definitions = \Drupal::service('plugin.manager.enewsletters_recipient_handler')->getDefinitions();

      // Get the handler class.
      $handler = $handler_definitions[$handler];
      $class = $handler['class'];

      if (method_exists($class, 'settingsFormValidate')) {
        $class::settingsFormValidate($form['recipient_handler_settings'], $form_state);
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $node = $form_state->get('node');
    $parent_nid = $node->id();
    
    $newsletterId = $node->newsletter_issue->target_id;
    $nlentity = enewsletters_newsletter_load($newsletterId, $reset = FALSE);
    $body = $node->body->value;

    $node = Node::create(array(
      'type' => 'newsletter_issue',
      'title' => $node->getTitle(),
      'body' => array(
        'value' => $body,
        'format' => 'basic_html',
      ),
      'newsletter_issue' => $newsletterId,
      'uid' => ADMIN_UID,
      'langcode' => 'en',
      'status' => NEWSLETTER_NODE_PUBLISH,
    ));
    $node->save();
    $this->mailSpoolStorage($parent_nid, $node->id(), $newsletterId);
  }

  /**
   * Submit handler for sending published newsletter issue.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   */
  public function submitSendNow(array &$form, FormStateInterface $form_state) {
    global $base_url;
    
    $node = $form_state->get('node');
    $newsletterSubject = $node->getTitle();
    $bodyTag = $node->body->value;
    $newsletterId = $node->newsletter_issue->target_id;
    $nlentity = enewsletters_newsletter_load($newsletterId, $reset = FALSE);
    $format = $nlentity->format;

    // Designing body section for sending test newsletter mail
    $body = '<div>';
      $body .= '<p><center>To view this content on web page, <a href="'.$base_url.'/node/'.$node->id().'">click here</a></center></p>';
      $body .= '<h2>'.$node->getTitle().'</h2>';
      $body .= '<p>'.$bodyTag.'</p><br>';
      if ($node->hasField('field_newsletter_articles')) {
        $articles = $node->field_newsletter_articles->getValue('target_id');
        if (!empty($articles)) {
          foreach ($articles as $articleKey => $articleVal) {
            $articleId = $articleVal['target_id'];
            $articleContent[$articleKey]['title'] = enewsletters_get_title($articleId);
            $articleContent[$articleKey]['url'] = enewsletters_get_uri('node',$articleId);
            $bodyVal = Node::load($articleId)->get('body')->value;
            $articleContent[$articleKey]['trimbody'] = $this->trimText($bodyVal, 250, $strip_html = true);
          }

          $body .= '<h4>Articles : </h4>';
          foreach ($articleContent as $article) {
            $body .= '<p><a href="'.$base_url.''.$article['url'].'">'.$article['title'].'</a></p>';
            $body .= '<p>'.$article['trimbody'].'</p>';
          }
        }
      }
      if ($node->hasField('field_newsletter_events')) {
        $events = $node->field_newsletter_events->getValue('target_id');
        if (!empty($events)) {
          foreach ($events as $eventKey => $eventVal) {
            $eventId = $eventVal['target_id'];
            $eventContent[$eventKey]['title'] = enewsletters_get_title($eventId);
            $eventContent[$eventKey]['url'] = enewsletters_get_uri('node',$eventId);
            $bodyVal = Node::load($eventId)->get('body')->value;
            $eventContent[$eventKey]['trimbody'] = $this->trimText($bodyVal, TRIM_TEXT_LIMIT, $strip_html = true);
          }
          $body .= '<h4>Events : </h4>';
          foreach ($eventContent as $event) {
            $body .= '<p><a href="'.$base_url.''.$event['url'].'">'.$event['title'].'</a></p>';
            $body .= '<p>'.$event['trimbody'].'</p>';
          }
        }
      }
      if ($node->hasField('field_newsletter_polls')) {
        $polls = $node->field_newsletter_polls->getValue('target_id');
        if (!empty($polls)) {
          foreach ($polls as $pollKey => $pollVal) {
            $pollId = $pollVal['target_id'];
            $pollContent[$pollKey]['title'] = enewsletters_get_poll_title($pollId);
            $pollContent[$pollKey]['url'] = enewsletters_get_uri('poll',$pollId);
          }
          $body .= '<h4>Polls : </h4>';
          foreach ($pollContent as $poll) {
            $body .= '<p><a href="'.$base_url.''.$poll['url'].'">'.$poll['title'].'</a></p>';
          }
        }
      }
    $body .= '</div>';

    $subscriberMail = enewsletters_subscriber_load_by_entityName($newsletterId);
    $nlIncreamentCount = 1;
    foreach ($subscriberMail as $subscribers) {
      $subscribers = trim($subscribers->sub_email);
      if (!empty($subscribers)) {
        $mailManager = \Drupal::service('plugin.manager.mail');
        $langcode = 'en';
        $key = 'subscribe';
        $params = array(
          'from_name' => $nlentity->from_name,
          'from_add' => $nlentity->from_address,
          'from' => $nlentity->from_name,
          'subject' => $this->getSubject($newsletterSubject),
          'bodytext' => $body,
        );
        $send = TRUE;
        $result = $mailManager->mail('enewsletters', $key, $subscribers, $langcode, $params, NULL, $send); 
      }
      $nlIncreamentCount++;
      if ($nlIncreamentCount == NEWSLETTER_MAIL_SENT_COUNTER) 
        sleep(NUMBER_OF_SECONDS);
    }
    // Attempt to send immediatly, if configured to do so.
    drupal_set_message(t('Newsletter %title sent.', array('%title' => $node->getTitle())));
  }

  // TEST mail settings goes here
  /**
   * Validates the test address.
   */
  public function validateTestMails(array $form, FormStateInterface $form_state) {
    $test_address = $form_state->getValue('test_address');
    $test_address = trim($test_address);
    
    if (!empty($test_address)) {
      $mails = explode(',', $test_address);
      foreach ($mails as $mail) {
        $mail = trim($mail);
        if (!valid_email_address($mail)) {
          $form_state->setErrorByName('test_address', t('Invalid email address "%mail".', array('%mail' => $mail)));
        }
      }
      $form_state->set('test_addresses', $mails);
    } else {
      $form_state->setErrorByName('test_address', t('Missing test email address.'));
    }
  }

  /**
   * Submit handler for sending test mails.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   */
  public function submitTestMail(array &$form, FormStateInterface $form_state) {
    global $base_url;
    
    $node = $form_state->get('node');
    $newsletterSubject = $node->getTitle();
    $bodyTag = $node->body->value;
    $newsletterId = $node->newsletter_issue->target_id;
    $nlentity = enewsletters_newsletter_load($newsletterId, $reset = FALSE);
    $format = $nlentity->format;

    // Designing body section for sending test newsletter mail
    $body = '<div>';
      $body .= '<p><center>To view this content on web page, <a href="'.$base_url.'/node/'.$node->id().'">click here</a></center></p>';
      $body .= '<h2>'.$node->getTitle().'</h2>';
      $body .= '<p>'.$bodyTag.'</p><br>';
      if ($node->hasField('field_newsletter_articles')) {
        $articles = $node->field_newsletter_articles->getValue('target_id');
        if (!empty($articles)) {
          foreach ($articles as $articleKey => $articleVal) {
            $articleId = $articleVal['target_id'];
            $articleContent[$articleKey]['title'] = enewsletters_get_title($articleId);
            $articleContent[$articleKey]['url'] = enewsletters_get_uri('node',$articleId);
            $bodyVal = Node::load($articleId)->get('body')->value;
            $articleContent[$articleKey]['trimbody'] = $this->trimText($bodyVal, 250, $strip_html = true);
          }

          $body .= '<h4>Articles : </h4>';
          foreach ($articleContent as $article) {
            $body .= '<p><a href="'.$base_url.''.$article['url'].'">'.$article['title'].'</a></p>';
            $body .= '<p>'.$article['trimbody'].'</p>';
          }
        }
      }
      if ($node->hasField('field_newsletter_events')) {
        $events = $node->field_newsletter_events->getValue('target_id');
        if (!empty($events)) {
          foreach ($events as $eventKey => $eventVal) {
            $eventId = $eventVal['target_id'];
            $eventContent[$eventKey]['title'] = enewsletters_get_title($eventId);
            $eventContent[$eventKey]['url'] = enewsletters_get_uri('node',$eventId);
            $bodyVal = Node::load($eventId)->get('body')->value;
            $eventContent[$eventKey]['trimbody'] = $this->trimText($bodyVal, TRIM_TEXT_LIMIT, $strip_html = true);
          }
          $body .= '<h4>Events : </h4>';
          foreach ($eventContent as $event) {
            $body .= '<p><a href="'.$base_url.''.$event['url'].'">'.$event['title'].'</a></p>';
            $body .= '<p>'.$event['trimbody'].'</p>';
          }
        }
      }
      if ($node->hasField('field_newsletter_polls')) {
        $polls = $node->field_newsletter_polls->getValue('target_id');
        if (!empty($polls)) {
          foreach ($polls as $pollKey => $pollVal) {
            $pollId = $pollVal['target_id'];
            $pollContent[$pollKey]['title'] = enewsletters_get_poll_title($pollId);
            $pollContent[$pollKey]['url'] = enewsletters_get_uri('poll',$pollId);
          }
          $body .= '<h4>Polls : </h4>';
          foreach ($pollContent as $poll) {
            $body .= '<p><a href="'.$base_url.''.$poll['url'].'">'.$poll['title'].'</a></p>';
          }
        }
      }
    $body .= '</div>';

    $testMails = $form_state->get('test_addresses');
    $nlIncreamentCount = 1;
    foreach ($testMails as $addresses) {
      $addresses = trim($addresses);
      if (!empty($addresses)) {
        $mailManager = \Drupal::service('plugin.manager.mail');
        $langcode = 'en';
        $key = 'test';
        $params = array(
          'from_name' => $nlentity->from_name,
          'from_add' => $nlentity->from_address,
          'from' => $nlentity->from_name,
          'subject' => $this->getSubject($newsletterSubject),
          'bodytext' => $body,
        );
        $send = TRUE;
        $result = $mailManager->mail('enewsletters', $key, $addresses, $langcode, $params, NULL, $send); 
      }
      $nlIncreamentCount++;
      if ($nlIncreamentCount > TEST_MAIL_NUMBER) 
        break;
    }
    drupal_set_message(t('Test newsletter %title sent.', array('%title' => $node->getTitle())));
    $node->save();
  }

  /**
   * Store the parent and child node thread to the mail spool table.
   */
  public function mailSpoolStorage($parent_nid, $new_node, $nlid) {
    $user = \Drupal::currentUser();
    \Drupal::database()->insert('enewsletter_mail_spool')
      ->fields([
        'parent_node_id',
        'node_id',
        'newsletter_id',
        'issue_by',
        'status',
      ])
      ->values(array(
        $parent_nid,
        $new_node,
        $nlid,
        $user->id(),
        NEWSLETTER_NODE_PUBLISH
      ))
      ->execute();
  }

  /**
   * Checks access for the newsletter node tab.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node where the tab should be added.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   An access result object.
   */
  public function checkAccess(NodeInterface $node) {
    $account = $this->currentUser();

    if ($node->hasField('newsletter_issue') && $node->newsletter_issue->target_id != NULL) {
      return AccessResult::allowedIfHasPermission($account, 'administer newsletters')->orIf(AccessResult::allowedIfHasPermission($account, 'send newsletter'));
    }
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenContext() {
    return array(
      'newsletter' => 'node',
      'entityType' => 'enewsletters-newsletter',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSubject($mail_sub) {
    $langcode = 'en';
    $subject = \Drupal::token()->replace($mail_sub, $this->getTokenContext(), array('sanitize' => FALSE, 'langcode' => $langcode));
    // Line breaks are removed from the email subject to prevent injection of malicious data 
    // into the email header.
    $subject = str_replace(array("\r", "\n"), '', $subject);
    return $subject;
  }

  /**
   * Trimming body text upto certain length.
   *
   * @param string $input 
   *    Text to trim
   *
   * @param int $length 
   *    In characters to trim to
   *
   * @param bool $stripHtml 
   *    If html tags are to be stripped
   */
  function trimText($input, $length, $stripHtml = true) {
    //strip tags, if desired
    if ($stripHtml) {
        $input = strip_tags($input);
    }
    return (strlen($input) < $length) ? $input : substr($input, 0, strrpos(substr($input, 0, $length), ' ')) . '...';
  }
}
