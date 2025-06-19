<?php
namespace Drupal\aichatbot\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;

class AichatbotOpenAIService {
  protected $configFactory;
  protected $httpClient;

  public function __construct(ConfigFactoryInterface $configFactory, ClientInterface $httpClient) {
    $this->configFactory = $configFactory;
    $this->httpClient = $httpClient;
  }

  public function queryOpenAI($prompt, $userInput) {
    $config = $this->configFactory->get('aichatbot.settings');
    $apiUrl = $config->get('api_url');
	$apiKey = $config->get('api_key');
    $model = $config->get('model');
    
    $response = $this->httpClient->post($apiUrl, [
      'headers' => [
        'Authorization' => 'Bearer ' . $apiKey,
        'Content-Type' => 'application/json',
      ],
      'json' => [
        'model' => $model,
        'messages' => [
          ['role' => 'system', 'content' => $prompt],
          ['role' => 'user', 'content' => $userInput],
        ],
        'max_tokens' => 200,
        'temperature' => 0.5,
      ],
    ]);

    $data = json_decode($response->getBody()->getContents(), TRUE);
    //\Drupal::logger('aichatbot')->info('Open AI Response- ' . $data['choices'][0]['message']['content']);
    
    return $data['choices'][0]['message']['content'] ?? 'Error in response.';
  }
}
