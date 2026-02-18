<?php
/**
 * ANRP AI Models Manager - v1.0
 * 
 * Gerencia modelos de IA customizados com suporte a OAuth
 * - Adicionar/remover modelos
 * - Configurar providers (OpenRouter, Gemini, OpenAI, Anthropic, Qwen, etc)
 * - Autenticação OAuth para providers que suportam
 */
class ANRP_AI_Models_Manager {
    
    private $models_option = 'anrp_custom_models';
    
    /**
     * Providers pré-configurados
     */
    public function get_default_providers() {
        return [
            'openrouter' => [
                'name' => 'OpenRouter',
                'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
                'auth_type' => 'api_key',
                'format' => 'openai',
                'models' => [
                    'google/gemini-2.0-flash-exp:free' => 'Gemini 2.0 Flash (Grátis)',
                    'meta-llama/llama-3.2-3b-instruct:free' => 'Llama 3.2 3B (Grátis)',
                    'qwen/qwen-2-7b-instruct:free' => 'Qwen 2 7B (Grátis)',
                    'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet',
                    'openai/gpt-4o' => 'GPT-4o',
                    'openai/gpt-4o-mini' => 'GPT-4o Mini'
                ]
            ],
            'gemini' => [
                'name' => 'Google Gemini',
                'endpoint' => 'https://generativelanguage.googleapis.com/v1/models/',
                'auth_type' => 'api_key',
                'format' => 'gemini',
                'models' => [
                    'gemini-1.5-flash' => 'Gemini 1.5 Flash',
                    'gemini-1.5-pro' => 'Gemini 1.5 Pro',
                    'gemini-2.0-flash-exp' => 'Gemini 2.0 Flash (Experimental)'
                ]
            ],
            'openai' => [
                'name' => 'OpenAI',
                'endpoint' => 'https://api.openai.com/v1/chat/completions',
                'auth_type' => 'api_key',
                'format' => 'openai',
                'models' => [
                    'gpt-4o' => 'GPT-4o',
                    'gpt-4o-mini' => 'GPT-4o Mini',
                    'gpt-4-turbo' => 'GPT-4 Turbo',
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo'
                ]
            ],
            'anthropic' => [
                'name' => 'Anthropic',
                'endpoint' => 'https://api.anthropic.com/v1/messages',
                'auth_type' => 'api_key',
                'format' => 'anthropic',
                'models' => [
                    'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
                    'claude-3-5-haiku-20241022' => 'Claude 3.5 Haiku',
                    'claude-3-opus-20240229' => 'Claude 3 Opus'
                ]
            ],
            'qwen' => [
                'name' => 'Alibaba Qwen',
                'endpoint' => 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions',
                'auth_type' => 'api_key',
                'format' => 'openai',
                'models' => [
                    'qwen-turbo' => 'Qwen Turbo',
                    'qwen-plus' => 'Qwen Plus',
                    'qwen-max' => 'Qwen Max'
                ]
            ],
            'groq' => [
                'name' => 'Groq',
                'endpoint' => 'https://api.groq.com/openai/v1/chat/completions',
                'auth_type' => 'api_key',
                'format' => 'openai',
                'models' => [
                    'llama-3.3-70b-versatile' => 'Llama 3.3 70B',
                    'llama-3.1-70b-versatile' => 'Llama 3.1 70B',
                    'mixtral-8x7b-32768' => 'Mixtral 8x7B'
                ]
            ]
        ];
    }
    
    /**
     * Obter todos os modelos configurados
     */
    public function get_all_models() {
        $custom = get_option($this->models_option, []);
        $defaults = $this->get_default_providers();
        
        $all_models = [];
        
        // Adicionar modelos default
        foreach ($defaults as $provider_id => $provider) {
            foreach ($provider['models'] as $model_id => $model_name) {
                $all_models[] = [
                    'id' => $provider_id . '::' . $model_id,
                    'provider' => $provider_id,
                    'provider_name' => $provider['name'],
                    'model_id' => $model_id,
                    'model_name' => $model_name,
                    'endpoint' => $provider['endpoint'],
                    'auth_type' => $provider['auth_type'],
                    'format' => $provider['format'],
                    'is_custom' => false,
                    'is_active' => $this->is_model_configured($provider_id)
                ];
            }
        }
        
        // Adicionar modelos customizados
        foreach ($custom as $custom_model) {
            $all_models[] = array_merge($custom_model, [
                'is_custom' => true,
                'is_active' => true
            ]);
        }
        
        return $all_models;
    }
    
