<?php
namespace Drupal\aichatbot\Services;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use \Exception;
use GuzzleHttp\Exception\GuzzleException;

class AichatbotOpenAIService {
  protected $configFactory;
  protected $httpClient;
  protected $loggerFactory;
  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;
  protected $keyRepository;
  protected $session;
  /**
   * Api constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The key repository service.
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client, LoggerChannelFactoryInterface $logger_factory, ModuleHandlerInterface $module_handler, KeyRepositoryInterface $key_repository, SessionInterface $session) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->loggerFactory = $logger_factory;
    $this->moduleHandler = $module_handler;
    $this->keyRepository = $key_repository;
    $this->session = $session; // Injected session handler.
  }

  // protected array $config = [];

  // public function __construct($configOrFactory, ClientInterface $httpClient, $session) {
  //   if (is_array($configOrFactory)) {
  //     $this->config = $configOrFactory;
  //   } else {
  //     $this->configFactory = $configOrFactory;
  //   }
  //   $this->httpClient = $httpClient;
  //   $this->session = $session;
  // }

  // Main method to query OpenAI with prompt and user input, branching between assistant and standard models.
  public function queryOpenAI($prompt, $userInput) {
    $config = $this->configFactory->get('aichatbot.settings');
    $apiUrl = $config->get('api_url');
    $key = $this->keyRepository->getKey($config->get('api_key'));
    if ($key && $key->getKeyValue()) {
      $apiKey = $key->getKeyValue();
    }
    $model = $config->get('model');

    // Determine if the selected model is an assistant model based on naming convention.
    $isAssistant = str_starts_with($model, 'asst_');

    try {
      return $this->queryOpenAIInternal($apiUrl, $apiKey, $model, $prompt, $userInput, $isAssistant);
    } catch (Exception $e) {
      // On any failure, wait 10 seconds and retry once.
      $this->loggerFactory->get('aichatbot')->warning('OpenAI request failed, retrying in 10 seconds: @message', ['@message' => $e->getMessage()]);
      sleep(10);
      return $this->queryOpenAIInternal($apiUrl, $apiKey, $model, $prompt, $userInput, $isAssistant);
    }
  }

  protected function queryOpenAIInternal($apiUrl, $apiKey, $model, $prompt, $userInput, $isAssistant) {
    if ($isAssistant) {
      // Remove trailing slash from the API URL if present.
      $apiUrl = rtrim($apiUrl, '/');

      $reply = $this->runAssistantAndGetReply($apiUrl, $apiKey, $model, $prompt, $userInput);

      // If the assistant couldn't find the information, retry once after a pause.
      if (str_contains($reply, "wasn't able to find that information")) {
        sleep(10);
        $reply = $this->runAssistantAndGetReply($apiUrl, $apiKey, $model, $prompt, 'try again');
      }

      return $reply;

    } else {
      return $this->sendStandardModelMessage($apiUrl, $apiKey, $model, $prompt, $userInput);
    }
  }

  public function runAssistantAndGetReply($apiUrl, $apiKey, $model, $prompt, $userInput) {

    // Retrieve the thread ID from the session for assistant conversation tracking.
    $threadId = $this->session->get('aichatbot_openai_threadId');

    // If no thread ID exists, initiate a new thread.
    if (!$threadId) {
      $threadId = $this->createAssistantThread($apiUrl, $apiKey);
      $this->session->set('aichatbot_openai_threadId', $threadId);
      $this->sendAssistantMessage($apiUrl, $apiKey, $threadId, 'user', $prompt);
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
      throw new Exception("Run timed out after 180 seconds.");
    }

    return $this->getFirstAssistantMessage($apiUrl, $apiKey, $threadId);
  }

  // Creates a new assistant thread using OpenAI API.
  public function createAssistantThread($apiUrl, $apiKey) {
    try {
      $response = $this->httpClient->post($apiUrl . '/v1/threads', [
        'headers' => [
          'Authorization' => 'Bearer ' . $apiKey,
          'Content-Type' => 'application/json',
          'OpenAI-Beta' => 'assistants=v2',
        ],
        'body' => ''
      ]);
    } catch (GuzzleException $e) {
      $this->loggerFactory->get('aichatbot')->error('Failed to create assistant thread: @message', ['@message' => $e->getMessage()]);
      throw new Exception('Failed to create assistant thread.');
    }

    $data = json_decode($response->getBody()->getContents(), TRUE);
    if (empty($data['id'])) {
      throw new Exception('Error getting threadId!');
    }
    $threadId = $data['id'];

    return $threadId;
  }

  // Sends a message to the assistant thread with given role and content.
  public function sendAssistantMessage($apiUrl, $apiKey, $threadId, $role, $content) {
    try {
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
    } catch (GuzzleException $e) {
      $this->loggerFactory->get('aichatbot')->error('Failed to send assistant message: @message', ['@message' => $e->getMessage()]);
      throw new Exception('Failed to send message to assistant thread.');
    }
  }

  // Sends a complete prompt and user input to a standard model (non-assistant) endpoint.
  public function sendStandardModelMessage($apiUrl, $apiKey, $model, $prompt, $userInput) {
    try {
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
          'max_tokens' => 1500,
          'temperature' => 0.5,
        ],
      ]);
    } catch (GuzzleException $e) {
      $this->loggerFactory->get('aichatbot')->error('Failed to send standard model message: @message', ['@message' => $e->getMessage()]);
      throw new Exception('Failed to get response from OpenAI.');
    }
    $data = json_decode($response->getBody()->getContents(), TRUE);
    return $data['choices'][0]['message']['content'] ?? 'Error in service response- O1';
  }

  // Initiates an assistant run for a thread and returns the run ID.
  public function initiateAssistantRun($apiUrl, $apiKey, $model, $threadId) {
    try {
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
    } catch (GuzzleException $e) {
      $this->loggerFactory->get('aichatbot')->error('Failed to initiate assistant run: @message', ['@message' => $e->getMessage()]);
      throw new Exception('Failed to initiate assistant run.');
    }

    $runData = json_decode($response->getBody()->getContents(), TRUE);
    if (empty($runData['id'])) {
      throw new Exception('Failed to get run ID from OpenAI.');
    }
    return $runData['id'];
  }

  // Polls the assistant run for completion status.
  public function getRunStatus($apiUrl, $apiKey, $threadId, $runId) {
    try {
      $statusResponse = $this->httpClient->get("{$apiUrl}/v1/threads/{$threadId}/runs/{$runId}", [
        'headers' => [
          'Authorization' => 'Bearer ' . $apiKey,
          'Content-Type' => 'application/json',
          'OpenAI-Beta' => 'assistants=v2',
        ]
      ]);
    } catch (GuzzleException $e) {
      $this->loggerFactory->get('aichatbot')->error('Failed to get run status: @message', ['@message' => $e->getMessage()]);
      throw new Exception('Failed to check assistant run status.');
    }
    $statusData = json_decode($statusResponse->getBody()->getContents(), TRUE);
    return $statusData['status'] ?? '';
  }

  // Retrieves the first message from the assistant's message list.
  public function getFirstAssistantMessage($apiUrl, $apiKey, $threadId) {
    try {
      $messageResponse = $this->httpClient->get("{$apiUrl}/v1/threads/{$threadId}/messages", [
        'headers' => [
          'Authorization' => 'Bearer ' . $apiKey,
          'Content-Type' => 'application/json',
          'OpenAI-Beta' => 'assistants=v2',
        ]
      ]);
    } catch (GuzzleException $e) {
      $this->loggerFactory->get('aichatbot')->error('Failed to get assistant messages: @message', ['@message' => $e->getMessage()]);
      throw new Exception('Failed to retrieve assistant response.');
    }
    $messageData = json_decode($messageResponse->getBody()->getContents(), TRUE);
    $messages = $messageData['data'] ?? [];
    if (!empty($messages)) {
      $firstMessage = $messages[0]['content'][0]['text']['value'] ?? 'No text content in message.';
      return $firstMessage;
    }
    return 'No messages found in thread.';
  }
}
