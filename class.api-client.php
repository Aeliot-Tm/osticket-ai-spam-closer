<?php

/**
 * API Client for Spam Detection
 * Handles AI-based spam detection using OpenAI-compatible API
 */
class AISpamCloserAPIClient {
    
    private string $api_key;
    private string $api_url;
    private bool $enable_logging;
    private string $model;
    private float $temperature;
    private int $timeout;
    
    public function __construct(string $api_key, string $model, string $api_url, int $timeout, bool $enable_logging, float $temperature) {
        $this->api_key = trim($api_key);
        $this->model = $model;
        $this->api_url = $api_url;
        $this->timeout = $timeout;
        $this->enable_logging = $enable_logging;
        $this->temperature = $temperature;
    }
    
    /**
     * Analyze ticket content for spam using AI
     *
     * @param string $ticket_content Full ticket content
     * @param array $spam_keywords Known spam keywords for context
     * @return array Result with spam detection
     */
    public function analyzeSpam($ticket_content, $spam_keywords = array()) {
        // Build prompt for spam detection
        $prompt = "Analyze the following ticket content and determine if it's spam.\n\n";
        $prompt .= "TICKET CONTENT:\n" . substr($ticket_content, 0, 3000) . "\n\n";
        
        if (!empty($spam_keywords)) {
            $prompt .= "KNOWN SPAM INDICATORS (for context):\n";
            $prompt .= implode(", ", array_slice($spam_keywords, 0, 20)) . "\n\n";
        }
        
        $prompt .= "Determine if this ticket is spam based on:\n";
        $prompt .= "1. Presence of promotional/commercial content\n";
        $prompt .= "2. Suspicious links or offers\n";
        $prompt .= "3. Generic mass-mailing patterns\n";
        $prompt .= "4. Requests for personal information or money\n";
        $prompt .= "5. Typical spam keywords and phrases\n";
        $prompt .= "6. Irrelevant or off-topic content\n\n";
        $prompt .= "Return JSON with:\n";
        $prompt .= '{"is_spam": <true/false>, "confidence": <0-100>, "reasoning": "<explanation>", "spam_indicators": ["indicator1", "indicator2"]}';
        
        $messages = array(
            array(
                'role' => 'system',
                'content' => 'You are a spam detection expert. Analyze ticket content and determine if it is spam. Always respond with valid JSON. Be thorough but err on the side of caution - only mark as spam if confidence is high.'
            ),
            array(
                'role' => 'user',
                'content' => $prompt
            )
        );
        
        $result = $this->makeRequest($messages, $this->model, true);
        
        if (!$result['success']) {
            return $result;
        }
        
        // Parse JSON response
        $analysis = json_decode($result['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'error' => 'Failed to parse AI response: ' . json_last_error_msg()
            );
        }
        
        if (!isset($analysis['is_spam'])) {
            return array(
                'success' => false,
                'error' => 'AI response missing spam determination'
            );
        }
        
        return array(
            'success' => true,
            'is_spam' => (bool)$analysis['is_spam'],
            'confidence' => isset($analysis['confidence']) ? intval($analysis['confidence']) : 0,
            'reasoning' => $analysis['reasoning'] ?? 'No reasoning provided',
            'spam_indicators' => $analysis['spam_indicators'] ?? array()
        );
    }
    
    /**
     * Extract text from image using GPT-4 Vision API
     *
     * @param string $file_data Binary file data
     * @param string $mime_type MIME type of the image
     * @return array Result with extracted text or error
     */
    public function extractTextFromImage($file_data, $mime_type) {
        // Convert to base64
        $base64_image = base64_encode($file_data);
        
        // Validate image type
        $supported_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array($mime_type, $supported_types)) {
            return array(
                'success' => false,
                'error' => 'Unsupported image type: ' . $mime_type
            );
        }
        
        $messages = array(
            array(
                'role' => 'system',
                'content' => 'You are an OCR assistant. Extract all text from the image and return it as plain text. If the image contains no readable text, return "No text found".'
            ),
            array(
                'role' => 'user',
                'content' => array(
                    array(
                        'type' => 'text',
                        'text' => 'Extract all text from this image:'
                    ),
                    array(
                        'type' => 'image_url',
                        'image_url' => array(
                            'url' => 'data:' . $mime_type . ';base64,' . $base64_image
                        )
                    )
                )
            )
        );
        
        $result = $this->makeRequest($messages, 'gpt-4o'); // Use gpt-4o for vision
        
        if (!$result['success']) {
            return $result;
        }
        
        return array(
            'success' => true,
            'text' => $result['data']
        );
    }
    
    /**
     * Make HTTP request to OpenAI API
     *
     * @param array $messages Messages array for chat completion
     * @param string $model Model to use (override default)
     * @param bool $json_mode Enable JSON response mode
     * @return array Result with data or error
     */
    private function makeRequest($messages, $model = null, $json_mode = false) {
        $data = array(
            'model' => $model ?: $this->model,
            'messages' => $messages,
            'temperature' => $this->temperature
        );
        
        if ($json_mode) {
            $data['response_format'] = array('type' => 'json_object');
        }
        
        $json_data = json_encode($data);
        
        if ($this->enable_logging) {
            error_log("AI Spam Closer - API Request: " . $json_data);
        }
        
        $ch = curl_init($this->api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            return array(
                'success' => false,
                'error' => 'CURL Error: ' . $curl_error
            );
        }
        
        if ($http_code !== 200) {
            $error_data = json_decode($response, true);
            $error_msg = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'Unknown error';
            return array(
                'success' => false,
                'error' => 'OpenAI API Error (' . $http_code . '): ' . $error_msg
            );
        }
        
        $result = json_decode($response, true);
        
        if ($this->enable_logging) {
            error_log("AI Spam Closer - API Response: " . $response);
        }
        
        if (!isset($result['choices'][0]['message']['content'])) {
            return array(
                'success' => false,
                'error' => 'Invalid API response format'
            );
        }
        
        return array(
            'success' => true,
            'data' => $result['choices'][0]['message']['content']
        );
    }
}