    /**
     * Adicionar modelo customizado
     */
    public function add_custom_model($data) {
        $custom = get_option($this->models_option, []);
        
        $model = [
            'id' => 'custom_' . time() . '_' . rand(1000, 9999),
            'provider' => sanitize_text_field($data['provider'] ?? 'custom'),
            'provider_name' => sanitize_text_field($data['provider_name'] ?? 'Custom Provider'),
            'model_id' => sanitize_text_field($data['model_id'] ?? ''),
            'model_name' => sanitize_text_field($data['model_name'] ?? ''),
            'endpoint' => esc_url_raw($data['endpoint'] ?? ''),
            'auth_type' => sanitize_text_field($data['auth_type'] ?? 'api_key'),
            'format' => sanitize_text_field($data['format'] ?? 'openai'),
            'api_key' => sanitize_text_field($data['api_key'] ?? ''),
            'oauth_token' => '', // Será preenchido via OAuth
            'oauth_refresh_token' => '',
            'oauth_expires' => '',
            'custom_headers' => $data['custom_headers'] ?? []
        ];
        
        $custom[] = $model;
        update_option($this->models_option, $custom);
        
        return $model;
    }
    
    /**
     * Remover modelo customizado
     */
    public function remove_custom_model($model_id) {
        $custom = get_option($this->models_option, []);
        
        $custom = array_filter($custom, function($model) use ($model_id) {
            return $model['id'] !== $model_id;
        });
        
        update_option($this->models_option, array_values($custom));
        
        return true;
    }
    
    /**
     * Atualizar modelo customizado
     */
    public function update_custom_model($model_id, $data) {
        $custom = get_option($this->models_option, []);
        
        foreach ($custom as $key => $model) {
            if ($model['id'] === $model_id) {
                $custom[$key] = array_merge($model, [
                    'provider_name' => sanitize_text_field($data['provider_name'] ?? $model['provider_name']),
                    'model_name' => sanitize_text_field($data['model_name'] ?? $model['model_name']),
                    'endpoint' => esc_url_raw($data['endpoint'] ?? $model['endpoint']),
                    'api_key' => isset($data['api_key']) ? sanitize_text_field($data['api_key']) : $model['api_key'],
                    'custom_headers' => $data['custom_headers'] ?? $model['custom_headers']
                ]);
                break;
            }
        }
        
        update_option($this->models_option, $custom);
        
        return true;
    }
    
    /**
     * Obter modelo por ID
     */
    public function get_model($full_id) {
        $all_models = $this->get_all_models();
        
        foreach ($all_models as $model) {
            if ($model['id'] === $full_id) {
                return $model;
            }
        }
        
        return null;
    }
    
    /**
     * Verificar se provider está configurado
     */
    private function is_model_configured($provider_id) {
        $key = get_option("anrp_{$provider_id}_key", '');
        return !empty($key);
    }
    
    /**
     * Fazer chamada de API com modelo específico
     */
    public function make_request($model_id, $prompt, $max_tokens = 1500) {
        $model = $this->get_model($model_id);
        
        if (!$model) {
            throw new Exception('Modelo não encontrado');
        }
        
        switch ($model['format']) {
            case 'openai':
                return $this->request_openai_format($model, $prompt, $max_tokens);
            case 'gemini':
                return $this->request_gemini_format($model, $prompt, $max_tokens);
            case 'anthropic':
                return $this->request_anthropic_format($model, $prompt, $max_tokens);
            default:
                throw new Exception('Formato de API não suportado');
        }
    }
    
    /**
     * Request formato OpenAI (OpenRouter, OpenAI, Groq, Qwen, etc)
     */
    private function request_openai_format($model, $prompt, $max_tokens) {
        $api_key = $model['is_custom'] ? $model['api_key'] : get_option("anrp_{$model['provider']}_key", '');
        
        if (empty($api_key)) {
            throw new Exception('API Key não configurada para ' . $model['provider_name']);
        }
        
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ];
        
        // Headers customizados adicionais
        if (!empty($model['custom_headers'])) {
            $headers = array_merge($headers, $model['custom_headers']);
        }
        
