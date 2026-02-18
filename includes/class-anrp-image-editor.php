<?php
/**
 * ANRP Image Editor - v3.2
 * Gerador de imagens sociais profissionais
 * Design System: Espaço do Povo
 * IMPORTANTE: Deve gerar imagem IDÊNTICA ao Editor frontend
 */
class ANRP_Image_Editor {
    
    // Cores do Design System
    private $lime = [204, 255, 0];      // #CCFF00
    private $black = [10, 10, 10];      // #0A0A0A
    private $white = [250, 250, 250];   // #FAFAFA
    private $gray = [180, 180, 180];    // #B4B4B4
    
    /**
     * Cria imagem social para Instagram (1080x1080)
     */
    public function create_social_image($post_id, $title, $format = 'instagram_square') {
        error_log('ANRP Image Editor: Criando imagem para post ' . $post_id);
        error_log('ANRP Image Editor: Título = ' . mb_substr($title, 0, 50));
        
        // Garantir diretório existe
        if (!file_exists(ANRP_UPLOAD_DIR)) {
            wp_mkdir_p(ANRP_UPLOAD_DIR);
        }
        
        // Dimensões
        $width = 1080;
        $height = 1080;
        
        // Criar imagem
        $image = imagecreatetruecolor($width, $height);
        if (!$image) {
            error_log('ANRP Image Editor: Falha ao criar imagem');
            return false;
        }
        
        imagealphablending($image, true);
        imagesavealpha($image, true);
        
        // Obter imagem destacada do post
        $bg_url = get_the_post_thumbnail_url($post_id, 'full');
        error_log('ANRP Image Editor: BG URL = ' . ($bg_url ?: 'nenhuma'));
        
        // Aplicar fundo (imagem ou gradiente)
        if ($bg_url) {
            $this->apply_background($image, $bg_url, $width, $height);
        } else {
            $this->apply_gradient($image, $width, $height);
        }
        
        // Aplicar overlay LEVE (20% topo, 50% base)
        $this->apply_overlay($image, $width, $height);
        
        // Borda superior lime (6px)
        $lime_color = imagecolorallocate($image, $this->lime[0], $this->lime[1], $this->lime[2]);
        imagefilledrectangle($image, 0, 0, $width, 6, $lime_color);
        
        // Barra lateral direita lime
        imagefilledrectangle($image, $width - 8, 40, $width, 130, $lime_color);
        
        // Logo Espaço do Povo (ou logo customizado)
        $this->draw_logo($image);
        
        // Título em destaque
        error_log('ANRP Image Editor: Desenhando título...');
        $this->draw_title($image, $title, $width, $height);
        
        // Footer com branding
        $this->draw_footer($image, $width, $height);
        
        // Salvar
        $output_path = ANRP_UPLOAD_DIR . 'social_' . $post_id . '_' . time() . '.jpg';
        $saved = imagejpeg($image, $output_path, 95);
        imagedestroy($image);
        
        if (!$saved) {
            error_log('ANRP Image Editor: Falha ao salvar imagem');
            return false;
        }
        
        error_log('ANRP Image Editor: Imagem salva em ' . $output_path);
        return ANRP_UPLOAD_URL . basename($output_path);
    }
    
    /**
     * Aplica imagem de fundo
     */
    private function apply_background(&$image, $url, $width, $height) {
        $data = @file_get_contents($url);
        if (!$data) {
            $this->apply_gradient($image, $width, $height);
            return;
        }
        
        $src = @imagecreatefromstring($data);
        if (!$src) {
            $this->apply_gradient($image, $width, $height);
            return;
        }
        
        $src_w = imagesx($src);
        $src_h = imagesy($src);
        
        // Cover crop
        $scale = max($width / $src_w, $height / $src_h);
        $new_w = (int)($src_w * $scale);
        $new_h = (int)($src_h * $scale);
        $x = (int)(($width - $new_w) / 2);
        $y = (int)(($height - $new_h) / 2);
        
        imagecopyresampled($image, $src, $x, $y, 0, 0, $new_w, $new_h, $src_w, $src_h);
        imagedestroy($src);
    }
    
