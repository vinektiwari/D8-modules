<?php
use Drupal\Core\Render\BubbleableMetadata;
/*
* implement hook_token_info()
*/
function enewsletters_token_token_info(){
  $types['enewsletters_token'] = array(
    'name' => t('Newsletter token'),
    'description' => t('Define newsletter token type'),
  );	
  $tokens['newsletter_daily'] = array(
    'name' => t('Daily Report'),
    'description' => t('Define daily newsletter token name'),
  );
  $tokens['newsletter_weekly'] = array(
    'name' => t('Weekly Report'),
    'description' => t('Define weekly newsletter token name'),
  );
  $tokens['newsletter_real_estate'] = array(
    'name' => t('Real Estate Insider'),
    'description' => t('Define real estate insider newsletter token name'),
  );
  $tokens['newsletter_mainebiz_event'] = array(
    'name' => t('MAinebiz Events'),
    'description' => t('Define mainebiz event newsletter token name'),
  );
  
  return array(
    'types' => $types,
    'tokens' => array(
      'enewsletters_token' => $tokens
    )
  );	
}

/*
* implement hook_token()
*/
function enewsletters_token_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata){
  $replacements = array();
  if($type == 'enewsletters_token') {
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'newsletter_daily':
          $text = 'Daily Report';
        	$replacements[$original] = $text;
        	break;
        case 'newsletter_weekly':
          $text = 'Weekly Report';
          $replacements[$original] = $text;
          break;
        case 'newsletter_real_estate':
          $text = 'Real Estate Insider';
          $replacements[$original] = $text;
          break;
        case 'newsletter_mainebiz_event':
          $text = 'Mainebiz Events';
          $replacements[$original] = $text;
          break;      
      }
    }
  }
  return $replacements;
}
?>
