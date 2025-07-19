<?php
namespace Drupal\aichatbot\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;

class AichatbotGeminiService {
  protected $configFactory;
  protected $httpClient;

  public function __construct(ConfigFactoryInterface $configFactory, ClientInterface $httpClient) {
    $this->configFactory = $configFactory;
    $this->httpClient = $httpClient;
  }

  public function queryGemini($prompt, $userInput) {
    $config = $this->configFactory->get('aichatbot.settings');
    $apiUrl = $config->get('api_url');
	$apiKey = $config->get('api_key');
    $model = $config->get('model');
    
    // Construct the full Gemini endpoint URL with the model name
    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models' . '/' . $model . ':generateContent';

    $response = $this->httpClient->post($apiUrl, [
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'query' => [
        'key' => $apiKey,
      ],
      'json' => [
        'contents' => [
          [
            'role' => 'user',
            'parts' => [
              ['text' => $prompt . "\n" . $userInput],
            ],
          ],
        ],
      ],
    ]);

    $data = json_decode($response->getBody()->getContents(), TRUE);
    //\Drupal::logger('aichatbot')->info('Gemini AI Response- ' . $data['candidates'][0]['content']['parts'][0]['text']);
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Error in service response- G1';
  }
}
