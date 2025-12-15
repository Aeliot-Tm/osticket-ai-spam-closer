<?php

class AISpamCloserPlugin extends Plugin {
    var $config_class = 'AISpamCloserConfig';
    
    function bootstrap() {
        // Register signal handlers
        Signal::connect('ticket.created', array($this, 'onTicketCreated'));
        Signal::connect('object.view', array($this, 'onObjectView'));
        Signal::connect('ajax.scp', array($this, 'registerAjax'));
    }

    /**
     * @param array<string,mixed> $defaults
     *
     * @return AISpamCloserConfig
     */
    function getConfig(PluginInstance $instance = null, $defaults = [])
    {
        $config = null;
        if(null === $instance) {
            foreach ($this->getInstances() as $existingInstance) {
                if ($existingInstance->isEnabled()) {
                    $config = $existingInstance->getConfig();
                    break;
                }
            }
        }

        return $config ?? parent::getConfig($instance, $defaults);
    }

    /**
     * Handle ticket.created signal for automatic processing
     *
     * @param \Ticket $ticket
     */
    function onTicketCreated($ticket) {
        $config = $this->getConfig();
        
        // Check if auto-close is enabled
        if (!$config->get('auto_close')) {
            if ($config->get('enable_logging')) {
                error_log("AI Spam Closer - Auto-close disabled, skipping ticket #" . $ticket->getNumber());
            }
            return;
        }
        
        try {
            if ($config->get('enable_logging')) {
                error_log("AI Spam Closer - Processing new ticket #" . $ticket->getNumber());
            }
            
            $this->tryCloseTicket($ticket);
            
        } catch (Exception $e) {
            if ($config->get('enable_logging')) {
                error_log("AI Spam Closer - Exception processing ticket: " . $e->getMessage());
            }
        }
    }
    
    /**
     * @param Ticket $ticket
     * @return array
     */
    public function tryCloseTicket($ticket)
    {
        $config = $this->getConfig();
        
        // Analyze ticket
        $analyzer = new AISpamCloserAnalyzer($config);
        $result = $analyzer->analyzeTicket($ticket);
        
        if ($result['success'] && isset($result['is_spam']) && $result['is_spam']) {
            // Close ticket
            $result = array_merge($result, $analyzer->closeTicket(
                $ticket,
                $result['reason'],
                $result['analyzed_files'] ?? array(),
                $result['ignored_files'] ?? array()
            ));
        }
        
        if ($result['success'] && isset($result['is_spam']) && !$result['is_spam']) {
            $result = array_merge($result, ['closed' => false, 'message' => 'No spam keywords detected']);
            $analyzer->logCheckFailure(
                $ticket,
                'No spam keywords detected',
                $result['analyzed_files'] ?? array(),
                $result['ignored_files'] ?? array()
            );
        }
        
        return $result;
    }
    
    /**
     * Register AJAX endpoints
     */
    function registerAjax($dispatcher, $data=null) {
        $dispatcher->append(
            url_post('^/ai-spam-closer/analyze', 'ai_spam_closer_handle_analyze')
        );
    }
    
    /**
     * Inject assets when viewing a ticket
     */
    function onObjectView($object, $type=null) {
        if ($object && is_a($object, 'Ticket')) {
            $this->loadAssets($object);
        }
    }
    
    /**
     * Load CSS and JavaScript assets for ticket view
     */
    function loadAssets($object) {
        $config = $this->getConfig();
        $path = dirname(__FILE__);
        
        // Load CSS
        echo '<style type="text/css">';
        @readfile($path . '/css/ai-spam-closer.css');
        echo '</style>';
        
        // Pass config to JavaScript
        echo '<script type="text/javascript">
            var AI_SPAM_CLOSER_CONFIG = {
                ajax_url: "ajax.php/ai-spam-closer",
                ticket_id: ' . $object->getId() . ',
                enable_logging: ' . ($config->get('enable_logging') ? 'true' : 'false') . '
            };
        </script>';
        
        // Load JavaScript
        echo '<script type="text/javascript">';
        @readfile($path . '/js/spam-closer.js');
        echo '</script>';
    }
}

