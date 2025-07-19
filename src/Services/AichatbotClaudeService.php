<?php
namespace Drupal\aichatbot\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;

class AichatbotClaudeService {
  protected $configFactory;
  protected $httpClient;

  public function __construct(ConfigFactoryInterface $configFactory, ClientInterface $httpClient) {
    $this->configFactory = $configFactory;
    $this->httpClient = $httpClient;
  }

  public function queryClaude($prompt, $userInput) {
    $config = $this->configFactory->get('aichatbot.settings');
    $apiUrl = $config->get('api_url');
    $apiKey = $config->get('api_key');
    $model = $config->get('model');

    $response = $this->httpClient->post($apiUrl, [
      'headers' => [
        'Content-Type' => 'application/json',
        'x-api-key' => $apiKey,
        'anthropic-version' => '2023-06-01',
      ],
      'json' => [
        'model' => $model,
        'max_tokens' => 1024,
        'temperature' => 0.7,
        'messages' => [
          [
            'role' => 'user',
            'content' => $prompt . "\n" . $userInput,
          ],
        ],
      ],
    ]);

    $data = json_decode($response->getBody()->getContents(), TRUE);
    //\Drupal::logger('aichatbot')->info('Claude AI Response- ' . $data['content'][0]['text']);
    return $data['content'][0]['text'] ?? 'Error in service response - C1';
  }
}
