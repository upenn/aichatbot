<?php
namespace Drupal\aichatbot\Services;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class AichatbotOpenAIService {
  protected $configFactory;
  protected $httpClient;
  protected $session;

  public function __construct(ConfigFactoryInterface $configFactory, ClientInterface $httpClient, $session) {
    $this->configFactory = $configFactory;
    $this->httpClient = $httpClient;
    $this->session = $session; // Injected session handler.
  }

  // Main method to query OpenAI with prompt and user input, branching between assistant and standard models.
  public function queryOpenAI($prompt, $userInput) {
    $config = $this->configFactory->get('aichatbot.settings');
    $apiUrl = $config->get('api_url');
    $apiKey = $config->get('api_key');
    $model = $config->get('model');

    // Determine if the selected model is an assistant model based on naming convention.
    $isAssistant = str_starts_with($model, 'asst_');

    if ($isAssistant) {
      // Remove trailing slash from the API URL if present.
      $apiUrl = rtrim($apiUrl, '/');

      // Retrieve the thread ID from the session for assistant conversation tracking.
      $threadId = $this->session->get('aichatbot_openai_threadId');

      // If no thread ID exists, initiate a new thread.
      if (!$threadId) {
        $threadId = $this->createAssistantThread($apiUrl, $apiKey, $prompt);
        $this->session->set('aichatbot_openai_threadId', $threadId);

        $this->sendAssistantMessage($apiUrl, $apiKey, $threadId, 'system', $prompt);
      }

      // Send a user role message to the assistant thread with the user input.
      $this->sendAssistantMessage($apiUrl, $apiKey, $threadId, 'user', $userInput);

      // Run the assistant thread and wait for response.
      $runId = $this->initiateAssistantRun($apiUrl, $apiKey, $model, $threadId);

      $completed = false;
      for ($i = 0; $i < 180; $i++) {
        sleep(1);
        $status = $this->getRunStatus($apiUrl, $apiKey, $threadId, $runId);
        if ($status === 'completed') {
          $completed = true;
          break;
        } elseif (in_array($status, ['expired', 'cancelling', 'cancelled', 'failed'], true)) {
          throw new Exception("Run failed with status: {$status}");
        }
      }
      if (!$completed) {
        throw new Exception("Run did not finish!");
      }

      return $this->getFirstAssistantMessage($apiUrl, $apiKey, $threadId);
    } else {
      return $this->sendStandardModelMessage($apiUrl, $apiKey, $model, $prompt, $userInput);
    }
  }

  // Creates a new assistant thread using OpenAI API.
  private function createAssistantThread($apiUrl, $apiKey, $prompt) {
    $response = $this->httpClient->post($apiUrl . '/v1/threads', [
      'headers' => [
        'Authorization' => 'Bearer ' . $apiKey,
        'Content-Type' => 'application/json',
        'OpenAI-Beta' => 'assistants=v2',
      ],
      'body' => ''
    ]);

    $data = json_decode($response->getBody()->getContents(), TRUE);
    if (empty($data['id'])) {
      throw new Exception('Error getting threadId!');
    }
    $threadId = $data['id'];

    return $threadId;
  }

  // Sends a message to the assistant thread with given role and content.
  private function sendAssistantMessage($apiUrl, $apiKey, $threadId, $role, $content) {
    $this->httpClient->post("{$apiUrl}/v1/threads/{$threadId}/messages", [
      'headers' => [
        'Authorization' => 'Bearer ' . $apiKey,
        'Content-Type' => 'application/json',
        'OpenAI-Beta' => 'assistants=v2',
      ],
      'json' => [
        'role' => $role,
        'content' => $content
      ]
    ]);
  }

  // Sends a complete prompt and user input to a standard model (non-assistant) endpoint.
  private function sendStandardModelMessage($apiUrl, $apiKey, $model, $prompt, $userInput) {
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
    return $data['choices'][0]['message']['content'] ?? 'Error in service response- O1';
  }

  // Initiates an assistant run for a thread and returns the run ID.
  private function initiateAssistantRun($apiUrl, $apiKey, $model, $threadId) {
    $response = $this->httpClient->post("{$apiUrl}/v1/threads/{$threadId}/runs", [
      'headers' => [
        'Authorization' => 'Bearer ' . $apiKey,
        'Content-Type' => 'application/json',
        'OpenAI-Beta' => 'assistants=v2',
      ],
      'json' => [
        'assistant_id' => $model
      ]
    ]);

    $runData = json_decode($response->getBody()->getContents(), TRUE);
    if (empty($runData['id'])) {
      throw new Exception('Failed to get run ID from OpenAI.');
    }
    return $runData['id'];
  }

  // Polls the assistant run for completion status.
  private function getRunStatus($apiUrl, $apiKey, $threadId, $runId) {
    $statusResponse = $this->httpClient->get("{$apiUrl}/v1/threads/{$threadId}/runs/{$runId}", [
      'headers' => [
        'Authorization' => 'Bearer ' . $apiKey,
        'Content-Type' => 'application/json',
        'OpenAI-Beta' => 'assistants=v2',
      ]
    ]);
    $statusData = json_decode($statusResponse->getBody()->getContents(), TRUE);
    return $statusData['status'] ?? '';
  }

  // Retrieves the first message from the assistant's message list.
  private function getFirstAssistantMessage($apiUrl, $apiKey, $threadId) {
    $messageResponse = $this->httpClient->get("{$apiUrl}/v1/threads/{$threadId}/messages", [
      'headers' => [
        'Authorization' => 'Bearer ' . $apiKey,
        'Content-Type' => 'application/json',
        'OpenAI-Beta' => 'assistants=v2',
      ]
    ]);
    $messageData = json_decode($messageResponse->getBody()->getContents(), TRUE);
    $messages = $messageData['data'] ?? [];
    if (!empty($messages)) {
      $firstMessage = $messages[0]['content'][0]['text']['value'] ?? 'No text content in message.';
      return $firstMessage;
    }
    return 'No messages found in thread.';
  }
}