        // OpenRouter específico
        if ($model['provider'] === 'openrouter') {
            $headers['HTTP-Referer'] = get_site_url();
            $headers['X-Title'] = get_bloginfo('name');
        }
        
        $body = [
            'model' => $model['model_id'],
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.3,
            'max_tokens' => $max_tokens
        ];
        
        $response = wp_remote_post($model['endpoint'], [
            'timeout' => 60,
            'headers' => $headers,
            'body' => wp_json_encode($body)
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Erro na requisição: ' . $response->get_error_message());
        }
        
        $result = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            $error_msg = $result['error']['message'] ?? "HTTP {$status_code}";
            throw new Exception('Erro da API: ' . $error_msg);
        }
        
        return $result['choices'][0]['message']['content'] ?? '';
    }
    
    /**
     * Request formato Gemini
     */
    private function request_gemini_format($model, $prompt, $max_tokens) {
        $api_key = $model['is_custom'] ? $model['api_key'] : get_option("anrp_{$model['provider']}_key", '');
        
        if (empty($api_key)) {
            throw new Exception('API Key não configurada para ' . $model['provider_name']);
        }
        
        $endpoint = $model['endpoint'] . urlencode($model['model_id']) . ':generateContent?key=' . urlencode($api_key);
        
        $body = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => $max_tokens
            ]
        ];
        
        $response = wp_remote_post($endpoint, [
            'timeout' => 60,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($body)
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Erro na requisição: ' . $response->get_error_message());
        }
        
        $result = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            throw new Exception('Erro da API Gemini: ' . ($result['error']['message'] ?? "HTTP {$status_code}"));
        }
        
        return $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }
    
    /**
     * Request formato Anthropic
     */
    private function request_anthropic_format($model, $prompt, $max_tokens) {
        $api_key = $model['is_custom'] ? $model['api_key'] : get_option("anrp_{$model['provider']}_key", '');
        
        if (empty($api_key)) {
            throw new Exception('API Key não configurada para ' . $model['provider_name']);
        }
        
        $headers = [
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01'
        ];
        
        $body = [
            'model' => $model['model_id'],
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => $max_tokens
        ];
        
        $response = wp_remote_post($model['endpoint'], [
            'timeout' => 60,
            'headers' => $headers,
            'body' => wp_json_encode($body)
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Erro na requisição: ' . $response->get_error_message());
        }
        
        $result = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            throw new Exception('Erro da API Anthropic: ' . ($result['error']['message'] ?? "HTTP {$status_code}"));
        }
        
        return $result['content'][0]['text'] ?? '';
    }
    
    /**
     * Iniciar fluxo OAuth (para providers que suportam)
     */
    public function initiate_oauth($provider) {
        // Configurações OAuth por provider
        $oauth_configs = [
            'qwen' => [
                'auth_url' => 'https://account.aliyun.com/oauth2/v1/authorize',
                'client_id' => get_option('anrp_qwen_client_id'),
                'redirect_uri' => admin_url('admin.php?page=anrp-models&oauth=callback&provider=qwen'),
                'scope' => 'dashscope'
            ]
        ];
        
        if (!isset($oauth_configs[$provider])) {
            return new WP_Error('oauth_not_supported', 'OAuth não suportado para este provider');
        }
        
        $config = $oauth_configs[$provider];
        
        if (empty($config['client_id'])) {
            return new WP_Error('oauth_not_configured', 'OAuth não configurado. Configure Client ID primeiro.');
        }
        
        $state = wp_create_nonce('anrp_oauth_' . $provider);
        update_option('anrp_oauth_state_' . $provider, $state, false);
        
        $auth_url = add_query_arg([
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => $config['scope'],
            'state' => $state
        ], $config['auth_url']);
        
        return $auth_url;
    }
    
    /**
     * Processar callback OAuth
     */
    public function process_oauth_callback($provider, $code, $state) {
        $saved_state = get_option('anrp_oauth_state_' . $provider);
        
        if ($state !== $saved_state) {
            return new WP_Error('invalid_state', 'State inválido');
        }
        
        delete_option('anrp_oauth_state_' . $provider);
        
        // Trocar code por token (implementar por provider)
        // ...
        
        return true;
    }
}
