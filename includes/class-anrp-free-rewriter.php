<?php
/**
 * ANRP Free Rewriter - v3.0 com OpenRouter
 * Suporta múltiplos provedores: OpenRouter, Gemini direto, Básico
 */
class ANRP_Free_Rewriter {
    
    private $openrouter_endpoint = 'https://openrouter.ai/api/v1/chat/completions';
    private $gemini_endpoint = 'https://generativelanguage.googleapis.com/v1/models/';
    
    /**
     * Método principal de reescrita (compatibilidade)
     */
    public function rewrite_content($title, $content, $method = 'auto') {
        return $this->rewrite($title, $content, $method);
    }
    
    /**
     * Reescreve conteúdo usando o método configurado
     */
    public function rewrite($title, $content, $method = 'auto') {
        $clean_content = $this->clean_content($content);
        
        // Determinar método
        if ($method === 'auto') {
            $method = $this->detect_best_method();
        }
        
        switch ($method) {
            case 'openrouter':
                return $this->rewrite_with_openrouter($title, $clean_content);
            case 'gemini':
                return $this->rewrite_with_gemini($title, $clean_content);
            case 'basic':
            case 'sonnet':
            default:
                return $this->basic_rewrite($title, $clean_content);
        }
    }
    
    /**
     * Detecta o melhor método disponível
     */
    private function detect_best_method() {
        // Prioridade: OpenRouter > Gemini > Basic
        if (!empty(get_option('anrp_openrouter_key'))) {
            return 'openrouter';
        }
        if (!empty(get_option('anrp_gemini_key'))) {
            return 'gemini';
        }
        return 'basic';
    }
    
    /**
     * Reescrita via OpenRouter (múltiplos modelos)
     */
    private function rewrite_with_openrouter($title, $content) {
        $api_key = get_option('anrp_openrouter_key', '');
        $model = get_option('anrp_openrouter_model', 'google/gemini-2.0-flash-exp:free');
        
        if (empty($api_key)) {
            return $this->rewrite_with_gemini($title, $content);
        }
        
        $prompt = $this->build_prompt($title, $content);
        
        $body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => 1500
        ];
        
        $site_url = get_site_url();
        $site_name = get_bloginfo('name');
        
        try {
            $response = wp_remote_post($this->openrouter_endpoint, [
                'timeout' => 60,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                    'HTTP-Referer' => $site_url,
                    'X-Title' => $site_name
                ],
                'body' => wp_json_encode($body)
            ]);
            