    /**
     * Aplica gradiente escuro
     */
    private function apply_gradient(&$image, $width, $height) {
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $r = (int)(15 + $ratio * -5);
            $g = (int)(23 + $ratio * -13);
            $b = (int)(42 + $ratio * -32);
            $color = imagecolorallocate($image, max(10, $r), max(10, $g), max(10, $b));
            imageline($image, 0, $y, $width, $y, $color);
        }
    }
    
    /**
     * Aplica overlay - LEVE no topo (20%), mais forte na base (50%)
     */
    private function apply_overlay(&$image, $width, $height) {
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            
            // Overlay muito leve no topo, gradiente para base
            if ($ratio < 0.5) {
                // Topo: apenas 10-20%
                $alpha = 115; // Quase transparente
            } else {
                // Base: gradiente de 20% para 50%
                $progress = ($ratio - 0.5) / 0.5;
                $alpha = (int)(115 - ($progress * 50)); // De 115 para 65
            }
            
            $overlay = imagecolorallocatealpha($image, 10, 10, 10, $alpha);
            imageline($image, 0, $y, $width, $y, $overlay);
        }
    }
    
    /**
     * Desenha o logo
     */
    private function draw_logo(&$image) {
        $logo_url = get_option('anrp_logo_url', '');
        
        // Tentar logo customizado
        if (!empty($logo_url)) {
            $logo_data = @file_get_contents($logo_url);
            if ($logo_data) {
                $logo = @imagecreatefromstring($logo_data);
                if ($logo) {
                    $lw = imagesx($logo);
                    $lh = imagesy($logo);
                    $scale = 80 / $lh;
                    $new_w = (int)($lw * $scale);
                    $new_h = 80;
                    imagecopyresampled($image, $logo, 40, 40, 0, 0, $new_w, $new_h, $lw, $lh);
                    imagedestroy($logo);
                    return;
                }
            }
        }
        
        // Logo padrão: círculo lime + estrela + texto
        $lime_color = imagecolorallocate($image, $this->lime[0], $this->lime[1], $this->lime[2]);
        $black_color = imagecolorallocate($image, $this->black[0], $this->black[1], $this->black[2]);
        $white_color = imagecolorallocate($image, $this->white[0], $this->white[1], $this->white[2]);
        
        // Círculo lime
        imagefilledellipse($image, 80, 85, 80, 80, $lime_color);
        
        // Estrela de 4 pontas
        $cx = 80;
        $cy = 85;
        $s = 22;
        $star = [
            $cx, $cy - $s,
            $cx + (int)($s * 0.35), $cy - (int)($s * 0.35),
            $cx + $s, $cy,
            $cx + (int)($s * 0.35), $cy + (int)($s * 0.35),
            $cx, $cy + $s,
            $cx - (int)($s * 0.35), $cy + (int)($s * 0.35),
            $cx - $s, $cy,
            $cx - (int)($s * 0.35), $cy - (int)($s * 0.35),
        ];
        imagefilledpolygon($image, $star, $black_color);
        
        // Texto do logo
        $font = $this->get_font();
        if ($font) {
            imagettftext($image, 20, 0, 135, 75, $white_color, $font, 'ESPAÇO');
            imagettftext($image, 20, 0, 135, 102, $lime_color, $font, 'do POVO');
        }
    }
    
    /**
     * Desenha o título em destaque
     */
    private function draw_title(&$image, $title, $width, $height) {
        $font = $this->get_font();
        if (!$font) return;
        
        // Limpar e formatar título
        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        $title = strip_tags($title);
        $title = mb_strtoupper(trim($title));
        
        // Calcular tamanho da fonte baseado no comprimento
        $len = mb_strlen($title);
        if ($len < 30) {
            $font_size = 52;
        } elseif ($len < 50) {
            $font_size = 46;
        } elseif ($len < 70) {
            $font_size = 40;
        } elseif ($len < 90) {
            $font_size = 36;
        } else {
            $font_size = 32;
        }
        
        $line_height = (int)($font_size * 1.2);
        $max_width = $width - 80;
        $margin_bottom = 130;
        
        // Quebrar em linhas
        $lines = $this->wrap_text($title, $font, $font_size, $max_width);
        
        // Cores
        $white = imagecolorallocate($image, 255, 255, 255);
        $lime = imagecolorallocate($image, $this->lime[0], $this->lime[1], $this->lime[2]);
        $shadow = imagecolorallocatealpha($image, 0, 0, 0, 60);
        
        // Posição Y
        $total_h = count($lines) * $line_height;
        $start_y = $height - $margin_bottom - $total_h + $font_size;
        
        // Desenhar linhas
        foreach ($lines as $i => $line) {
            $y = $start_y + ($i * $line_height);
            
            // Sombra
            imagettftext($image, $font_size, 0, 43, $y + 3, $shadow, $font, $line);
            // Texto
            imagettftext($image, $font_size, 0, 40, $y, $white, $font, $line);
        }
        
        // Linha decorativa lime
        $line_y = $start_y + (count($lines) * $line_height) + 15;
        imagefilledrectangle($image, 40, $line_y, 240, $line_y + 5, $lime);
    }
    
    /**
     * Quebra texto em linhas
     */
    private function wrap_text($text, $font, $size, $max_width) {
        $words = explode(' ', $text);
        $lines = [];
        $current = '';
        
        foreach ($words as $word) {
            $test = $current ? $current . ' ' . $word : $word;
            $bbox = imagettfbbox($size, 0, $font, $test);
            $w = abs($bbox[4] - $bbox[0]);
            
            if ($w > $max_width && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $test;
            }
        }
        
        if ($current !== '') {
            $lines[] = $current;
        }
        
        return array_slice($lines, 0, 4);
    }
    
    /**
     * Desenha footer com branding
     */
    private function draw_footer(&$image, $width, $height) {
        $font = $this->get_font();
        if (!$font) return;
        
        $lime = imagecolorallocate($image, $this->lime[0], $this->lime[1], $this->lime[2]);
        $gray = imagecolorallocate($image, $this->gray[0], $this->gray[1], $this->gray[2]);
        $black = imagecolorallocate($image, 20, 20, 20);
        
        $site_name = get_bloginfo('name') ?: 'Espaço do Povo';
        $handle = '@' . strtolower(str_replace(' ', '', $site_name));
        
        // Nome do site (16px, cinza)
        imagettftext($image, 16, 0, 40, $height - 55, $gray, $font, $site_name);
        
        // Handle Instagram (20px, lime)
        imagettftext($image, 20, 0, 40, $height - 28, $lime, $font, $handle);
        
        // Badge "DESDE 2007"
        $badge = 'DESDE 2007';
        $bbox = imagettfbbox(14, 0, $font, $badge);
        $bw = abs($bbox[4] - $bbox[0]) + 40;
        $bx = $width - $bw - 30;
        $by = $height - 60;
        
        imagefilledrectangle($image, $bx, $by, $bx + $bw, $by + 35, $black);
        imagettftext($image, 14, 0, $bx + 20, $by + 24, $lime, $font, $badge);
    }
    
    /**
     * Obtém fonte
     */
    private function get_font() {
        $fonts = [
            ANRP_PLUGIN_DIR . 'assets/fonts/SpaceGrotesk-Bold.ttf',
            ANRP_PLUGIN_DIR . 'assets/fonts/Roboto-Regular.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        ];
        
        foreach ($fonts as $f) {
            if (file_exists($f)) return $f;
        }
        
        return null;
    }
    
    /**
     * Template padrão (compatibilidade)
     */
    public function get_default_template() {
        return ['accent_color' => '#CCFF00'];
    }
    
    /**
     * Formatos disponíveis
     */
    public function get_available_formats() {
        return ['instagram_square', 'instagram_portrait', 'instagram_story', 'facebook', 'twitter'];
    }
}
