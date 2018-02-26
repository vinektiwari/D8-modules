<?php
/**
 * @file
 * Token related hook implementations.
 */
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/*
* implement hook_token_info()
*/
function enewsletters_token_info(){
  $types['enewsletters-newsletter'] = array(
    'name' => t('Newsletter'),
    'description' => t('Tokens related to the newsletter'),
  );

  // Tokens for enewsletters newsletter.
  $newsletter['name'] = array(
    'name' => t('Newsletter name'),
    'description' => t('The name of the newsletter.'),
  );
  
  // Tokens for static newsletters type.
  $tokens['daily'] = array(
    'name' => t('Daily Report'),
    'description' => t('Daily newsletter name'),
  );
  $tokens['weekly'] = array(
    'name' => t('Weekly Report'),
    'description' => t('Weekly newsletter name'),
  );
  $tokens['real-estate'] = array(
    'name' => t('Real Estate Insider'),
    'description' => t('Real estate insider newsletter name'),
  );
  $tokens['mainebiz-event'] = array(
    'name' => t('Mainebiz Events'),
    'description' => t('MaineBiz event newsletter name'),
  );
  
  return array(
    'types' => $types,
    'tokens' => array(
      'enewsletters-newsletter' => $tokens
    )
  );	
}

/*
* implement hook_token()
*/
function enewsletters_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata){
  $replacements = array();
  if (!isset($data['newsletter'])) {
    return;
  }
  $newsletter = $data['newsletter'];
  
  if($type == 'enewsletters-newsletter') {
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'name':
          if (isset($newsletter->name)) {
            $replacements[$original] = $sanitize ? SafeMarkup::checkPlain($newsletter->name) : $newsletter->name;
          } else {
            $replacements[$original] = t('Unassigned newsletter');
          }
          break;
        case 'daily':
          $text = 'Daily Report';
          $replacements[$original] = $text;
          break;
        case 'weekly':
          $text = 'Weekly Report';
          $replacements[$original] = $text;
          break;
        case 'real-estate':
          $text = 'Real Estate Insider';
          $replacements[$original] = $text;
          break;
        case 'mainebiz-event':
          $text = 'Mainebiz Events';
          $replacements[$original] = $text;
          break;      
      }    
    }
  }
  return $replacements;
}
?>