            if (is_wp_error($response)) {
                error_log('ANRP OpenRouter Error: ' . $response->get_error_message());
                return $this->rewrite_with_gemini($title, $content);
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            $result = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($http_code !== 200) {
                $error_msg = isset($result['error']['message']) ? $result['error']['message'] : 'HTTP ' . $http_code;
                error_log('ANRP OpenRouter HTTP Error: ' . $error_msg);
                
                // Fallback para Gemini
                return $this->rewrite_with_gemini($title, $content);
            }
            
            // Extrair texto (formato OpenAI)
            $text = '';
            if (!empty($result['choices'][0]['message']['content'])) {
                $text = $result['choices'][0]['message']['content'];
            }
            
            if (empty($text)) {
                return $this->basic_rewrite($title, $content);
            }
            
            return $this->parse_ai_response($text, $title, $content);
            
        } catch (Exception $e) {
            error_log('ANRP OpenRouter Exception: ' . $e->getMessage());
            return $this->basic_rewrite($title, $content);
        }
    }
    
    /**
     * Reescrita via Google Gemini direto
     */
    private function rewrite_with_gemini($title, $content) {
        $api_key = get_option('anrp_gemini_key', '');
        $model = get_option('anrp_gemini_model', 'gemini-1.5-flash');
        
        if (empty($api_key)) {
            return $this->basic_rewrite($title, $content);
        }
        
        $prompt = $this->build_prompt($title, $content);
        
        $endpoint = $this->gemini_endpoint . urlencode($model) . ':generateContent?key=' . urlencode($api_key);
        
        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 1500
            ]
        ];
        
        try {
            $response = wp_remote_post($endpoint, [
                'timeout' => 60,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode($body)
            ]);
            
            if (is_wp_error($response)) {
                error_log('ANRP Gemini Error: ' . $response->get_error_message());
                return $this->basic_rewrite($title, $content);
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            $result = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($http_code !== 200) {
                error_log('ANRP Gemini HTTP Error: ' . $http_code . ' - ' . wp_json_encode($result));
                return $this->basic_rewrite($title, $content);
            }
            
            $text = '';
            if (!empty($result['candidates'][0]['content']['parts'][0]['text'])) {
                $text = $result['candidates'][0]['content']['parts'][0]['text'];
            }
            
            if (empty($text)) {
                return $this->basic_rewrite($title, $content);
            }
            
            return $this->parse_ai_response($text, $title, $content);
            
        } catch (Exception $e) {
            error_log('ANRP Gemini Exception: ' . $e->getMessage());
            return $this->basic_rewrite($title, $content);
        }
    }
    
    /**
     * Constrói o prompt para IA
     */
    private function build_prompt($title, $content) {
        $custom_prompt = get_option('anrp_gemini_prompt', '');
        
        if (empty($custom_prompt)) {
            $custom_prompt = 'Você é um editor jornalístico experiente do Brasil. Sua tarefa é REESCREVER completamente a notícia abaixo.

REGRAS OBRIGATÓRIAS:
1. O TÍTULO deve ser COMPLETAMENTE DIFERENTE do original - use sinônimos, mude a estrutura da frase
2. O conteúdo deve ter entre 4 a 6 parágrafos bem desenvolvidos
3. Cada parágrafo deve ter pelo menos 3 frases
4. Mantenha todas as informações factuais (nomes, datas, lugares)
5. Use vocabulário jornalístico brasileiro
6. NÃO copie frases do original - reescreva tudo com suas palavras
7. NÃO inclua o nome do veículo original no título ou texto

FORMATO DE RESPOSTA - JSON puro sem markdown:
{"title": "NOVO TÍTULO AQUI", "content": "Primeiro parágrafo aqui.\n\nSegundo parágrafo aqui.\n\nTerceiro parágrafo aqui.\n\nQuarto parágrafo aqui."}

TÍTULO ORIGINAL (REESCREVA DIFERENTE): {title}

CONTEÚDO ORIGINAL (REESCREVA COMPLETAMENTE):
{content}';
        }
        
        return str_replace(
            ['{title}', '{content}'],
            [$title, mb_substr($content, 0, 5000)],
            $custom_prompt
        );
    }
    
    /**
     * Processa resposta da IA
     */
    private function parse_ai_response($text, $original_title, $original_content) {
        error_log('ANRP Parse AI Response - Input length: ' . strlen($text));
        error_log('ANRP Parse AI Response - First 500 chars: ' . mb_substr($text, 0, 500));
        
        // Limpar markdown code blocks
        $text = preg_replace('/```json\s*/i', '', $text);
        $text = preg_replace('/```\s*/', '', $text);
        $text = trim($text);
        
        // Tentar encontrar JSON no texto (pode ter texto antes/depois)
        $json_text = $text;
        
        // Procurar por padrão JSON { ... }
        if (preg_match('/\{[^{}]*"title"[^{}]*"content"[^{}]*\}/s', $text, $matches)) {
            $json_text = $matches[0];
        } elseif (preg_match('/\{.*\}/s', $text, $matches)) {
            $json_text = $matches[0];
        }
        
        // Tentar decodificar JSON
        $decoded = json_decode($json_text, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('ANRP JSON Error: ' . json_last_error_msg());
            
            // Tentar corrigir JSON mal formado
            $json_text = $this->fix_json($json_text);
            $decoded = json_decode($json_text, true);
        }
        
        if (is_array($decoded) && (isset($decoded['title']) || isset($decoded['content']))) {
            $new_title = isset($decoded['title']) ? trim($decoded['title']) : $original_title;
            $new_content = isset($decoded['content']) ? trim($decoded['content']) : $original_content;
            
            // Garantir que o título foi realmente reescrito (diferente do original)
            if ($new_title === $original_title || empty($new_title)) {
                // Tentar gerar título a partir do conteúdo
                $new_title = $this->generate_title_from_content($new_content, $original_title);
            }
            
            error_log('ANRP Parse Success - New title: ' . mb_substr($new_title, 0, 100));
            error_log('ANRP Parse Success - Content length: ' . strlen($new_content));
            
            return [
                'title' => $new_title,
                'content' => $new_content,
                'keywords' => $this->extract_keywords($new_content)
            ];
        }
        
        error_log('ANRP Parse Fallback - Using heuristics');
        
        // Fallback: usar heurística
        return $this->extract_title_and_content($text, $original_title);
    }
    
    /**
     * Tenta corrigir JSON mal formado
     */
    private function fix_json($json) {
        // Remover caracteres de controle
        $json = preg_replace('/[\x00-\x1F\x7F]/u', '', $json);
        
        // Corrigir aspas curvas para aspas retas
        $json = str_replace(
            array("\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x98", "\xe2\x80\x99"),
            array('"', '"', "'", "'"),
            $json
        );
        
        // Corrigir quebras de linha dentro de strings
        $json = preg_replace('/\n(?=[^"]*"[^"]*$)/m', '\\n', $json);
        
        return $json;
    }
    
    /**
     * Gera título a partir do conteúdo
     */
    private function generate_title_from_content($content, $fallback) {
        if (empty($content)) return $fallback;
        
        // Pegar primeira frase
        $sentences = preg_split('/[.!?]+/', $content, 2);
        if (!empty($sentences[0])) {
            $title = trim($sentences[0]);
            // Limitar tamanho
            if (mb_strlen($title) > 100) {
                $title = mb_substr($title, 0, 97) . '...';
            }
            if (mb_strlen($title) > 20) {
                return $title;
            }
        }
        
        return $fallback;
    }
    
    /**
     * Limpar conteúdo HTML
     */
    private function clean_content($content) {
        if (empty($content)) return '';
        
        // Remover scripts, styles
        $content = preg_replace('#<script[^>]*>.*?</script>#si', ' ', $content);
        $content = preg_replace('#<style[^>]*>.*?</style>#si', ' ', $content);
        
        $content = wp_strip_all_tags($content);
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        $content = preg_replace('/\s+/', ' ', $content);
        return trim($content);
    }
    
    /**
     * Reescrita básica sem IA - Formata e estrutura o conteúdo
     */
    private function basic_rewrite($title, $content) {
        error_log('ANRP Basic Rewrite: Iniciando formatação local');
        
        // Sinônimos para variação
        $synonyms = [
            'importante' => ['relevante', 'significativo', 'crucial'],
            'grande' => ['enorme', 'vasto', 'expressivo'],
            'pequeno' => ['reduzido', 'menor', 'compacto'],
            'informou' => ['comunicou', 'relatou', 'anunciou'],
            'disse' => ['afirmou', 'declarou', 'comentou'],
            'segundo' => ['conforme', 'de acordo com', 'segundo informações'],
            'ainda' => ['também', 'além disso', 'adicionalmente'],
        ];
        
        // 1. Criar novo título (trocar algumas palavras e estrutura)
        $new_title = $this->generate_new_title($title, $synonyms);
        
        // 2. Dividir conteúdo em sentenças
        $sentences = $this->split_into_sentences($content);
        
        // 3. Agrupar em parágrafos (3-4 sentenças cada)
        $paragraphs = $this->group_into_paragraphs($sentences, 3);
        
        // 4. Aplicar sinônimos em cada parágrafo
        $formatted_paragraphs = [];
        $h2_count = 0;
        foreach ($paragraphs as $i => $para) {
            $para = $this->replace_with_synonyms($para, $synonyms);
            
            // Adicionar subtítulo H2 a cada 2 parágrafos (exceto primeiro)
            if ($i > 0 && $i % 2 === 0 && count($paragraphs) > 3) {
                $subtitle = $this->generate_subtitle($para, $h2_count);
                if ($subtitle) {
                    $formatted_paragraphs[] = '<h2>' . $subtitle . '</h2>';
                    $h2_count++;
                }
            }
            
            $formatted_paragraphs[] = '<p>' . trim($para) . '</p>';
        }
        
        $new_content = implode("\n\n", $formatted_paragraphs);
        
        error_log('ANRP Basic Rewrite: ' . count($paragraphs) . ' parágrafos criados');
        
        return [
            'title' => $new_title,
            'content' => $new_content,
            'keywords' => $this->extract_keywords($content)
        ];
    }
    
    /**
     * Gera novo título com variações
     */
    private function generate_new_title($title, $synonyms) {
        // Aplicar sinônimos
        $new_title = $this->replace_with_synonyms($title, $synonyms);
        
        // Se título ainda igual, tentar inverter estrutura
        if ($new_title === $title) {
            // Tentar dividir por : ou - e inverter
            if (strpos($title, ':') !== false) {
                $parts = explode(':', $title, 2);
                if (count($parts) === 2) {
                    $new_title = trim($parts[1]) . ': ' . trim($parts[0]);
                }
            } elseif (strpos($title, ' - ') !== false) {
                $parts = explode(' - ', $title, 2);
                if (count($parts) === 2) {
                    $new_title = trim($parts[1]) . ' - ' . trim($parts[0]);
                }
            }
        }
        
        // Adicionar prefixos de destaque se título pequeno
        if ($new_title === $title && mb_strlen($title) < 60) {
            $prefixes = ['Entenda:', 'Saiba mais:', 'Confira:', 'Destaque:'];
            $new_title = $prefixes[array_rand($prefixes)] . ' ' . $title;
        }
        
        return $new_title;
    }
    
    /**
     * Divide texto em sentenças
     */
    private function split_into_sentences($text) {
        // Dividir por pontuação final
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Filtrar sentenças muito curtas
        return array_filter($sentences, function($s) {
            return mb_strlen(trim($s)) > 30;
        });
    }
    
    /**
     * Agrupa sentenças em parágrafos
     */
    private function group_into_paragraphs($sentences, $sentences_per_para = 3) {
        $paragraphs = [];
        $current = [];
        
        foreach ($sentences as $sentence) {
            $current[] = trim($sentence);
            
            if (count($current) >= $sentences_per_para) {
                $paragraphs[] = implode(' ', $current);
                $current = [];
            }
        }
        
        // Adicionar resto
        if (!empty($current)) {
            $paragraphs[] = implode(' ', $current);
        }
        
        return $paragraphs;
    }
    
    /**
     * Gera subtítulo baseado no conteúdo do parágrafo
     */
    private function generate_subtitle($paragraph, $index) {
        // Subtítulos genéricos contextuais que fazem sentido jornalisticamente
        $subtitles = [
            'Entenda o Contexto',
            'O Que Dizem os Especialistas',
            'Detalhes da Situação',
            'Próximos Passos',
            'Impactos e Consequências',
            'Histórico do Caso',
            'Repercussão',
            'Análise do Cenário',
        ];
        
        // Usar índice para variar, mas de forma previsível
        $idx = $index % count($subtitles);
        return $subtitles[$idx];
    }
    
    private function replace_with_synonyms($text, $synonyms) {
        foreach ($synonyms as $word => $alternatives) {
            if (stripos($text, $word) !== false) {
                $replacement = $alternatives[array_rand($alternatives)];
                $text = preg_replace('/\b' . preg_quote($word, '/') . '\b/iu', $replacement, $text, 1);
            }
        }
        return $text;
    }
    
    private function extract_keywords($text) {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\s]/u', '', $text);
        $words = preg_split('/\s+/', $text);
        
        $stopwords = ['de', 'a', 'o', 'que', 'e', 'do', 'da', 'em', 'um', 'para', 'é', 'com', 'não', 'uma', 'os', 'no', 'se', 'na', 'por', 'mais', 'as', 'dos', 'como', 'mas', 'foi', 'ao', 'ele', 'das', 'tem', 'à', 'seu', 'sua', 'ou', 'ser', 'quando', 'muito', 'há', 'nos', 'já', 'está', 'eu', 'também', 'só', 'pelo', 'pela', 'até', 'isso', 'ela', 'entre', 'era', 'depois', 'sem', 'mesmo', 'aos', 'ter', 'seus', 'quem', 'nas', 'me', 'esse', 'eles', 'estão', 'você'];
        
        $words = array_filter($words, function($w) use ($stopwords) {
            return mb_strlen($w) > 3 && !in_array($w, $stopwords);
        });
        
        $freq = array_count_values($words);
        arsort($freq);
        
        return array_slice(array_keys($freq), 0, 10);
    }
    
    private function extract_title_and_content($text, $fallback_title) {
        $lines = explode("\n", trim($text));
        $title = $fallback_title;
        $content_lines = [];
        
        foreach ($lines as $i => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if ($i === 0 && mb_strlen($line) < 150 && mb_strlen($line) > 10) {
                $title = preg_replace('/^(título|title|#)+[:\s]*/i', '', $line);
                continue;
            }
            
            $content_lines[] = $line;
        }
        
        return [
            'title' => $title,
            'content' => implode("\n\n", $content_lines),
            'keywords' => $this->extract_keywords(implode(' ', $content_lines))
        ];
    }
}
