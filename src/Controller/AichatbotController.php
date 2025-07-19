<?php

namespace Drupal\aichatbot\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\aichatbot\Services\AichatbotOpenAIService;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;

/**
 * Handles AI chatbot AJAX interactions.
 */
class AichatbotController extends ControllerBase {

  protected $cache;
  protected $session;
  protected $openaiService;
  protected $logger;

  /**
   * Constructor.
   */
  public function __construct(
    CacheBackendInterface $cache,
    SessionInterface $session,
    AichatbotOpenAIService $openaiService,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->cache = $cache;
    $this->session = $session;
    $this->openaiService = $openaiService;
    $this->logger = $logger_factory->get('aichatbot');
  }

  /**
   * Dependency injection.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.custom_aichatbot'),
      $container->get('session'),
      $container->get('aichatbot.openai'),
      $container->get('logger.factory')
    );
  }

  /**
   * Chatbot AJAX callback.
   */
  public function chat(Request $request) {
    
    $service_down_message = $this->t("Service seems to be down. Try again later.");

    $ai_service_config = $this->config('aichatbot.settings');
    $ai_service_name = trim($ai_service_config->get('ai_service') ?? '');
    
    if (empty($ai_service_name)) {
	  $this->logger->warning('AI service is not configured under AIChatbot API settings page.');
      return new JsonResponse(['error' => $service_down_message . ' E1'], 500);
    }
    
    $content = json_decode($request->getContent(), true);
    $question = isset($content['question']) ? trim($content['question']) : '';
    $question_stripped = Html::decodeEntities(strip_tags($question));
    $question = Xss::filter($question_stripped, []);	
	
    if (empty($question)) {
      $empty_question_response = $this->t("Not a valid query.");
      return new JsonResponse(['error' => $empty_question_response], 400);
    }

    // Load configured system prompt for AI.
    $config_prompt = $this->config('aichatbot.prompt');
    $prompt = trim($config_prompt->get('custom_prompt') ?? '');
    
    if (empty($prompt)) {
      $this->logger->warning('Model prompt is not set in configuration.');
      return new JsonResponse(['error' => $service_down_message . ' E2'], 500);
    }

    // Generate cache key.
    $hash_key = sha1($question . '|' . $prompt);
    $cache_id = "aichatbot_query:$hash_key";

    // Try to retrieve from cache.
    if ($cache = $this->cache->get($cache_id)) {
      $cached_response = $cache->data;
      $this->appendToSession($question, $cached_response, true);
      return new JsonResponse([
        'answer' => $cached_response,
        'from_cache' => true,
      ]);
    }

    // Load custom data for AI.
    $custom_data = trim($config_prompt->get('chatbot_custom_data') ?? '');

    // Custom data text (replace this with your actual data)
    $custom_data_trim = trim($custom_data);
    $custom_data_strip = Html::decodeEntities(strip_tags($custom_data_trim));
    $custom_data = Xss::filter($custom_data_strip, []);

    // Search for context using custom data.
    $context_text = $this->getRelevantContextCustom($question, $custom_data);
    //$this->logger->info('Context and question: Q- ' . $question . ' Context- ' . $context_text);

    // Fallback if context not found.
    if (empty($context_text)) {
      //$this->logger->warning('No relevant content found in custom data for question: @q', ['@q' => $question]);
      $context_text = 'No relevant information found for your query.';
    }

    // Call AI service with context and prompt.
    try {
        $inputWithContext = $context_text . "\n\nQuestion: " . $question;
        
        if ($ai_service_name == 'openai') {
		  $answer = $this->openaiService->queryOpenAI($prompt, $inputWithContext);
	    } 
	    else if ($ai_service_name == 'gemini') {
			$this->logger->warning('Calling GEMINI.');
		  $answer = \Drupal::service('aichatbot.googlegemini')->queryGemini($prompt, $inputWithContext);
		}
	    else if ($ai_service_name == 'claude') {
			$this->logger->warning('Calling CLAUDE.');
		  $answer = \Drupal::service('aichatbot.anthropicclaude')->queryClaude($prompt, $inputWithContext);
		}
    }
    catch (\Exception $e) {
      $this->logger->error('AI Service API error: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse(['error' => $service_down_message . ' E3'], 500);
    }

    // Cache the result for 30 mins.
    $this->cache->set($cache_id, $answer, time() + 1800);

    // Add to session chat history.
    $this->appendToSession($question, $answer, false);

    return new JsonResponse([
      'answer' => $answer,
      'from_cache' => false,
    ]);
  }

    // Find context based on the user's question
    function getRelevantContextCustom($question, $data) {
        // Normalize the input for better matching (optional)
        $question = strtolower($question);
        $data = strtolower($data);

        // Split the custom data into sentences for better context extraction
        $sentences = preg_split('/(?<=[.!?])\s+/', $data);

        // Initialize an array to store relevant sentences
        $relevantSentences = [];

        // Search for sentences containing keywords from the user's question
        $keywords = explode(' ', $question); // Split the question into words
        foreach ($sentences as $sentence) {
            foreach ($keywords as $keyword) {
                if (stripos($sentence, $keyword) !== false) {
                    $relevantSentences[] = $sentence;
                    break; // Avoid adding the same sentence multiple times
                }
            }
        }

        // Combine relevant sentences into a single context
        $context = implode(' ', $relevantSentences);

        return $context;
    }

  /**
   * Appends Q&A pair to session history with optional timeout enforcement.
   */
  protected function appendToSession(string $question, string $answer, bool $from_cache) {
    $now = time();
    $last_active = $this->session->get('aichatbot_last_active');
    $history = $this->session->get('aichatbot_chat_history', []);

    // Enforce 30-min session timeout.
    if ($last_active && ($now - $last_active > 1800)) {
      //$this->logger->info('Session timed out; resetting chat history.');
      $history = [];
    }

    $history[] = [
      'q' => $question,
      'a' => $answer,
      'cached' => $from_cache,
      'timestamp' => $now,
    ];

    $this->session->set('aichatbot_chat_history', $history);
    $this->session->set('aichatbot_last_active', $now);
  }

  /**
   * Returns current chat history for the session.
   */
  public function getHistory() {
    $history = $this->session->get('aichatbot_chat_history', []);
    return new JsonResponse([
      'history' => $history,
    ]);
  }

  /**
   * Resets the chat session for the user.
   */
  public function resetHistory() {
    $this->session->remove('aichatbot_chat_history');
    $this->session->remove('aichatbot_last_active');
    //$session_id = session_id();
    //$this->logger->notice('User manually reset chatbot session.');
    return new JsonResponse(['status' => 'Chat session reset.']);
  }

}
