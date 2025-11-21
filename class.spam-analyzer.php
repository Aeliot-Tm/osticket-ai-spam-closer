<?php

require_once('class.openai-client.php');

/**
 * Spam Analyzer
 * Analyzes tickets and determines if they are spam using AI and keywords
 */
class AISpamCloserAnalyzer {
    
    private $config;
    private $openai;
    
    public function __construct($config) {
        $this->config = $config;
        
        // Only initialize OpenAI if API key is provided
        $api_key = $config->get('api_key');
        if (!empty($api_key) && trim($api_key) !== '') {
            $this->openai = new AISpamCloserOpenAIClient(
                $api_key,
                $config->get('model'),
                $config->get('timeout'),
                $config->get('enable_logging')
            );
        } else {
            $this->openai = null;
            if ($config->get('enable_logging')) {
                error_log("AI Spam Closer - OpenAI API key not configured, using keywords only");
            }
        }
    }
    
    /**
     * Analyze ticket and determine if it's spam
     * 
     * @param int $ticket_id Ticket ID
     * @return array Result with spam detection
     */
    public function analyzeTicket($ticket_id) {
        try {
            $ticket = Ticket::lookup($ticket_id);
            
            if (!$ticket) {
                return array(
                    'success' => false,
                    'error' => 'Ticket not found'
                );
            }
            
            // Get spam keywords
            $keywords = $this->config->getSpamKeywords();
            if (empty($keywords)) {
                return array(
                    'success' => false,
                    'error' => 'No spam keywords configured'
                );
            }
            
            // Extract all content from ticket
            $content = $this->extractTicketContent($ticket);
            
            // Collect debug info
            $debug_info = array(
                'keywords_count' => count($keywords),
                'keywords' => array_slice($keywords, 0, 10),
                'content_length' => strlen($content),
                'content_preview' => substr($content, 0, 300)
            );
            
            if ($this->config->get('enable_logging')) {
                error_log("AI Spam Closer - Analyzing ticket #" . $ticket->getNumber());
                error_log("AI Spam Closer - Content length: " . strlen($content));
                error_log("AI Spam Closer - Keywords count: " . count($keywords));
                error_log("AI Spam Closer - Keywords: " . implode(', ', array_slice($keywords, 0, 10)));
                error_log("AI Spam Closer - Content preview: " . substr($content, 0, 200));
            }
            
            // First check keywords for direct match (fast and reliable)
            $keywordResult = null;
            if (!empty($keywords)) {
                $keywordResult = $this->checkForSpam($content, $keywords);
                $debug_info['keyword_check_result'] = $keywordResult;
                
                if ($this->config->get('enable_logging')) {
                    error_log("AI Spam Closer - Keyword check: " . ($keywordResult['is_spam'] ? 'SPAM' : 'Clean'));
                    if ($keywordResult['is_spam']) {
                        error_log("AI Spam Closer - Matched keywords: " . implode(', ', $keywordResult['matched_keywords']));
                    }
                }
                
                // If keywords found spam, return immediately
                if ($keywordResult['is_spam']) {
                    $result = array(
                        'success' => true,
                        'is_spam' => true,
                        'matched_keywords' => $keywordResult['matched_keywords'],
                        'reason' => 'Detected spam keywords: ' . implode(', ', $keywordResult['matched_keywords'])
                    );
                    
                    if ($this->config->get('enable_logging')) {
                        $result['debug'] = $debug_info;
                    }
                    
                    return $result;
                }
            }
            
            // Use AI for deeper analysis if no keywords matched (and AI is available)
            if ($this->openai === null) {
                // No AI configured, keywords didn't match
                if ($this->config->get('enable_logging')) {
                    error_log("AI Spam Closer - No AI available, no keyword matches found");
                }
                
                $result = array(
                    'success' => true,
                    'is_spam' => false,
                    'message' => 'No spam keywords found (AI not configured)'
                );
                
                if ($this->config->get('enable_logging')) {
                    $result['debug'] = $debug_info;
                }
                
                return $result;
            }
            
            $aiResult = $this->openai->analyzeSpam($content, $keywords);
            
            if ($aiResult['success']) {
                if ($aiResult['is_spam']) {
                    $indicators = isset($aiResult['spam_indicators']) ? $aiResult['spam_indicators'] : array();
                    
                    if ($this->config->get('enable_logging')) {
                        error_log("AI Spam Closer - AI detected spam with confidence: " . $aiResult['confidence'] . '%');
                    }
                    
                    return array(
                        'success' => true,
                        'is_spam' => true,
                        'confidence' => $aiResult['confidence'],
                        'spam_indicators' => $indicators,
                        'reason' => 'AI detected spam (confidence: ' . $aiResult['confidence'] . '%): ' . $aiResult['reasoning']
                    );
                }
                
                if ($this->config->get('enable_logging')) {
                    error_log("AI Spam Closer - AI determined not spam with confidence: " . $aiResult['confidence'] . '%');
                }
                
                $result = array(
                    'success' => true,
                    'is_spam' => false,
                    'confidence' => $aiResult['confidence'],
                    'message' => 'AI determined this is not spam (confidence: ' . $aiResult['confidence'] . '%)'
                );
                
                if ($this->config->get('enable_logging')) {
                    $result['debug'] = $debug_info;
                }
                
                return $result;
            }
            
            // AI failed - rely on keyword result
            if ($this->config->get('enable_logging')) {
                error_log("AI Spam Closer - AI analysis failed: " . ($aiResult['error'] ?? 'Unknown error'));
            }
            
            if ($keywordResult !== null) {
                // We already checked keywords above, no spam found
                return array(
                    'success' => true,
                    'is_spam' => false,
                    'message' => 'No spam keywords found (AI unavailable)'
                );
            }
            
            return array(
                'success' => false,
                'error' => 'AI analysis failed and no keywords configured: ' . ($aiResult['error'] ?? 'Unknown error')
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Extract all content from ticket including subject, body, and attachments
     * 
     * @param Ticket $ticket
     * @return string Combined content
     */
    private function extractTicketContent($ticket) {
        $content = array();
        
        // Add subject
        $subject = $ticket->getSubject();
        if ($subject) {
            $content[] = 'Subject: ' . $subject;
        }
        
        // Get thread entries
        $thread = $ticket->getThread();
        if ($thread) {
            $entries = $thread->getEntries();
            
            foreach ($entries as $entry) {
                // Add message body
                $body = $entry->getBody();
                if ($body) {
                    $text = strip_tags($body->getClean());
                    if (!empty(trim($text))) {
                        $content[] = $text;
                    }
                }
                
                // Process attachments
                if ($entry->has_attachments && isset($entry->attachments)) {
                    foreach ($entry->attachments as $attachment) {
                        $file_text = $this->processAttachment($attachment);
                        if ($file_text) {
                            $content[] = 'File content: ' . $file_text;
                        }
                    }
                }
            }
        }
        
        return implode("\n\n", $content);
    }
    
    /**
     * Extract text from attachment file
     * 
     * @param object $attachment Attachment object
     * @return string|null Extracted text or null
     */
    private function processAttachment($attachment) {
        try {
            $file = $attachment->getFile();
            if (!$file) {
                return null;
            }
            
            $filename = $attachment->getFilename();
            $size = $file->getSize();
            $mime_type = $file->getType();
            
            // Check file size limit
            $max_size = intval($this->config->get('max_file_size')) * 1024 * 1024; // Convert MB to bytes
            if ($size > $max_size) {
                if ($this->config->get('enable_logging')) {
                    error_log("AI Spam Closer - File too large: $filename ($size bytes)");
                }
                return null;
            }
            
            if ($this->config->get('enable_logging')) {
                error_log("AI Spam Closer - Processing file: $filename (type: $mime_type, size: $size)");
            }
            
            // Handle images with Vision API
            if (preg_match('/^image\/(jpeg|jpg|png|gif|webp)$/i', $mime_type)) {
                return $this->extractTextFromImage($file);
            }
            
            // Handle plain text files
            if (preg_match('/^text\//i', $mime_type)) {
                return $file->getData();
            }
            
            // Handle PDF files
            if ($mime_type == 'application/pdf') {
                return $this->extractTextFromPDF($file);
            }
            
            // Handle Word documents
            if (preg_match('/word|officedocument\.wordprocessing/i', $mime_type)) {
                return $this->extractTextFromWord($file);
            }
            
            if ($this->config->get('enable_logging')) {
                error_log("AI Spam Closer - Unsupported file type: $mime_type");
            }
            
            return null;
            
        } catch (Exception $e) {
            if ($this->config->get('enable_logging')) {
                error_log("AI Spam Closer - Error processing attachment: " . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Extract text from image using AI
     */
    private function extractTextFromImage($file) {
        $file_data = $file->getData();
        $mime_type = $file->getType();
        
        if ($this->config->get('enable_logging')) {
            error_log("AI Spam Closer - Processing image file: " . $file->getName() . " (mime: " . $mime_type . ", size: " . strlen($file_data) . " bytes)");
        }
        
        $result = $this->openai->extractTextFromImage($file_data, $mime_type);
        
        if ($result['success']) {
            if ($this->config->get('enable_logging')) {
                error_log("AI Spam Closer - Successfully extracted " . strlen($result['text']) . " bytes from image using Vision API");
            }
            return $result['text'];
        }
        
        if ($this->config->get('enable_logging')) {
            error_log("AI Spam Closer - Image extraction failed: " . ($result['error'] ?? 'Unknown error'));
        }
        
        return null;
    }
    
    /**
     * Extract text from PDF
     */
    private function extractTextFromPDF($file) {
        if (!function_exists('shell_exec')) {
            if ($this->config->get('enable_logging')) {
                error_log("AI Spam Closer - shell_exec not available for PDF extraction");
            }
            return null;
        }
        
        if ($this->config->get('enable_logging')) {
            error_log("AI Spam Closer - Processing PDF file: " . $file->getName());
        }
        
        $tmpfile = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($tmpfile, $file->getData());
        
        // Use pdftotext to extract text from PDF
        $output = shell_exec("pdftotext '$tmpfile' - 2>/dev/null");
        
        unlink($tmpfile);
        
        if ($output && !empty(trim($output))) {
            if ($this->config->get('enable_logging')) {
                error_log("AI Spam Closer - Successfully extracted " . strlen($output) . " bytes from PDF document");
            }
            return $output;
        }
        
        if ($this->config->get('enable_logging')) {
            error_log("AI Spam Closer - Failed to extract text from PDF (empty output from pdftotext)");
        }
        
        return null;
    }
    
    /**
     * Extract text from Word document (.doc and .docx)
     */
    private function extractTextFromWord($file) {
        if (!function_exists('shell_exec')) {
            if ($this->config->get('enable_logging')) {
                error_log("AI Spam Closer - shell_exec not available");
            }
            return null;
        }
        
        $tmpfile = tempnam(sys_get_temp_dir(), 'doc_');
        file_put_contents($tmpfile, $file->getData());
        
        $output = '';
        $mime_type = $file->getType();
        
        // Get filename to check extension as fallback
        $filename = method_exists($file, 'getName') ? $file->getName() : '';
        if (!$filename && isset($file->filename)) {
            $filename = $file->filename;
        }
        
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if ($this->config->get('enable_logging')) {
            error_log("AI Spam Closer - Processing Word file: $filename (mime: $mime_type, ext: $extension)");
        }
        
        // Determine if it's .doc or .docx based on MIME type and extension
        $is_doc_legacy = (
            stripos($mime_type, 'msword') !== false && 
            stripos($mime_type, 'officedocument') === false
        ) || $extension === 'doc';
        
        $is_docx = (
            stripos($mime_type, 'wordprocessingml') !== false || 
            stripos($mime_type, 'officedocument') !== false
        ) || $extension === 'docx';
        
        // Try antiword for legacy .doc files
        if ($is_doc_legacy) {
            if ($this->config->get('enable_logging')) {
                error_log("AI Spam Closer - Detected legacy .doc file, trying antiword");
            }
            
            $output = shell_exec("antiword '$tmpfile' 2>/dev/null");
            
            if (!empty(trim($output))) {
                if ($this->config->get('enable_logging')) {
                    error_log("AI Spam Closer - Successfully extracted " . strlen($output) . " bytes using antiword");
                }
            } else {
                // Try catdoc as alternative
                if ($this->config->get('enable_logging')) {
                    error_log("AI Spam Closer - antiword failed, trying catdoc");
                }
                $output = shell_exec("catdoc '$tmpfile' 2>/dev/null");
                
                if (!empty(trim($output))) {
                    if ($this->config->get('enable_logging')) {
                        error_log("AI Spam Closer - Successfully extracted " . strlen($output) . " bytes using catdoc");
                    }
                } else {
                    if ($this->config->get('enable_logging')) {
                        error_log("AI Spam Closer - Both antiword and catdoc failed, trying .docx format as fallback");
                    }
                    // Mark as potential .docx for fallback attempt
                    $is_docx = true;
                }
            }
        }
        
        // Try extracting .docx (Office Open XML)
        if (empty(trim($output)) && ($is_docx || !$is_doc_legacy)) {
            if ($this->config->get('enable_logging')) {
                error_log("AI Spam Closer - Trying to extract as .docx using unzip");
            }
            
            // Create temp directory for extraction
            $tmpdir = sys_get_temp_dir() . '/docx_' . uniqid();
            mkdir($tmpdir);
            
            // Extract document.xml
            shell_exec("unzip -q -d '$tmpdir' '$tmpfile' word/document.xml 2>/dev/null");
            
            $xml_file = $tmpdir . '/word/document.xml';
            if (file_exists($xml_file)) {
                $xml_content = file_get_contents($xml_file);
                
                // Remove XML tags and extract text
                $text = preg_replace('/<[^>]+>/', ' ', $xml_content);
                
                // Clean up whitespace
                $text = preg_replace('/\s+/', ' ', $text);
                $output = trim($text);
                
                if (!empty($output)) {
                    if ($this->config->get('enable_logging')) {
                        error_log("AI Spam Closer - Successfully extracted " . strlen($output) . " bytes from .docx");
                    }
                }
            } else {
                if ($this->config->get('enable_logging')) {
                    error_log("AI Spam Closer - Failed to extract document.xml from .docx");
                }
            }
            
            // Clean up temp directory
            shell_exec("rm -rf '$tmpdir'");
        }
        
        unlink($tmpfile);
        
        if ($output && !empty(trim($output))) {
            return $output;
        }
        
        if ($this->config->get('enable_logging')) {
            error_log("AI Spam Closer - Failed to extract text from Word document (mime: $mime_type, ext: $extension)");
        }
        
        return null;
    }
    
    /**
     * Check if content contains spam keywords
     * 
     * @param string $content Content to check
     * @param array $keywords Spam keywords (comma/semicolon separated)
     * @return array Result with is_spam and matched_keywords
     */
    private function checkForSpam($content, $keywords) {
        $content_lower = mb_strtolower($content, 'UTF-8');
        $matched_keywords = array();
        
        if ($this->config->get('enable_logging')) {
            error_log("AI Spam Closer - checkForSpam: checking " . count($keywords) . " keywords against " . strlen($content) . " bytes");
        }
        
        foreach ($keywords as $idx => $keyword) {
            if (empty($keyword)) {
                if ($this->config->get('enable_logging')) {
                    error_log("AI Spam Closer - Skipping empty keyword at index $idx");
                }
                continue;
            }
            
            $keyword_lower = mb_strtolower($keyword, 'UTF-8');
            
            if ($this->config->get('enable_logging')) {
                error_log("AI Spam Closer - Checking keyword[$idx]: '" . $keyword . "' (lower: '" . $keyword_lower . "')");
            }
            
            // Check if keyword exists in content (case-insensitive)
            $pos = mb_stripos($content_lower, $keyword_lower);
            if ($pos !== false) {
                $matched_keywords[] = $keyword;
                
                if ($this->config->get('enable_logging')) {
                    error_log("AI Spam Closer - MATCH FOUND: '$keyword' at position $pos");
                }
            } else {
                if ($this->config->get('enable_logging')) {
                    error_log("AI Spam Closer - NO MATCH: '$keyword' not found in content");
                }
            }
        }
        
        if ($this->config->get('enable_logging')) {
            error_log("AI Spam Closer - checkForSpam result: " . (empty($matched_keywords) ? 'NO MATCHES' : count($matched_keywords) . ' matches'));
        }
        
        return array(
            'is_spam' => !empty($matched_keywords),
            'matched_keywords' => $matched_keywords
        );
    }
    
    /**
     * Close ticket as spam
     * 
     * @param Ticket $ticket
     * @param string $reason Reason for closing
     * @return bool Success status
     */
    public function closeTicket($ticket, $reason) {
        try {
            // Check if ticket is already closed
            $status = $ticket->getStatus();
            if ($status && $status->getState() == 'closed') {
                if ($this->config->get('enable_logging')) {
                    error_log("AI Spam Closer - Ticket already closed");
                }
                return false;
            }
            
            // Get close reason text from config
            $close_reason_text = $this->config->get('close_reason') ?: 'Closed as spam';
            
            // Log internal note
            $note_title = 'Spam Detected - Auto Closed';
            $note_body = sprintf(
                '%s<br><br>Reason: %s',
                htmlspecialchars($close_reason_text),
                htmlspecialchars($reason)
            );
            
            $ticket->logNote($note_title, $note_body, 'SYSTEM', false);
            
            // Close the ticket
            // Find a closed status
            $closed_status = null;
            foreach (TicketStatusList::getStatuses() as $status) {
                if ($status->getState() == 'closed') {
                    $closed_status = $status;
                    break;
                }
            }
            
            if ($closed_status) {
                $ticket->setStatus($closed_status);
                
                if ($this->config->get('enable_logging')) {
                    error_log("AI Spam Closer - Successfully closed ticket #{$ticket->getNumber()} as spam");
                }
                
                return true;
            } else {
                if ($this->config->get('enable_logging')) {
                    error_log("AI Spam Closer - No closed status found");
                }
                return false;
            }
            
        } catch (Exception $e) {
            if ($this->config->get('enable_logging')) {
                error_log("AI Spam Closer - Close failed: " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Log a note when spam check cannot be performed
     * 
     * @param Ticket $ticket
     * @param string $reason Reason why check failed
     */
    public function logCheckFailure($ticket, $reason) {
        try {
            $note_title = 'Spam Check - Not Performed';
            $note_body = 'Automatic spam check was not performed.<br><br>Reason: ' . htmlspecialchars($reason);
            
            $ticket->logNote($note_title, $note_body, 'SYSTEM', false);
            
        } catch (Exception $e) {
            if ($this->config->get('enable_logging')) {
                error_log("AI Spam Closer - Failed to log check failure: " . $e->getMessage());
            }
        }
    }
}

