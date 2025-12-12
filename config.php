<?php

require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.forms.php');

/**
 * Custom field for Model selection that switches between dropdown and textbox
 */
class AISpamCloserModelField extends TextboxField {
    static $widget = 'AISpamCloserModelWidget';
}

class AISpamCloserModelWidget extends Widget {
    function render($options=array()) {
        $name = $this->name;
        $value = $this->value;

        // OpenAI models list
        $models = array(
            // GPT-5 series (latest)
            'gpt-5.2' => 'GPT-5.2 (Latest, improved reasoning)',
            'gpt-5.1' => 'GPT-5.1 (Coding & agentic tasks)',
            'gpt-5.1-codex' => 'GPT-5.1 Codex (Optimized for code)',
            'gpt-5.1-codex-mini' => 'GPT-5.1 Codex Mini',
            'gpt-5.1-codex-max' => 'GPT-5.1 Codex Max (Project-scale coding)',
            'gpt-5-mini' => 'GPT-5 Mini (Fast, 400K context)',
            'gpt-5-nano' => 'GPT-5 Nano (Fastest, cheapest)',
            // Reasoning models (o-series) - think longer before responding
            'o3' => 'o3 (Most advanced reasoning)',
            'o3-mini' => 'o3-mini (Cost-efficient reasoning)',
            'o4-mini' => 'o4-mini (Latest compact reasoning)',
            'o1' => 'o1 (Extended reasoning)',
            'o1-mini' => 'o1-mini (Compact reasoning)',
            // GPT-4.1 series - improved coding & long context
            'gpt-4.1' => 'GPT-4.1 (Best for coding, 1M context)',
            'gpt-4.1-mini' => 'GPT-4.1 Mini (Balanced)',
            'gpt-4.1-nano' => 'GPT-4.1 Nano (Fastest)',
            // GPT-4o series - multimodal
            'gpt-4o' => 'GPT-4o (Multimodal, capable)',
            'gpt-4o-mini' => 'GPT-4o Mini (Fast and affordable)',
            // Legacy models
            'gpt-4-turbo' => 'GPT-4 Turbo (Legacy)',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Cheapest, legacy)'
        );
        ?>
        <input type="hidden" name="<?php echo $name; ?>" id="model_value" value="<?php echo Format::htmlchars($value); ?>" />
        <select id="model_select" class="model-select-dropdown" style="width: 350px;">
            <?php foreach ($models as $model_id => $model_name): ?>
                <option value="<?php echo $model_id; ?>" <?php if ($value === $model_id) echo 'selected="selected"'; ?>>
                    <?php echo Format::htmlchars($model_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="text" id="model_text" class="model-text-input"
               value="<?php echo Format::htmlchars($value); ?>"
               placeholder="Enter model name (e.g., gpt-4o-mini)"
               style="width: 350px; padding: 5px;" />
        <script type="text/javascript"><?php readfile(__DIR__ . '/js/config-api-provider.js'); ?></script>
        <?php
    }
}

class AISpamCloserConfig extends PluginConfig {
    
    function getOptions() {
        return array(
            'api_provider' => new ChoiceField(array(
                'label' => __('API Provider'),
                'default' => 'openai',
                'choices' => array(
                    'openai' => 'Open AI',
                    'custom' => 'Custom'
                ),
                'hint' => __('Choose API provider type')
            )),
            'api_key' => new TextboxField(array(
                'label' => __('API Key'),
                'required' => false,
                'configuration' => array(
                    'size' => 60,
                    'length' => 500,
                    'placeholder' => 'sk-... (leave empty to use keywords only)'
                ),
                'hint' => __('Your API key. Leave empty to use only keyword matching. Get key for example from https://platform.openai.com/api-keys')
            )),
            'api_url' => new TextboxField(array(
                'label' => __('API URL'),
                'required' => false,
                'configuration' => array(
                    'size' => 60,
                    'length' => 500,
                    'placeholder' => 'https://api.example.com/v1/chat/completions'
                ),
                'hint' => __('Custom API endpoint URL (compatible with OpenAI)')
            )),
            'model' => new AISpamCloserModelField(array(
                'label' => __('Model Name'),
                'default' => 'gpt-4o-mini',
                'required' => true,
                'hint' => __('Select or enter the model name to use for analysis')
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

    function pre_save(&$config, &$errors) {
        $result = true;

        if ('openai' === $config['api_provider']) {
            $config['api_url'] = 'https://api.openai.com/v1/chat/completions';
        }

        if (empty($config['api_url'])) {
            $errors['api_url'] = __('API URL is required for Custom provider');
            $result = false;
        }

        return $result;
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

