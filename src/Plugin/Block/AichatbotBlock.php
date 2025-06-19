<?php

namespace Drupal\aichatbot\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;

/**
 * Provides the AI Chatbot block.
 *
 * @Block(
 *   id = "aichatbot_block",
 *   admin_label = @Translation("AI Chatbot"),
 * )
 */
class AichatbotBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = \Drupal::config('aichatbot.prompt');
    $welcome = $config->get('welcome_message') ?? '';
    $agent_name = $config->get('chatbot_agent_name') ?? '';

    $build = [
      '#theme' => 'aichatbot_block',
      '#welcome_message' => $welcome,
      '#attached' => [
        'library' => ['aichatbot/chatbot'],
        'drupalSettings' => [
          'aichatbot' => [
            'chatUrl' => Url::fromRoute('aichatbot.chat')->toString(),
            'resetUrl' => Url::fromRoute('aichatbot.reset')->toString(),
            'historyUrl' => Url::fromRoute('aichatbot.history')->toString(),
            'agentName' => $agent_name,
          ],
        ],
      ],
    ];

    return $build;
  }

}
