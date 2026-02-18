<?php
/**
 * ANRP Scraper Enhanced - v4.0
 * Sistema avançado de extração com anti-bloqueio e múltiplos formatos
 */
class ANRP_Scraper {
    
    private $user_agents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
    ];
    
    private $max_retries = 3;
    private $retry_delay = 2;
    private $cache_manager;
    
    public function __construct() {
        // Integrar com sistema de cache se disponível
        if (class_exists('ANRP_Cache_Manager')) {
            $this->cache_manager = new ANRP_Cache_Manager();
        }
    }
    
    public function extract_content($url) {
        error_log('ANRP Scraper Enhanced: Iniciando extração de ' . $url);
        
        // Verificar cache primeiro
        if ($this->cache_manager) {
            $cached = $this->cache_manager->get('scraper_' . md5($url));
            if ($cached) {
                error_log('ANRP Scraper: Retornando do cache');
                return $cached;
            }
        }
        
        $content_type = $this->detect_content_type($url);
        
        try {
            switch ($content_type) {
                case 'rss':
                    $result = $this->extract_from_rss($url);
                    break;
                case 'json':
                    $result = $this->extract_from_json($url);
                    break;
                default:
                    $result = $this->extract_from_html($url);
            }
            
            // Armazenar no cache
            if ($this->cache_manager && !empty($result)) {
                $this->cache_manager->set('scraper_' . md5($url), $result, 3600); // 1 hora
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log('ANRP Scraper Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function detect_content_type($url) {
        $url_lower = strtolower($url);
        
        if (strpos($url_lower, '/feed') !== false || strpos($url_lower, '.xml') !== false || 
            strpos($url_lower, '/rss') !== false || strpos($url_lower, '/atom') !== false) {
            return 'rss';
        }
        
        if (strpos($url_lower, '/api/') !== false || strpos($url_lower, '.json') !== false) {
            return 'json';
        }
        
        return 'html';
    }
    
    private function extract_from_rss($url) {
        error_log('ANRP Scraper: Detectado RSS feed');
        
        $response = $this->fetch_with_retry($url, [
            'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml'
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Erro ao acessar RSS: ' . $response->get_error_message());
        }
        
        $xml = wp_remote_retrieve_body($response);
        
        libxml_use_internal_errors(true);
        $rss = simplexml_load_string($xml);
        libxml_clear_errors();
        
        if (!$rss) {
            throw new Exception('XML inválido ou malformado');
        }
        
        // RSS 2.0
        if (isset($rss->channel->item[0])) {
            $item = $rss->channel->item[0];
            return [
                'title' => (string) $item->title,
                'content' => strip_tags((string) ($item->description ?? $item->content ?? '')),
                'meta' => [
                    'description' => (string) ($item->description ?? ''),
                    'pubdate' => (string) ($item->pubDate ?? '')
                ],
                'main_image' => $this->extract_image_from_rss($item),
                'main_image_caption' => '',
                'main_image_credit' => '',
                'url' => (string) $item->link
            ];
        }
        
        // Atom
        if (isset($rss->entry[0])) {
            $entry = $rss->entry[0];
            return [
                'title' => (string) $entry->title,
                'content' => strip_tags((string) ($entry->summary ?? $entry->content ?? '')),
                'meta' => [
                    'description' => (string) ($entry->summary ?? ''),
                    'updated' => (string) ($entry->updated ?? '')
                ],
                'main_image' => $this->extract_image_from_atom($entry),
                'main_image_caption' => '',
                'main_image_credit' => '',
                'url' => (string) $entry->link['href']
            ];
        }
        
        throw new Exception('Feed RSS não contém itens válidos');
    }
    
    private function extract_image_from_rss($item) {
        if (isset($item->enclosure['url']) && strpos($item->enclosure['type'], 'image') !== false) {
            return (string) $item->enclosure['url'];
        }
        
        if (isset($item->children('media', true)->thumbnail)) {
            return (string) $item->children('media', true)->thumbnail['url'];
        }
        
        if (isset($item->children('media', true)->content)) {
            return (string) $item->children('media', true)->content['url'];
        }
        
        $content = (string) ($item->description ?? $item->content ?? '');
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $content, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    private function extract_image_from_atom($entry) {
        foreach ($entry->link as $link) {
            if ((string)$link['rel'] === 'enclosure' && strpos((string)$link['type'], 'image') !== false) {
                return (string) $link['href'];
            }
        }
        
        $content = (string) ($entry->summary ?? $entry->content ?? '');
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $content, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    private function extract_from_json($url) {
        error_log('ANRP Scraper: Detectado JSON API');
        
        $response = $this->fetch_with_retry($url, ['Accept' => 'application/json']);
        
        if (is_wp_error($response)) {
            throw new Exception('Erro ao acessar API: ' . $response->get_error_message());
        }
        
        $json = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!$json) {
            throw new Exception('JSON inválido');
        }
        
        $title = $json['title'] ?? $json['headline'] ?? $json['name'] ?? '';
        $content = $json['content'] ?? $json['body'] ?? $json['description'] ?? $json['text'] ?? '';
        $image = $json['image'] ?? $json['featured_image'] ?? $json['thumbnail'] ?? null;
        
        if (is_array($image)) {
            $image = $image['url'] ?? $image['src'] ?? $image[0] ?? null;
        }
        
        return [
            'title' => $title,
            'content' => strip_tags($content),
            'meta' => $json['meta'] ?? [],
            'main_image' => $image,
            'main_image_caption' => $json['image_caption'] ?? '',
            'main_image_credit' => $json['image_credit'] ?? '',
            'url' => $url
        ];
    }
    
    private function extract_from_html($url) {
        $html = null;
        $last_error = null;
        
        // Estratégia 1: Fetch normal
        try {
            $response = $this->fetch_with_retry($url);
            
            if (!is_wp_error($response)) {
                $html = wp_remote_retrieve_body($response);
                
                if ($this->is_blocked($html)) {
                    error_log('ANRP Scraper: Bloqueio detectado na tentativa 1');
                    $html = null;
                }
            }
        } catch (Exception $e) {
            $last_error = $e->getMessage();
        }
        
        // Estratégia 2: Headers alternativos
        if (!$html) {
            try {
                $response = $this->fetch_with_retry($url, [
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'DNT' => '1',
                    'Upgrade-Insecure-Requests' => '1'
                ]);
                
                if (!is_wp_error($response)) {
                    $html = wp_remote_retrieve_body($response);
                    
                    if ($this->is_blocked($html)) {
                        error_log('ANRP Scraper: Bloqueio detectado na tentativa 2');
                        $html = null;
                    }
                }
            } catch (Exception $e) {
                $last_error = $e->getMessage();
            }
        }
        
        // Estratégia 3: Versão AMP
        if (!$html) {
            $amp_url = $this->get_amp_url($url);
            if ($amp_url !== $url) {
                try {
                    $response = $this->fetch_with_retry($amp_url);
                    if (!is_wp_error($response)) {
                        $html = wp_remote_retrieve_body($response);
                        error_log('ANRP Scraper: Sucesso com versão AMP');
                    }
                } catch (Exception $e) {
                    $last_error = $e->getMessage();
                }
            }
        }
        
        if (empty($html)) {
            throw new Exception($last_error ?? 'Não foi possível extrair o conteúdo. O site pode estar bloqueando ou o formato não é suportado.');
        }
        
        if (strlen($html) < 500) {
            throw new Exception('Página retornou conteúdo muito curto ou vazio');
        }
        
        return $this->parse_html($html, $url);
    }
    
    private function fetch_with_retry($url, $extra_headers = []) {
        $attempt = 0;
        $last_error = null;
        
        while ($attempt < $this->max_retries) {
            $attempt++;
            
            $user_agent = $this->user_agents[array_rand($this->user_agents)];
            
            $headers = array_merge([
                'User-Agent' => $user_agent,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive'
            ], $extra_headers);
            
            error_log("ANRP Scraper: Tentativa {$attempt}/{$this->max_retries} para {$url}");
            
            $response = wp_remote_get($url, [
                'timeout' => 30,
                'httpversion' => '1.1',
                'headers' => $headers,
                'sslverify' => false,
                'redirection' => 5
            ]);
            
            if (!is_wp_error($response)) {
                $status_code = wp_remote_retrieve_response_code($response);
                
                if ($status_code >= 200 && $status_code < 300) {
                    error_log("ANRP Scraper: Sucesso na tentativa {$attempt}");
                    return $response;
                }
                
                if ($status_code >= 500) {
                    $last_error = new WP_Error('server_error', "Erro do servidor: HTTP {$status_code}");
                } else {
                    $last_error = new WP_Error('http_error', "HTTP {$status_code}");
                }
            } else {
                $last_error = $response;
            }
            
            if ($attempt < $this->max_retries) {
                $delay = $this->retry_delay * pow(2, $attempt - 1);
                error_log("ANRP Scraper: Aguardando {$delay}s antes da próxima tentativa");
                sleep($delay);
            }
        }
        
        return $last_error;
    }
    
    private function is_blocked($html) {
        if (empty($html)) return true;
        
        $blocked_indicators = [
            'captcha', 'cloudflare', 'access denied', 'you have been blocked',
            'forbidden', '403 forbidden', 'bot detected', 'unusual traffic',
            'verificação de segurança', 'blocked by', 'protection by'
        ];
        
        $html_lower = strtolower($html);
        
        foreach ($blocked_indicators as $indicator) {
            if (strpos($html_lower, $indicator) !== false) {
                return true;
            }
        }
        
        if (strlen($html) < 1000 && preg_match('/<title>.*?(403|blocked|denied|forbidden).*?<\/title>/i', $html)) {
            return true;
        }
        
        return false;
    }
    
    private function get_amp_url($url) {
        if (strpos($url, '/amp/') !== false || strpos($url, '/amp') !== false) {
            return $url;
        }
        
        $parsed = parse_url($url);
        $path = rtrim($parsed['path'] ?? '', '/');
        
        return ($parsed['scheme'] ?? 'https') . '://' . $parsed['host'] . $path . '/amp/';
    }
    
    private function parse_html($html, $url) {
        $html = $this->pre_clean_html($html);
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $this->remove_unwanted_elements($dom);
        
        $title = $this->extract_title($dom);
        $content = $this->extract_article_content($dom);
        $meta = $this->extract_meta($dom);
        $image_info = $this->extract_main_image($dom, $url);
        
        error_log('ANRP Scraper: Título: ' . mb_substr($title, 0, 50) . ' | Conteúdo: ' . strlen($content) . ' chars');
        
        return [
            'title' => $title,
            'content' => $content,
            'meta' => $meta,
            'main_image' => $image_info['url'] ?? null,
            'main_image_caption' => $image_info['caption'] ?? '',
            'main_image_credit' => $image_info['credit'] ?? '',
            'url' => $url
        ];
    }
    
    private function pre_clean_html($html) {
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
        
        $patterns = [
            '/<aside[^>]*>.*?<\/aside>/si',
            '/<nav[^>]*>.*?<\/nav>/si',
            '/<footer[^>]*>.*?<\/footer>/si',
            '/<div[^>]*class="[^"]*sidebar[^"]*"[^>]*>.*?<\/div>/si',
            '/<div[^>]*class="[^"]*widget[^"]*"[^>]*>.*?<\/div>/si'
        ];
        
        foreach ($patterns as $pattern) {
            $html = preg_replace($pattern, '', $html);
        }
        
        return $html;
    }
    
    private function remove_unwanted_elements($dom) {
        $xpath = new DOMXPath($dom);
        
        $tags_to_remove = ['script', 'style', 'noscript', 'iframe', 'svg', 'nav', 'aside', 'footer'];
        foreach ($tags_to_remove as $tag) {
            $nodes = $dom->getElementsByTagName($tag);
            while ($nodes->length > 0) {
                $nodes->item(0)->parentNode->removeChild($nodes->item(0));
            }
        }
        
        $classes_to_remove = ['sidebar', 'widget', 'related', 'comment', 'share', 'social', 'newsletter', 'ad', 'advertisement'];
        
        foreach ($classes_to_remove as $class) {
            $nodes = $xpath->query("//*[contains(@class, '{$class}')]");
            foreach ($nodes as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
    }
    
    private function extract_title($dom) {
        $xpath = new DOMXPath($dom);
        
        $selectors = [
            '//meta[@property="og:title"]/@content',
            '//meta[@name="twitter:title"]/@content',
            '//h1[@class="entry-title"]',
            '//article//h1[1]',
            '//h1[1]',
            '//title'
        ];
        
        foreach ($selectors as $selector) {
            $result = $xpath->query($selector);
            if ($result->length > 0) {
                $title = trim($result->item(0)->nodeValue);
                if (mb_strlen($title) > 10 && mb_strlen($title) < 200) {
                    return $this->clean_text($title);
                }
            }
        }
        
        return 'Sem título';
    }
    
    private function extract_article_content($dom) {
        $xpath = new DOMXPath($dom);
        $content = '';
        
        $selectors = [
            '//article//p',
            '//*[@itemprop="articleBody"]//p',
            '//*[contains(@class, "entry-content")]//p',
            '//*[contains(@class, "post-content")]//p',
            '//*[contains(@class, "article-content")]//p',
            '//main//p'
        ];
        
        foreach ($selectors as $selector) {
            $paragraphs = $xpath->query($selector);
            
            if ($paragraphs->length > 0) {
                $extracted = [];
                
                foreach ($paragraphs as $p) {
                    $text = trim($p->textContent);
                    
                    if (mb_strlen($text) < 50) continue;
                    if ($this->is_boilerplate($text)) continue;
                    if (substr_count($text, '|') > 2) continue;
                    
                    $text = $this->clean_text($text);
                    
                    if (!empty($text) && !in_array($text, $extracted)) {
                        $extracted[] = $text;
                    }
                }
                
                if (count($extracted) >= 2) {
                    $content = implode("\n\n", $extracted);
                    break;
                }
            }
        }
        
        if (mb_strlen($content) < 200) {
            $og_desc = $xpath->query('//meta[@property="og:description"]/@content');
            if ($og_desc->length > 0) {
                $desc = trim($og_desc->item(0)->nodeValue);
                if (mb_strlen($desc) > 100) {
                    $content = $desc;
                }
            }
        }
        
        return $content;
    }
    
    private function is_boilerplate($text) {
        $patterns = [
            '/continua (após|depois) (a |o )?(publicidade|anúncio)/i',
            '/^(compartilh|coment|curtir|siga)/i',
            '/^leia (também|mais)/i',
            '/todos os direitos reservados/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function clean_text($text) {
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
    
    private function extract_meta($dom) {
        $meta = [];
        $metas = $dom->getElementsByTagName('meta');
        
        foreach ($metas as $tag) {
            $name = $tag->getAttribute('name') ?: $tag->getAttribute('property');
            $content = $tag->getAttribute('content');
            
            if ($name && $content) {
                $meta[strtolower($name)] = $content;
            }
        }
        
        return $meta;
    }
    
    private function extract_main_image($dom, $base_url) {
        $xpath = new DOMXPath($dom);
        $result = ['url' => null, 'caption' => '', 'credit' => ''];
        
        $body_selectors = [
            '//figure[contains(@class, "wp-block-image")]',
            '//figure',
            '//article//img',
            '//main//img'
        ];
        
        foreach ($body_selectors as $query) {
            $nodes = $xpath->query($query);
            if ($nodes->length > 0) {
                $node = $nodes->item(0);
                
                if ($node->nodeName !== 'img') {
                    $imgs = $node->getElementsByTagName('img');
                    if ($imgs->length > 0) {
                        $imgNode = $imgs->item(0);
                        $captions = $xpath->query('.//figcaption', $node);
                        if ($captions->length > 0) {
                            $result['caption'] = trim($captions->item(0)->textContent);
                        }
                        
                        $result['url'] = $imgNode->getAttribute('src') ?: $imgNode->getAttribute('data-src');
                    }
                } else {
                    $result['url'] = $node->getAttribute('src') ?: $node->getAttribute('data-src');
                    $result['caption'] = $node->getAttribute('alt');
                }
                
                if (!empty($result['url'])) {
                     break;
                }
            }
        }
        
        if (empty($result['url'])) {
            $meta_selectors = ['//meta[@property="og:image"]', '//meta[@name="twitter:image"]'];
            foreach ($meta_selectors as $sel) {
                $nodes = $xpath->query($sel);
                if ($nodes->length > 0) {
                    $result['url'] = $nodes->item(0)->getAttribute('content');
                    break;
                }
            }
        }
        
        if (!empty($result['url'])) {
            $url = $result['url'];
            if (strpos($url, 'http') !== 0) {
                $parsed = parse_url($base_url);
                $base = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
                $url = rtrim($base, '/') . '/' . ltrim($url, '/');
                $result['url'] = $url;
            }
        }
        
        return !empty($result['url']) ? $result : null;
    }
}
