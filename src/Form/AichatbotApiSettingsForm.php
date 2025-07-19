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

    // AI Service select dropdown
    $form['ai_service'] = [
      '#type' => 'select',
      '#title' => $this->t('AI Service'),
      '#options' => [
        '' => $this->t('Select Service'),
        'openai' => $this->t('Open AI'),
        'gemini' => $this->t('Google Gemini'),
        'claude' => $this->t('Anthropic Claude'),
      ],
      '#default_value' => $config->get('ai_service') ?? '',
      '#description' => $this->t('Choose the AI service provider.'),
      '#required' => TRUE,
    ];

    $form['api_url'] = [
      '#type' => 'textfield',
      '#title' => 'API URL',
      '#default_value' => $config->get('api_url'),
	  '#description' => $this->t('Enter AI Service API URL to POST and GET data from. 
	  For Open AI: Use this URL if you are not sure: https://api.openai.com/v1/chat/completions | 
	  For Gemini: https://generativelanguage.googleapis.com/v1beta/models [do not add model name in API URL] |
	  For Claude: https://api.anthropic.com/v1/messages'),
	  '#maxlength' => 255,
	  '#size' => 100,
      '#required' => TRUE,
    ];

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => 'AI Service API Key',
      '#default_value' => $config->get('api_key'),
	  '#description' => $this->t('Enter API Key.'),
	  '#maxlength' => 255,
	  '#size' => 100,
      '#required' => TRUE,
    ];

    $form['model'] = [
      '#type' => 'textfield',
      '#title' => 'Model Name',
      '#default_value' => $config->get('model'),
	  '#description' => $this->t('Enter Model for which your API Key has access to, 
	  like Open AI: gpt-4.1, gpt-4.1-mini, gpt-4.1-nano, gpt-4o, gpt-4o-mini, gpt-3.5-turbo etc.
	  Gemini: gemini-2.0-flash-lite, gemini-2.0-flash, gemini-2.5-flash-preview-04-17 etc. |
	  Claude: claude-3-5-haiku-20241022, claude-3-opus-20240229'),
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
    if (trim($form_state->getValue('ai_service')) === '') {
      $form_state->setErrorByName('ai_service', $this->t('Select AI service provider.'));
    }
	  
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
      ->set('ai_service', $form_state->getValue('ai_service'))
	  ->set('api_url', trim($form_state->getValue('api_url')))
      ->set('api_key', trim($form_state->getValue('api_key')))
      ->set('model', trim($form_state->getValue('model')))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
