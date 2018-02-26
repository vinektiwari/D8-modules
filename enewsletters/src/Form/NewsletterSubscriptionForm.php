<?php
/**
 * @file
 * Contains \Drupal\enewsletters\Form\NewsletterSubscriptionForm
 */
namespace Drupal\enewsletters\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class NewsletterSubscriptionForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'newsletter_subscription_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $newsletters = enewsletters_newsletter_get_all();
    foreach ($newsletters as $newsletter) {
      $options[$newsletter->id] = $newsletter->name;
    }
    $loggedInUser = check_logged_in_user();
    $userEmail = \Drupal::currentUser()->getEmail();
    $subscriber = enewsletters_subscriber_details($userEmail);
    $checkedBoxes = enewsletters_machine_name_by_subscriber_id($subscriber->id);
    foreach ($checkedBoxes as $newsletterName => $newsletterMachineName) {
      $checkedValues[] = $checkedBoxes[$newsletterName]->newsletter_machine_name;
    }
    switch($loggedInUser) {
      case ANONYMOUS: 
        $form['manage_newsletter'] = array(
          '#markup' => empty($options) ? t('No newsletter available') : t('Manage your newsletter subscriptions'),
          '#prefix' => '<b>',
          '#suffix' => '</b>',
        );
        $form['newsletters'] = array(
          '#type' => 'checkboxes',
          '#options' => $options,
          '#required' => TRUE,
        );
        $form['newsletter_subscription'] = array(
          '#markup' => empty($options) ? '' : t('Check the newsletters you want to subscribe to. Uncheck the ones you want to unsubscribe from.'), 
          '#prefix' => '<p>',
          '#suffix' => '</p>',
        );
        $form['stay_informed'] = array(
          '#markup' => empty($options) ? '' : t('Stay informed - subscribe to our newsletters. '),
        );
        $form['sub_first_name'] = array(
          '#type' => 'textfield',
          '#title' => t('First Name:'),
        );
        $form['sub_last_name'] = array(
          '#type' => 'textfield',
          '#title' => t('Last Name:'),
        );
        $form['sub_mail'] = array(
          '#type' => 'email',
          '#title' => t('Email ID:'),
          '#required' => TRUE,
        );
        $form['actions']['submit'] =  array(
          '#type' => 'submit',
          '#value' => t('Subscribe'),
          '#disabled' => empty($options) ? TRUE : FALSE,    
        );
        break;
      case LOGGED_IN:
        $form['subscriptions'] = array(
          '#markup' => empty($options) ? t('No newsletter available') : t(' Subscriptions for '.$userEmail),
          '#prefix' => '<b>',
          '#suffix' => '</b>',
        );
        $form['newsletters'] = array(
          '#type' => 'checkboxes',
          '#options' => $options,
          '#required' => TRUE,
          '#default_value' => $checkedValues,
        );
        $form['newsletter_subscription'] = array(
          '#markup' => empty($options) ? '' : t('Check the newsletters you want to subscribe to. Uncheck the ones you want to unsubscribe from.'), 
          '#prefix' => '<p>',
          '#suffix' => '</p>',
        );
        $form['stay_informed'] = array(
          '#markup' => empty($options) ? '' : t('Stay informed - subscribe to our newsletters. '),
        );    
        $form['sub_first_name'] = array(
          '#type' => 'textfield',
          '#title' => t('First Name:'),
          '#default_value' => $subscriber->sub_fname,
        );
        $form['sub_last_name'] = array(
          '#type' => 'textfield',
          '#title' => t('Last Name:'),
          '#default_value' => $subscriber->sub_lname,
        );
        if (!sizeof($checkedValues)) {
          $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => t('Subscribe'),
            '#submit' => array('::submitSubscibeForm'),
            '#disabled' => empty($options) ? TRUE : FALSE,
          );
        } else {
          $form['actions']['update'] = array(
            '#type' => 'submit',
            '#value' => t('Update'),
            '#submit' => array('::submitUpdateForm'),
          );
          $form['actions']['unsubscribe'] = array(
            '#type' => 'submit',
            '#value' => t('Unsubscribe All'),
            '#submit' => array('::submitUnsubscribeForm'),
          );
        } 
        break;
    }    
    return $form;
  }

  /**
  * Function to validate form .
  */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $formVal = $form_state->getValues();
    $uncheckedNewsletters = count(array_keys($formVal['newsletters'],'0'));
    $checkedValue = $formVal['newsletters'];
    if (sizeof($formVal['newsletters']) == $uncheckedNewsletters)
      drupal_set_message("Please select atleast one newsletter to subscribe!",'error');
  }

  /**
  * Function to update susbcriptions of subscriber .
  */
  public function submitUpdateForm(array &$form, FormStateInterface $form_state) {
    $formVal = $form_state->getValues();
    $userEmail = \Drupal::currentUser()->getEmail();
    $subscriber = enewsletters_subscriber_details($userEmail);
    $uid = \Drupal::currentUser()->id();
    \Drupal::database()->update('enewsletter_subscriber')
        ->fields([
          'uid' => $uid,
          'sub_fname' => $formVal['sub_first_name'],
          'sub_lname' => $formVal['sub_last_name'],
        ])->condition('sub_email', $userEmail)->execute();
    $machineName = enewsletters_machine_name_by_subscriber_id($subscriber->id);
    foreach ($machineName as $newsletterName => $newsletterMachineName) {
        $enewsletters[] = $machineName[$newsletterName]->newsletter_machine_name;
     }
    $result = array_diff($formVal['newsletters'],$enewsletters);
    foreach ($result as $newsletterName => $newsletterMachineName) {
        if ('0' != $newsletterMachineName) {
            $newsletter_id = enewsletters_get_newsletter_id($newsletterMachineName);
            \Drupal::database()->merge('enewsletter_subscriber_subscription')->key(array('sub_id' => $subscriber->id, 'newsletter_id' => $newsletter_id))
            ->fields([
              'newsletter_id' => $newsletter_id,
              'newsletter_machine_name' => $newsletterMachineName,
              'status' => NEWSLETTER_SUBSCRIPTION_STATUS_SUBSCRIBED,
            ])->execute();
        } else {
            \Drupal::database()->delete('enewsletter_subscriber_subscription')
            ->condition('sub_id' , $subscriber->id)
            ->condition('newsletter_machine_name' , $newsletterName)
            ->execute();
        }
    }
    drupal_set_message($this->t('@sub_name ,Your newsletter subscriptions have being updated!', array('@sub_name' => $form_state->getValue('sub_first_name')." ".$form_state->getValue('sub_last_name'))));
  }

  /**
  * Function to unsubscribe susbcriptions of subscriber .
  */
  public function submitUnsubscribeForm(array &$form, FormStateInterface $form_state) {
    $formVal = $form_state->getValues();
    $userEmail = \Drupal::currentUser()->getEmail();
    $subscriber = enewsletters_subscriber_details($userEmail);
    $uid = \Drupal::currentUser()->id();
    \Drupal::database()->update('enewsletter_subscriber')
        ->fields([
          'uid' => $uid,
        ])->condition('sub_email', $userEmail)->execute();
    foreach ($formVal['newsletters'] as $newsletterName => $newsletterMachineName) {
        if ('0' != $newsletterMachineName) {
          $newsletter_id = enewsletters_get_newsletter_id($newsletterMachineName);
          \Drupal::database()->delete('enewsletter_subscriber_subscription')
          ->condition('sub_id' , $subscriber->id)
          ->condition('newsletter_id' , $newsletter_id)
          ->execute();
        }     
    }
    drupal_set_message($this->t('@sub_name ,Your newsletter subscriptions have been removed!', array('@sub_name' => $form_state->getValue('sub_first_name')." ".$form_state->getValue('sub_last_name'))));  
  }

  /**
  * Function to subscribe if user is logged in .
  */
  public function submitSubscibeForm(array &$form, FormStateInterface $form_state) {
    $formVal = $form_state->getValues();
    $userEmail = \Drupal::currentUser()->getEmail();
    $uid = \Drupal::currentUser()->id();
    \Drupal::database()->merge('enewsletter_subscriber')->key(array('sub_email' => $userEmail))
        ->fields([
          'uid' => $uid,
          'sub_fname' => $formVal['sub_first_name'],
          'sub_lname' => $formVal['sub_last_name'],
          'status' => NEWSLETTER_SUBSCRIPTION_STATUS_SUBSCRIBED,
        ])->execute();
    $subscriber = enewsletters_subscriber_details($userEmail);
    foreach ($formVal['newsletters'] as $newsletterName => $newsletterMachineName) {
        if ('0' != $newsletterMachineName) {
          $newsletter_id = enewsletters_get_newsletter_id($newsletterMachineName);
          $enewsletters[] = array(
           'sub_id' => $subscriber->id,
           'newsletter_id' => $newsletter_id , 
           'newsletter_machine_name' => $newsletterMachineName,
           'status' => NEWSLETTER_SUBSCRIPTION_STATUS_SUBSCRIBED
           );
        }     
      }
    $query = \Drupal::database()->insert('enewsletter_subscriber_subscription')->fields(array('sub_id','newsletter_id', 'newsletter_machine_name','status'));
    foreach ($enewsletters as $record) {
      $query->values($record);
    }
    $query->execute();
    drupal_set_message($this->t('@sub_name ,You have been subscribed for our newsletters .', array('@sub_name' => $form_state->getValue('sub_first_name')." ".$form_state->getValue('sub_last_name'))));    
  }
 
  /**
  * Function to insert the subscriber information  .
  */
  public function insertSubscriber($sub_fn, $sub_ln, $sub_mail, $uid = NULL) {
    if ($uid) {
        return \Drupal::database()->insert('enewsletter_subscriber')
          ->fields([
            'uid',
            'sub_fname',
            'sub_lname',
            'sub_email',
            'status'
          ])
          ->values(array(
            $uid,
            $sub_fn,
            $sub_ln,
            $sub_mail,
            NEWSLETTER_SUBSCRIPTION_STATUS_SUBSCRIBED,
          ))->execute();       
    } else {
        return \Drupal::database()->insert('enewsletter_subscriber')
          ->fields([
            'sub_fname',
            'sub_lname',
            'sub_email',
            'status'
          ])
          ->values(array(
            $sub_fn,
            $sub_ln,
            $sub_mail,
            NEWSLETTER_SUBSCRIPTION_STATUS_SUBSCRIBED,
          ))->execute();
      }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $formVal = $form_state->getValues();
    $subscribedUser = check_subscribed_user($formVal['sub_mail']);
    $user = check_registered_user($formVal['sub_mail']);
    switch ($user) {
      case ANONYMOUS: 
        if ($subscribedUser == '0') {
         $id = $this->insertSubscriber($formVal['sub_first_name'],$formVal['sub_last_name'],$formVal['sub_mail']);    
        } else {
          drupal_set_message("Email already present as subscriber. Please login to manage subscriptions!",'error');
          return false;
        }
        break;
      case REGISTERED_USER: 
        if ($subscribedUser == '0') {
          $uid = enewsletter_get_uid($formVal['sub_mail']); 
          $id = $this->insertSubscriber($formVal['sub_first_name'],$formVal['sub_last_name'],$formVal['sub_mail'],$uid);
        } else {
          drupal_set_message("Email already present as subscriber. Please login to manage subscriptions!",'error');
          return false;
        }  
    }

    foreach ($formVal['newsletters'] as $newsletterName => $newsletterMachineName) {
        if ('0' != $newsletterMachineName) {
          $newsletter_id = enewsletters_get_newsletter_id($newsletterMachineName);
          $enewsletters[] = array(
           'sub_id' => $id,
           'newsletter_id' => $newsletter_id , 
           'newsletter_machine_name' => $newsletterMachineName,
           'status' => NEWSLETTER_SUBSCRIPTION_STATUS_SUBSCRIBED
           );
        }     
      }
    $query = \Drupal::database()->insert('enewsletter_subscriber_subscription')->fields(array('sub_id','newsletter_id', 'newsletter_machine_name','status'));
    foreach ($enewsletters as $record) {
      $query->values($record);
    }
    $query->execute();
    drupal_set_message($this->t('@sub_name ,You have been subscribed for our enewsletters . ', array('@sub_name' => $form_state->getValue('sub_first_name')." ".$form_state->getValue('sub_last_name'))));    
  } 
}
