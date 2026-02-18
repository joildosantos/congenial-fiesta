<?php
// includes/class-anrp-free-image-finder.php

class ANRP_Free_Image_Finder {
    
    public function find_image($title, $keywords = []) {
        $methods = ['pexels', 'pixabay', 'unsplash', 'flickr'];
        
        foreach ($methods as $method) {
            try {
                switch ($method) {
                    case 'pexels':
                        $image = $this->search_pexels($title, $keywords);
                        break;
                    case 'pixabay':
                        $image = $this->search_pixabay($title, $keywords);
                        break;
                    case 'unsplash':
                        $image = $this->search_unsplash($title, $keywords);
                        break;
                    case 'flickr':
                        $image = $this->search_flickr($title, $keywords);
                        break;
                }
                
                if ($image) {
                    return $image;
                }
            } catch (Exception $e) {
                // Continua para o próximo método
                continue;
            }
        }
        
        return $this->get_fallback_image($title);
    }
    
    private function search_pexels($title, $keywords) {
        $api_key = get_option('anrp_pexels_key', '');
        
        if (empty($api_key)) {
            return false;
        }
        
        $search_term = $this->get_search_term($title, $keywords);
        
        $response = wp_remote_get(
            'https://api.pexels.com/v1/search?' . http_build_query([
                'query' => $search_term,
                'per_page' => 1,
                'orientation' => 'landscape'
            ]),
            [
                'timeout' => 15,
                'headers' => [
                    'Authorization' => $api_key
                ]
            ]
        );
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['photos'][0]['src']['large'])) {
            return [
                'url' => $data['photos'][0]['src']['large'],
                'alt' => $title,
                'source' => 'Pexels',
                'photographer' => $data['photos'][0]['photographer'] ?? '',
                'license' => 'Free to use'
            ];
        }
        
        return false;
    }
    
    private function search_pixabay($title, $keywords) {
        // Pixabay API (precisa de chave, mas tem plano gratuito)
        $api_key = get_option('anrp_pixabay_key', '');
        
        if (empty($api_key)) {
            return false;
        }
        
        $search_term = $this->get_search_term($title, $keywords);
        
        $response = wp_remote_get(
            'https://pixabay.com/api/?' . http_build_query([
                'key' => $api_key,
                'q' => $search_term,
                'image_type' => 'photo',
                'per_page' => 1,
                'safesearch' => 'true'
            ]),
            ['timeout' => 15]
        );
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['hits'][0]['largeImageURL'])) {
            return [
                'url' => $data['hits'][0]['largeImageURL'],
                'alt' => $data['hits'][0]['tags'] ?: $title,
                'source' => 'Pixabay',
                'license' => 'Free for commercial use'
            ];
        }
        
        return false;
    }
    
    private function search_unsplash($title, $keywords) {
        // Unsplash API (gratuita com limite)
        $search_term = $this->get_search_term($title, $keywords);
        
        $response = wp_remote_get(
            'https://api.unsplash.com/photos/random?' . http_build_query([
                'query' => urlencode($search_term),
                'orientation' => 'landscape',
                'client_id' => 'YOUR_UNSPLASH_ACCESS_KEY' // Precisa registrar aplicação
            ]),
            ['timeout' => 15]
        );
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['urls']['regular'])) {
            return [
                'url' => $data['urls']['regular'],
                'alt' => $data['alt_description'] ?: $title,
                'source' => 'Unsplash',
                'photographer' => $data['user']['name'] ?? '',
                'license' => 'Free to use'
            ];
        }
        
        return false;
    }
    
    private function search_flickr($title, $keywords) {
        // Flickr API para Creative Commons
        $search_term = $this->get_search_term($title, $keywords);
        
        $response = wp_remote_get(
            'https://api.flickr.com/services/feeds/photos_public.gne?' . http_build_query([
                'tags' => urlencode($search_term),
                'format' => 'json',
                'nojsoncallback' => 1,
                'license' => '4,5,6,7,8' // Creative Commons licenses
            ]),
            ['timeout' => 15]
        );
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['items'][0]['media']['m'])) {
            return [
                'url' => str_replace('_m.jpg', '_b.jpg', $data['items'][0]['media']['m']),
                'alt' => $data['items'][0]['title'] ?: $title,
                'source' => 'Flickr',
                'license' => 'Creative Commons',
                'author' => $data['items'][0]['author'] ?? ''
            ];
        }
        
        return false;
    }
    
    private function get_fallback_image($title) {
        // Tentar obter uma imagem temática via Unsplash Source (sem chave)
        $search_term = $this->get_search_term($title, []);
        if (!empty($search_term)) {
            $unsplash_url = 'https://source.unsplash.com/1200x800/?' . urlencode($search_term);
            return [
                'url' => $unsplash_url,
                'alt' => $title,
                'source' => 'Unsplash (search)',
                'license' => 'Unsplash license'
            ];
        }

        // Fallback estático caso não haja termos suficientes
        $default_images = [
            'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=1200',
            'https://images.pexels.com/photos/669615/pexels-photo-669615.jpeg?w=1200',
            'https://cdn.pixabay.com/photo/2018/01/14/23/12/nature-3082832_1280.jpg'
        ];

        $random_image = $default_images[array_rand($default_images)];

        return [
            'url' => $random_image,
            'alt' => $title,
            'source' => 'Stock Image',
            'license' => 'Free to use'
        ];
    }
    
    private function get_search_term($title, $keywords) {
        if (!empty($keywords)) {
            return implode(' ', array_slice($keywords, 0, 3));
        }
        
        // Extrair palavras-chave do título
        $words = explode(' ', $title);
        $filtered = array_filter($words, function($word) {
            return strlen($word) > 3 && !in_array(strtolower($word), $this->get_stopwords());
        });
        
        return implode(' ', array_slice($filtered, 0, 5));
    }
    
    private function get_stopwords() {
        return [
            'para', 'com', 'sem', 'sobre', 'entre', 'após',
            'até', 'contra', 'desde', 'durante', 'exceto',
            'mediante', 'menos', 'perante', 'salvo', 'segundo',
            'visto', 'como', 'que', 'qual', 'quais'
        ];
    }
}