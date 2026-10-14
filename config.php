<?php

require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.forms.php');
require_once(__DIR__ . '/class.api-client.php');

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
        $models = AISpamCloserModelCapabilities::MODELS;
        ?>
        <input type="hidden" name="<?php echo $name; ?>" id="model_value" value="<?php echo Format::htmlchars($value); ?>" />
        <select id="model_select" class="model-select-dropdown" style="width: 350px;">
            <?php foreach ($models as $model_id => $model_name): ?>
                <option value="<?php echo $model_id; ?>" <?php if ($value === $model_id) echo 'selected="selected"'; ?> <?php if (AISpamCloserModelCapabilities::responsesOnly($model_id)) echo 'disabled="disabled"'; ?>>
                    <?php echo Format::htmlchars($model_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="text" id="model_text" class="model-text-input"
               value="<?php echo Format::htmlchars($value); ?>"
               placeholder="Enter model name (e.g., gpt-6-luna)"
               style="width: 350px; padding: 5px;" />
        <script type="text/javascript"><?php readfile(__DIR__ . '/js/config-api-provider.js'); ?></script>
        <?php
    }
}

class AISpamCloserVisionModelField extends TextboxField {
    static $widget = 'AISpamCloserVisionModelWidget';
}

class AISpamCloserVisionModelWidget extends Widget {
    function render($options=array()) {
        $name = $this->name;
        $value = $this->value;
        $input_id = 'vision_model_text_' . uniqid();
        $datalist_id = $input_id . '_datalist';
        ?>
        <input type="text"
               id="<?php echo $input_id; ?>"
               name="<?php echo $name; ?>"
               class="vision-model-text-input"
               list="<?php echo $datalist_id; ?>"
               value="<?php echo Format::htmlchars($value); ?>"
               placeholder="Enter vision model (e.g., gpt-6-luna)"
               style="width: 350px; padding: 5px;" />
        <datalist id="<?php echo $datalist_id; ?>"></datalist>
        <script type="text/javascript"><?php readfile(__DIR__ . '/js/config-vision-model-autocomplete.js'); ?></script>
        <script type="text/javascript">
            (function() {
                if (window.AIADTVisionModelAutocomplete && typeof window.AIADTVisionModelAutocomplete.setup === 'function') {
                    window.AIADTVisionModelAutocomplete.setup('<?php echo $input_id; ?>', '<?php echo $datalist_id; ?>');
                }
            })();
        </script>
        <?php
    }
}

class AISpamCloserConfig extends PluginConfig {
    
    function getOptions() {
        return array(
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
                'default' => 'gpt-6-luna',
                'required' => true,
                'hint' => __('Select or enter the model name to use for analysis')
            )),
            'reasoning_effort' => new ChoiceField(array(
                'label' => __('Reasoning Effort'),
                'default' => 'none',
                'configuration' => array(
                    'classes' => 'reasoning-effort-select',
                    'data' => array(
                        'reasoning-rules' => json_encode(AISpamCloserModelCapabilities::REASONING_RULES),
                        'unavailable-hint' => __('Reasoning configuration is not available for the selected models.')
                    )
                ),
                'choices' => array(
                    'none' => __('None'),
                    'minimal' => __('Minimal'),
                    'low' => __('Low'),
                    'medium' => __('Medium'),
                    'high' => __('High'),
                    'xhigh' => __('Extra High'),
                    'max' => __('Maximum')
                ),
                'hint' => __('Reasoning level for analysis and image text extraction with supported models. Higher levels may increase response time and cost.')
            )),
            'vision_model' => new AISpamCloserVisionModelField(array(
                'label' => __('Vision Model'),
                'default' => 'gpt-6-luna',
                'required' => false,
                'hint' => __('Optional: Vision-capable model for image text extraction (e.g., gpt-6-luna). Autocomplete suggests common OpenAI vision models, but any model name is allowed.')
            )),
            'temperature' => new TextboxField(array(
                'label' => __('Temperature'),
                'default' => '0.3',
                'required' => false,
                'configuration' => array(
                    'size' => 10,
                    'length' => 4
                ),
                'hint' => __('Advanced: Controls response randomness (0.0-2.0). Default: 0.3. For supported reasoning models, used only when Reasoning Effort is None.')
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
            )),
            'show_files_info' => new BooleanField(array(
                'label' => __('Show processed files info'),
                'default' => false,
                'configuration' => array(
                    'desc' => __('Add analyzed file contents and/or names of ignored files to the spam detection note (debug mode)')
                )
            )),
            'enable_logging' => new BooleanField(array(
                'label' => __('Enable Debug Logging'),
                'default' => false,
                'configuration' => array(
                    'desc' => __('Log processing details for debugging')
                )
            )),
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

        if (isset($config['temperature'])) {
            $config['temperature'] = (float) $config['temperature'];
            if (0.0 > $config['temperature'] || $config['temperature'] > 2.0) {
                $errors['temperature'] = __('Value is out of range');
                $result = false;
            }
        }

        $efforts = AISpamCloserModelCapabilities::commonReasoningEfforts(
            (string) ($config['model'] ?? ''),
            (string) ($config['vision_model'] ?? '')
        );
        $config['reasoning_effort'] = $config['reasoning_effort'] ?? ($efforts[0] ?? 'none');
        if (!in_array($config['reasoning_effort'], array('none', 'minimal', 'low', 'medium', 'high', 'xhigh', 'max'), true)) {
            $errors['reasoning_effort'] = __('Invalid reasoning effort');
            $result = false;
        } elseif ($efforts && !in_array($config['reasoning_effort'], $efforts, true)) {
            $errors['reasoning_effort'] = __('This reasoning effort is not supported by the selected models.');
            $result = false;
        }

        return $result;
    }
    
    /**
     * Get parsed spam keywords
     * @return string[]
     */
    function getSpamKeywords() {
        return array_filter(array_map('trim', preg_split('/[,;]/', (string) $this->get('spam_keywords', ''))));
    }
}

