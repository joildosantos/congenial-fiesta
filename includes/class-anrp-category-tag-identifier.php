<?php
// includes/class-anrp-category-tag-identifier.php

class ANRP_Category_Tag_Identifier {
    
    public function identify_category_and_tags($title, $content) {
        $full_text = $title . ' ' . $content;
        
        // Identificar categoria
        $category_id = $this->identify_category($full_text);
        
        // Gerar tags
        $tags = $this->generate_tags($full_text);
        
        return [
            'category_id' => $category_id,
            'tags' => $tags
        ];
    }
    
    private function identify_category($text) {
        $all_categories = get_categories(['hide_empty' => false]);
        
        if (empty($all_categories)) {
            return $this->create_default_category();
        }
        
        // Extrair palavras-chave do texto
        $text_keywords = $this->extract_keywords($text, 15);
        
        $best_score = 0;
        $best_category = get_option('default_category', 1);
        
        foreach ($all_categories as $category) {
            $category_text = $category->name . ' ' . $category->description;
            $category_keywords = $this->extract_keywords($category_text, 10);
            
            // Calcular similaridade
            $score = count(array_intersect($text_keywords, $category_keywords));
            
            if ($score > $best_score) {
                $best_score = $score;
                $best_category = $category->term_id;
            }
        }
        
        // Se não encontrou boa correspondência, criar categoria
        if ($best_score < 2) {
            return $this->create_auto_category($text_keywords);
        }
        
        return $best_category;
    }
    
    private function generate_tags($text) {
        $tags = [];
        
        // Tags fixas
        $default_tags = get_option('anrp_default_tags', 'ig, territorio');
        $fixed_tags = array_map('trim', explode(',', $default_tags));
        $tags = array_merge($tags, $fixed_tags);
        
        // Tags automáticas
        $auto_tags_count = get_option('anrp_auto_tags_count', 5);
        if ($auto_tags_count > 0) {
            $auto_tags = $this->extract_keywords($text, $auto_tags_count);
            $tags = array_merge($tags, $auto_tags);
        }
        
        // Limpar e remover duplicatas
        $tags = array_map(function($t) { return mb_strtolower($t, 'UTF-8'); }, $tags);
        $tags = array_map('trim', $tags);
        $tags = array_unique($tags);
        $tags = array_filter($tags, function($tag) {
            return mb_strlen($tag, 'UTF-8') >= 2;
        });
        
        return $tags;
    }
    
    private function extract_keywords($text, $limit = 10) {
        $text = mb_strtolower($text, 'UTF-8');
        
        // Remover stopwords
        $stopwords = $this->get_stopwords();
        $text = preg_replace('/\b(' . implode('|', $stopwords) . ')\b/u', ' ', $text);
        
        // Remover pontuação (manter letras, números e espaços)
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        
        // Extrair palavras de forma segura para UTF-8 (substitui str_word_count)
         $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Filtrar palavras muito curtas (manter >= 3 para keywords extraídas, mas tags fixas já foram tratadas)
        // O usuário reclamou de "ig" não aparecer nos fixos, mas nos keywords automáticos talvez "ig" não faça sentido.
        // No entanto, para consistência, se for keyword extraída, 3 chars é razoável. 
        // Mas o problema do "ig" era no generate_tags filter.
        $words = array_filter($words, function($word) {
            return mb_strlen($word, 'UTF-8') >= 3;
        });
        
        // Contar frequência
        $frequency = array_count_values($words);
        arsort($frequency);
        
        return array_slice(array_keys($frequency), 0, $limit);
    }
    
    private function create_default_category() {
        $default = get_option('default_category', 1);
        if (!$default) {
            $default = $this->create_category('Notícias');
        }
        return $default;
    }
    
    private function create_auto_category($keywords) {
        if (empty($keywords)) {
            return $this->create_default_category();
        }
        
        $category_name = ucfirst($keywords[0]);
        
        // Verificar se categoria já existe
        $existing = get_category_by_slug(sanitize_title($category_name));
        
        if ($existing) {
            return $existing->term_id;
        }
        
        // Criar nova categoria
        $result = wp_insert_term(
            $category_name,
            'category',
            [
                'description' => 'Categoria criada automaticamente pelo Auto News Rewriter Pro'
            ]
        );
        
        if (is_wp_error($result)) {
            return $this->create_default_category();
        }
        
        return $result['term_id'];
    }
    
    private function create_category($name) {
        $result = wp_insert_term(
            $name,
            'category',
            [
                'description' => 'Categoria padrão'
            ]
        );
        
        if (!is_wp_error($result)) {
            return $result['term_id'];
        }
        
        return 1; // ID padrão
    }
    
    private function get_stopwords() {
        return [
            'a', 'o', 'as', 'os', 'um', 'uma', 'uns', 'umas',
            'de', 'do', 'da', 'dos', 'das', 'em', 'no', 'na',
            'nos', 'nas', 'por', 'para', 'com', 'sem', 'sob',
            'sobre', 'entre', 'que', 'se', 'não', 'sim', 'é',
            'são', 'está', 'estão', 'tem', 'têm', 'foi', 'foram',
            'ser', 'estar', 'ter', 'há', 'houve', 'era', 'eram',
            'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to',
            'for', 'of', 'with', 'by', 'from', 'up', 'about'
        ];
    }
}