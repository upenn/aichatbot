<?php

namespace Drupal\aichatbot\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a settings form for AI Chatbot prompt and welcome message.
 */
class AichatbotPromptSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'aichatbot_prompt_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['aichatbot.prompt'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('aichatbot.prompt');
    
    if (trim($config->get('custom_prompt'))) {
		$custom_prompt_val = $config->get('custom_prompt');
	} else {
		$custom_prompt_val = "You are a helpful agent. You will just talk about the company ABC Example Services Pte. Ltd. and available information. You will not provide any information related to topics other than this company and its products. If you are asked about something else other than the company then politely deny it.";
	}

    $form['custom_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom System Prompt for AI Model'),
      '#default_value' => $custom_prompt_val,
      '#description' => $this->t('Enter a system-level prompt to define how the chatbot behaves. This will be prepended to every user interaction. SAMPLE PROMPT: You are a helpful agent. You will just talk about the company ABC Example Services Pte. Ltd. and available information. You will not provide any information related to topics other than this company and its products. If you are asked about something else other than the company then politely deny it.'),
      '#maxlength' => 400,
      '#required' => TRUE,
    ];

    $form['welcome_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Chatbot Welcome Message'),
      '#default_value' => $config->get('welcome_message'),
      '#description' => $this->t('Message displayed when the chat window is opened.'),
	  '#maxlength' => 255,
	  '#size' => 100,
      '#required' => TRUE,
    ];

    $form['chatbot_agent_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Chatbot Agent Name'),
      '#default_value' => $config->get('chatbot_agent_name'),
      '#description' => $this->t('The name of the Agent in chatbot window for user interaction.'),
	  '#maxlength' => 40,
	  '#size' => 50,
      '#required' => TRUE,
    ];

    $form['chatbot_custom_data'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom Data for AI Model'),
      '#default_value' => $config->get('chatbot_custom_data'),
      '#description' => $this->t('Enter custom data for the model to do the search. This can be your company or organization data like About Us, FAQ\'s, Product Details etc. and infact anything. The chatbot will search this data and send it over to AI for creating an agent response.'),
      '#rows' => 20,
      '#cols' => 100,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (trim($form_state->getValue('custom_prompt')) === '') {
      $form_state->setErrorByName('custom_prompt', $this->t('The custom system prompt cannot be empty.'));
    }

    if (trim($form_state->getValue('welcome_message')) === '') {
      $form_state->setErrorByName('welcome_message', $this->t('The welcome message cannot be empty.'));
    }

    if (trim($form_state->getValue('chatbot_agent_name')) === '') {
      $form_state->setErrorByName('chatbot_agent_name', $this->t('Name of the Agent can not be empty.'));
    }

    if (trim($form_state->getValue('chatbot_custom_data')) === '') {
      $form_state->setErrorByName('chatbot_custom_data', $this->t('The chatbot custom data cannot be empty.'));
    }
    
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('aichatbot.prompt')
      ->set('custom_prompt', $form_state->getValue('custom_prompt'))
      ->set('welcome_message', $form_state->getValue('welcome_message'))
      ->set('chatbot_agent_name', $form_state->getValue('chatbot_agent_name'))
      ->set('chatbot_custom_data', $form_state->getValue('chatbot_custom_data'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
