<?php
namespace Drupal\aichatbot\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a settings form for AI URL, Key and model.
 */
class AichatbotApiSettingsForm extends ConfigFormBase {
  public function getFormId() {
    return 'aichatbot_api_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['aichatbot.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('aichatbot.settings');

    $form['api_url'] = [
      '#type' => 'textfield',
      '#title' => 'OpenAI API URL',
      '#default_value' => $config->get('api_url'),
	  '#description' => $this->t('Enter OpenAI URL to POST and GET data from. Use this URL if you are not sure: https://api.openai.com/v1/chat/completions'),
	  '#maxlength' => 255,
	  '#size' => 100,
      '#required' => TRUE,
    ];

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => 'OpenAI API Key',
      '#default_value' => $config->get('api_key'),
	  '#description' => $this->t('Enter OpenAI Key.'),
	  '#maxlength' => 255,
	  '#size' => 100,
      '#required' => TRUE,
    ];

    $form['model'] = [
      '#type' => 'textfield',
      '#title' => 'Model Name',
      '#default_value' => $config->get('model'),
	  '#description' => $this->t('Enter OpenAI Model for which your API Key has access to, like: gpt-4.1, gpt-4.1-mini, gpt-4.1-nano, gpt-4o, gpt-4o-mini, gpt-3.5-turbo etc.'),
	  '#maxlength' => 200,
	  '#size' => 50,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (trim($form_state->getValue('api_url')) === '') {
      $form_state->setErrorByName('api_url', $this->t('The API URL cannot be empty.'));
    }

    if (trim($form_state->getValue('api_key')) === '') {
      $form_state->setErrorByName('api_key', $this->t('The API Key cannot be empty.'));
    }

    if (trim($form_state->getValue('model')) === '') {
      $form_state->setErrorByName('model', $this->t('The Model name cannot be empty.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('aichatbot.settings')
	  ->set('api_url', trim($form_state->getValue('api_url')))
      ->set('api_key', trim($form_state->getValue('api_key')))
      ->set('model', trim($form_state->getValue('model')))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
