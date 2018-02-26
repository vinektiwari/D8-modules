<?php
/**
 * @file
 * Contains \Drupal\indesign_import\Form\InDesignImportForm.
 */

namespace Drupal\indesign_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection;
use Drupal\Component\Utility;

class InDesignImportForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'indesign_import_form';
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['enctype'] = 'multipart/form-data';
    $form['upload_xml'] = array(
      '#type' => 'file',
      '#title' => 'Upload XML File',
      '#description' => t('Upload a file, allowed extensions: XML')
    );

    $form['images'] = array(
      '#type' => 'managed_file',
      '#title' => 'Upload Images',
      '#multiple' => TRUE,
      '#upload_location' => 'public://indesign_import/',
      '#upload_validators' => array(
        'file_validate_extensions' => array('png jpg jpeg')
      ),
      '#description' => 'Allowed extensions: png, jpg, jpeg'
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => 'Submit',
    );

    $form['#prefix'] = t(
      "<br>Instructions:
        <br>1. Make sure the XML is validated before uploading.
        <br>2. The XML should be created by Adobe Indesign as the importer parses the XML tags accordingly.
        <br>3. Multiple images can be uploaded at a time.
        <br>4. Make sure you upload all the images which linked to the uploaded XML.
        <br>5. Articles which are imported are by default unpublished.");
    return $form;
  }

  /**
   * {@inheritdoc}
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $xmlValidators = ['file_validate_extensions' => ['xml']];
    if ($articleFile = file_save_upload('upload_xml', $xmlValidators, FALSE, 0)) {
        $this->saveArticles($articleFile);
        drupal_set_message('Xml imported successfully');
    }
    return $form;
  }

  /**
   * Import xml as article node
   * @param $xmlFile
   */
  public function saveArticles($xmlFile) {
    $dom = new \DOMDocument();
    $articleFileXml = file_get_contents($xmlFile->getFileUri());
    $xmlDecoder = new \Symfony\Component\Serializer\Encoder\XmlEncoder();
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $articlesXml = simplexml_load_string($articleFileXml);
    foreach ($articlesXml as $allArticles) {
      //Get the articles from the xml
      $articleXml = $allArticles->asXml();
      $articlesHtml = $this->xmlToHtml(XML_TO_HTML_REPLACEMENTS, $articleXml);

      //Decode Xml string to html array
      $articleHtmlDecoded = $xmlDecoder->decode($articlesHtml, array());
      $dom->loadHTML(mb_convert_encoding($articlesHtml, 'HTML-ENTITIES', 'UTF-8'));

      foreach ($articleHtmlDecoded as $articles) {
        foreach ($articles as $article) {
          if (isset($article['title'])) {
            $node = \Drupal\node\Entity\Node::create(
              array(
                'type' => 'article',
                'title' => $article['title'],
                'body' => [
                  'value' => $dom->saveHTML(),
                  'format' => 'full_html'
                ],
                'language' => $language,
                'status' => UNPUBLISHED
              )
            );
            $node->save();
            break;
          }
        }
      }
    }
  }

  /**
   * Search and replace an XML string and replace tags with provided HTML tag mapping
   * @param $replacements
   * @param $xmlString
   * @return string - the modified string
   */
  public function xmlToHtml($replacements, $xmlString) {
    foreach ($replacements as $key => $value) {
      //Replace xml tags and attributes to html tags
      $xmlString = preg_replace('#(<|(<\/))(' . $key . ')#', ' $1' . $value . '$4 ', $xmlString);
      $xmlString = preg_replace('#(href_opt="images/)#', 'src="/sites/default/files/indesign_import/', $xmlString);
      $xmlString = preg_replace('#(href)#', 'alt', $xmlString);
    }
    return $xmlString;
  }
}
