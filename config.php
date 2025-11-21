<?php

require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.forms.php');

class AISpamCloserConfig extends PluginConfig {
    
    function getOptions() {
        return array(
            'api_key' => new TextboxField(array(
                'label' => __('OpenAI API Key (Optional)'),
                'required' => false,
                'configuration' => array(
                    'size' => 60,
                    'length' => 500,
                    'placeholder' => 'sk-... (leave empty to use keywords only)'
                ),
                'hint' => __('Your OpenAI API key for AI analysis. Leave empty to use only keyword matching. Get key from https://platform.openai.com/api-keys')
            )),
            'model' => new ChoiceField(array(
                'label' => __('OpenAI Model'),
                'default' => 'gpt-4o-mini',
                'choices' => array(
                    'gpt-4o' => 'GPT-4o (Most capable, expensive)',
                    'gpt-4o-mini' => 'GPT-4o Mini (Fast and affordable)',
                    'gpt-4-turbo' => 'GPT-4 Turbo',
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Cheapest)'
                ),
                'hint' => __('Choose the model to use for spam analysis')
            )),
            'timeout' => new TextboxField(array(
                'label' => __('API Timeout (seconds)'),
                'default' => '30',
                'required' => true,
                'validator' => 'number',
                'configuration' => array(
                    'size' => 10,
                    'length' => 3
                ),
                'hint' => __('Maximum time to wait for OpenAI response')
            )),
            'spam_keywords' => new TextareaField(array(
                'label' => __('Spam Keywords (Fallback)'),
                'required' => false,
                'default' => 'viagra, casino, lottery, winner, click here, buy now, limited offer, earn money fast, work from home, make money online, free money, get paid, amazing offer',
                'configuration' => array(
                    'rows' => 10,
                    'cols' => 60,
                    'html' => false,
                    'placeholder' => 'Enter keywords separated by comma or semicolon'
                ),
                'hint' => __('Fallback keywords if AI analysis fails. Use comma (,) or semicolon (;) as separators.')
            )),
            'close_reason' => new TextareaField(array(
                'label' => __('Close Reason Text'),
                'default' => 'This ticket has been automatically closed as spam based on content analysis.',
                'required' => true,
                'configuration' => array(
                    'rows' => 3,
                    'cols' => 60
                ),
                'hint' => __('Internal note text to add when closing spam tickets')
            )),
            'auto_close' => new BooleanField(array(
                'label' => __('Auto-close on ticket creation'),
                'default' => true,
                'configuration' => array(
                    'desc' => __('Automatically analyze and close new tickets if spam is detected')
                )
            )),
            'enable_logging' => new BooleanField(array(
                'label' => __('Enable Debug Logging'),
                'default' => false,
                'configuration' => array(
                    'desc' => __('Log processing details for debugging')
                )
            )),
            'max_file_size' => new TextboxField(array(
                'label' => __('Max File Size (MB)'),
                'default' => '10',
                'required' => true,
                'validator' => 'number',
                'configuration' => array(
                    'size' => 10,
                    'length' => 3
                ),
                'hint' => __('Maximum file size to process for text extraction')
            ))
        );
    }
    
    function getFormOptions() {
        return array(
            'title' => __('AI Spam Closer Configuration'),
            'instructions' => __('Configure automatic spam detection and closing based on keywords in subject, content, and attachments.')
        );
    }
    
    /**
     * Get parsed spam keywords
     * @return array
     */
    function getSpamKeywords() {
        $keywords = $this->get('spam_keywords');
        
        if ($this->get('enable_logging')) {
            error_log("AI Spam Closer Config - Raw keywords length: " . strlen($keywords));
            error_log("AI Spam Closer Config - Raw keywords (first 200 chars): " . substr($keywords, 0, 200));
        }
        
        if (empty($keywords)) {
            if ($this->get('enable_logging')) {
                error_log("AI Spam Closer Config - Keywords are empty");
            }
            return array();
        }
        
        // Parse keywords (comma or semicolon separated)
        $keyword_list = preg_split('/[,;]/', $keywords);
        $keyword_list = array_map('trim', $keyword_list);
        $keyword_list = array_filter($keyword_list); // Remove empty values
        
        if ($this->get('enable_logging')) {
            error_log("AI Spam Closer Config - Parsed " . count($keyword_list) . " keywords");
            foreach ($keyword_list as $idx => $kw) {
                error_log("AI Spam Closer Config - Keyword[$idx]: '" . $kw . "' (length: " . strlen($kw) . ")");
            }
        }
        
        return $keyword_list;
    }
}

