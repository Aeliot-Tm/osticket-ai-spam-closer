<?php

require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.signal.php');
require_once(INCLUDE_DIR . 'class.dispatcher.php');
require_once('class.ai-spam-closer-plugin.php');
require_once('class.api-client.php');
require_once('class.spam-analyzer.php');
require_once('config.php');

/**
 * @return AISpamCloserPlugin
 */
function get_plugin_ai_spam_closer() {
    // Get plugin instance
    $plugin = null;
    $installed_plugins = PluginManager::allInstalled();

    foreach ($installed_plugins as $path => $info) {
        if (is_object($info)) {
            $manifest = isset($info->info) ? $info->info : array();
            if (isset($manifest['id']) && $manifest['id'] == 'osticket:ai-spam-closer') {
                $plugin = $info;
                break;
            }
        } elseif (is_array($info)) {
            if (isset($info['id']) && $info['id'] == 'osticket:ai-spam-closer') {
                $plugin = PluginManager::getInstance($path);
                break;
            }
        }
    }

    if (!$plugin) {
        $plugin = PluginManager::getInstance('plugins/ai-spam-closer');
    }

    if (!$plugin || !is_a($plugin, 'Plugin')) {
        throw new DomainException('Plugin instance not found');
    }

    return $plugin;
}

// --- ГЛОБАЛЬНЫЕ ФУНКЦИИ-ОБРАБОТЧИКИ ---

function ai_spam_closer_handle_analyze() {
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
            Http::response(200, json_encode(array(
                'success' => false,
                'error' => 'FATAL ERROR: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']
            )), 'application/json');
        }
    });

    global $thisstaff;
    
    if (!$thisstaff) {
        Http::response(403, 'Access Denied');
        return;
    }
    
    $ticket_id = $_POST['ticket_id'] ?? $_GET['ticket_id'] ?? null;
    
    if (!$ticket_id) {
        Http::response(400, json_encode(array('success' => false, 'error' => 'Ticket ID required')), 'application/json');
        return;
    }
    
    try {
        $ticket = Ticket::lookup($ticket_id);
        if (!$ticket) {
            Http::response(400, json_encode(array(
                'success' => false,
                'error' => 'Ticket not found'
            )), 'application/json');
        }
        
        if (!class_exists('AISpamCloserAnalyzer')) {
            throw new Exception('Class AISpamCloserAnalyzer not found');
        }

        //Get active instance config
        $result = get_plugin_ai_spam_closer()->tryCloseTicket($ticket);
        
        Http::response(200, json_encode($result), 'application/json');
        
    } catch (Throwable $e) {
        Http::response(200, json_encode(array(
            'success' => false, 
            'error' => 'EXCEPTION: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        )), 'application/json');
    }
}

