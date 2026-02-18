<?php
/**
 * Admin Image Editor Template - CRIA Releituras
 * Design System Espaço do Povo - Múltiplos Templates
 */
$posts = get_posts(['numberposts' => 30, 'post_status' => ['publish', 'draft']]);

// Verificar se veio post_id via URL
$preload_post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
$preload_data = null;

if ($preload_post_id) {
    $preload_post = get_post($preload_post_id);
    if ($preload_post) {
        $author_id = $preload_post->post_author;
        $author = get_userdata($author_id);
        
        // CORREÇÃO: Obter avatar com suporte a colunista (mesma lógica do single-opiniao.php)
        $author_avatar = '';
        
        // Verificar se tem colunista vinculado
        $post_colunista_id = get_post_meta($preload_post_id, '_post_colunista_id', true);
        $colunista = null;
        
        if ($post_colunista_id) {
            $colunista = get_post($post_colunista_id);
        } elseif (function_exists('espacodopovo_get_colunista_by_author')) {
            $colunista = espacodopovo_get_colunista_by_author($author_id);
        }
        
        // Se tem colunista, usar foto do colunista
        if ($colunista) {
            if (function_exists('espacodopovo_get_colunista_photo')) {
                $photo_data = espacodopovo_get_colunista_photo($colunista->ID, 'thumbnail');
                if (!empty($photo_data['has_photo']) && !empty($photo_data['url'])) {
                    $author_avatar = $photo_data['url'];
                }
            }
            if (empty($author_avatar) && has_post_thumbnail($colunista->ID)) {
                $author_avatar = get_the_post_thumbnail_url($colunista->ID, 'thumbnail');
            }
        }
        
        // Fallback: Avatar do WordPress/Gravatar
        if (empty($author_avatar)) {
            $author_avatar = get_avatar_url($author_id, ['size' => 200]);
        }
        
        $preload_data = [
            'id' => $preload_post_id,
            'title' => $preload_post->post_title,
            'image' => get_the_post_thumbnail_url($preload_post_id, 'large') ?: '',
            'author_name' => $author ? $author->display_name : 'Autor',
            'author_role' => get_user_meta($author_id, 'description', true) ?: 'Colunista',
            'author_avatar' => $author_avatar,
        ];
    }
}
?>
<div class="wrap anrp-wrap">
    <!-- Brand Header -->
    <div class="anrp-brand-header">
        <div class="anrp-brand-logo">
            <svg viewBox="0 0 24 24" fill="#0A0A0A">
                <path d="M12 2 L14 9 L21 10 L14 11 L12 18 L10 11 L3 10 L10 9 Z"/>
            </svg>
        </div>
        <div class="anrp-brand-text">
            <div class="anrp-brand-name">CRIA <span>Releituras</span></div>
            <div class="anrp-brand-tagline">Editor de Imagens para Redes Sociais</div>
        </div>
    </div>

    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">

    <div class="anrp-page-header">
        <div>
            <h1><span class="dashicons dashicons-format-image"></span> Editor de Imagens</h1>
            <p class="anrp-page-subtitle">Crie imagens profissionais com templates exclusivos</p>
        </div>
        <?php if ($preload_data): ?>
        <a href="<?php echo get_edit_post_link($preload_post_id); ?>" class="anrp-btn anrp-btn-ghost">
            <span class="dashicons dashicons-arrow-left-alt"></span> Voltar ao Post
        </a>
        <?php endif; ?>
    </div>


    <div style="display:grid;grid-template-columns:1fr 380px;gap:24px;">
        <!-- Canvas Principal -->
        <div>
            <div class="anrp-card" style="background:#0F172A;border:none;">
                <div class="anrp-card-body" style="padding:24px;">
                    <!-- Canvas Container -->
                    <div id="anrp-canvas-wrapper" style="position:relative;width:100%;max-width:540px;margin:0 auto;">
                        
                        <!-- Template: Notícia (Padrão) -->
                        <div id="template-noticia" class="anrp-template-canvas active" style="aspect-ratio:1;background:#0A0A0A;border-radius:8px;overflow:hidden;position:relative;font-family:'Space Grotesk',sans-serif;">
                            <div class="tpl-bg" style="position:absolute;inset:0;background-size:cover;background-position:center;"></div>
                            <div style="position:absolute;inset:0;background:linear-gradient(180deg, rgba(10,10,10,0.6) 0%, rgba(10,10,10,0.15) 30%, rgba(10,10,10,0.15) 50%, rgba(10,10,10,0.8) 75%, rgba(10,10,10,0.95) 100%);"></div>
                            <div style="position:absolute;top:0;left:0;right:0;height:80px;padding:16px 24px;display:flex;align-items:center;gap:12px;z-index:10;">
                                <div style="width:48px;height:48px;background:#CCFF00;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="#0A0A0A"><path d="M12 2 L14 9 L21 10 L14 11 L12 18 L10 11 L3 10 L10 9 Z"/></svg>
                                </div>
                                <div>
                                    <div style="font-size:14px;font-weight:700;color:#FAFAFA;">ESPAÇO</div>
                                    <div style="font-size:14px;font-weight:700;color:#CCFF00;">do POVO</div>
                                </div>
                            </div>
                            <div style="position:absolute;bottom:0;left:0;right:0;padding:24px;z-index:10;">
                                <div class="tpl-category" style="display:inline-block;background:#CCFF00;color:#0A0A0A;padding:4px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;border-radius:2px;">NOTÍCIA</div>
                                <h2 class="tpl-title" style="font-size:28px;font-weight:700;color:#FAFAFA;line-height:1.15;text-transform:uppercase;margin:0 0 16px 0;">DIGITE SEU TÍTULO AQUI</h2>
                                <div style="width:80px;height:4px;background:#CCFF00;margin-bottom:16px;border-radius:2px;"></div>
                                <div style="display:flex;justify-content:space-between;align-items:flex-end;">
                                    <div><span style="font-size:12px;color:rgba(250,250,250,0.5);display:block;">Espaço do Povo</span><span style="font-size:14px;color:#CCFF00;font-weight:600;">@espacodopovo</span></div>
                                    <span style="font-size:12px;color:#CCFF00;font-weight:600;letter-spacing:1px;">DESDE 2007</span>
                                </div>
                            </div>
                        </div>

                        <!-- Template: Citação -->
                        <div id="template-citacao" class="anrp-template-canvas" style="aspect-ratio:1;background:#0A0A0A;border-radius:8px;overflow:hidden;position:relative;font-family:'Playfair Display',serif;display:none;">
                            <div class="tpl-bg" style="position:absolute;inset:0;background-size:cover;background-position:center;opacity:0.3;"></div>
                            <div style="position:absolute;inset:0;background:linear-gradient(135deg, #0A0A0A 0%, #1a1a2e 100%);"></div>
                            <div style="position:absolute;top:24px;left:24px;width:48px;height:48px;background:#CCFF00;border-radius:50%;display:flex;align-items:center;justify-content:center;z-index:10;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="#0A0A0A"><path d="M12 2 L14 9 L21 10 L14 11 L12 18 L10 11 L3 10 L10 9 Z"/></svg>
                            </div>
                            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;padding:40px;z-index:10;width:90%;">
                                <div style="font-size:72px;color:#CCFF00;line-height:0.5;margin-bottom:20px;font-family:'Playfair Display',serif;">"</div>
                                <p class="tpl-title" style="font-size:24px;font-weight:400;color:#FAFAFA;line-height:1.4;font-style:italic;margin:0 0 24px 0;">Digite sua citação inspiradora aqui</p>
                                <div style="width:60px;height:2px;background:#CCFF00;margin:0 auto 16px;"></div>
                                <p class="tpl-author" style="font-size:14px;color:#CCFF00;font-weight:600;text-transform:uppercase;letter-spacing:2px;margin:0;font-family:'Space Grotesk',sans-serif;">— AUTOR DA CITAÇÃO</p>
                            </div>
                            <div style="position:absolute;bottom:20px;left:24px;right:24px;display:flex;justify-content:space-between;align-items:center;z-index:10;">
                                <span style="font-size:12px;color:#CCFF00;font-weight:600;font-family:'Space Grotesk',sans-serif;">@espacodopovo</span>
                                <span style="font-size:11px;color:rgba(250,250,250,0.5);font-family:'Space Grotesk',sans-serif;">DESDE 2007</span>
                            </div>
                        </div>

                        <!-- Template: Dados/Estatísticas -->
                        <div id="template-dados" class="anrp-template-canvas" style="aspect-ratio:1;background:#0A0A0A;border-radius:8px;overflow:hidden;position:relative;font-family:'Space Grotesk',sans-serif;display:none;">
                            <div style="position:absolute;inset:0;background:linear-gradient(135deg, #0F172A 0%, #0A0A0A 100%);"></div>
                            <!-- Grid decorativo -->
                            <div style="position:absolute;inset:0;opacity:0.1;background-image:linear-gradient(rgba(204,255,0,0.3) 1px, transparent 1px),linear-gradient(90deg, rgba(204,255,0,0.3) 1px, transparent 1px);background-size:40px 40px;"></div>
                            <div style="position:absolute;top:24px;left:24px;display:flex;align-items:center;gap:12px;z-index:10;">
                                <div style="width:48px;height:48px;background:#CCFF00;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="#0A0A0A"><path d="M12 2 L14 9 L21 10 L14 11 L12 18 L10 11 L3 10 L10 9 Z"/></svg>
                                </div>
                                <div><div style="font-size:14px;font-weight:700;color:#FAFAFA;">ESPAÇO</div><div style="font-size:14px;font-weight:700;color:#CCFF00;">do POVO</div></div>
                            </div>
                            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;z-index:10;width:85%;">
                                <div class="tpl-number" style="font-size:96px;font-weight:700;color:#CCFF00;line-height:1;margin-bottom:8px;">72%</div>
                                <div class="tpl-title" style="font-size:20px;font-weight:600;color:#FAFAFA;line-height:1.3;text-transform:uppercase;margin-bottom:16px;">dos moradores de favelas são empreendedores</div>
                                <div style="width:100px;height:4px;background:#CCFF00;margin:0 auto;border-radius:2px;"></div>
                            </div>
                            <div style="position:absolute;bottom:20px;left:24px;right:24px;z-index:10;">
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <span class="tpl-source" style="font-size:11px;color:rgba(250,250,250,0.5);">Fonte: CRIA Labs Research</span>
                                    <span style="font-size:12px;color:#CCFF00;font-weight:600;">@espacodopovo</span>
                                </div>
                            </div>
                        </div>

                        <!-- Template: Evento -->
                        <div id="template-evento" class="anrp-template-canvas" style="aspect-ratio:1;background:#0A0A0A;border-radius:8px;overflow:hidden;position:relative;font-family:'Space Grotesk',sans-serif;display:none;">
                            <div class="tpl-bg" style="position:absolute;inset:0;background-size:cover;background-position:center;"></div>
                            <div style="position:absolute;inset:0;background:linear-gradient(180deg, rgba(10,10,10,0.7) 0%, rgba(10,10,10,0.4) 40%, rgba(10,10,10,0.9) 100%);"></div>
                            <!-- Borda lime -->
                            <div style="position:absolute;top:0;left:0;right:0;height:6px;background:#CCFF00;"></div>
                            <div style="position:absolute;bottom:0;left:0;right:0;height:6px;background:#CCFF00;"></div>
                            <div style="position:absolute;top:24px;left:24px;display:flex;align-items:center;gap:12px;z-index:10;">
                                <div style="width:48px;height:48px;background:#CCFF00;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="#0A0A0A"><path d="M12 2 L14 9 L21 10 L14 11 L12 18 L10 11 L3 10 L10 9 Z"/></svg>
                                </div>
                                <div><div style="font-size:14px;font-weight:700;color:#FAFAFA;">ESPAÇO</div><div style="font-size:14px;font-weight:700;color:#CCFF00;">do POVO</div></div>
                            </div>
                            <div style="position:absolute;top:24px;right:24px;background:#CCFF00;padding:12px 16px;text-align:center;z-index:10;">
                                <div class="tpl-day" style="font-size:36px;font-weight:700;color:#0A0A0A;line-height:1;">15</div>
                                <div class="tpl-month" style="font-size:12px;font-weight:700;color:#0A0A0A;text-transform:uppercase;">JAN</div>
                            </div>
                            <div style="position:absolute;bottom:80px;left:24px;right:24px;z-index:10;">
                                <div style="display:inline-block;background:rgba(204,255,0,0.2);border:1px solid #CCFF00;padding:4px 12px;font-size:11px;font-weight:600;color:#CCFF00;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">EVENTO</div>
                                <h2 class="tpl-title" style="font-size:28px;font-weight:700;color:#FAFAFA;line-height:1.2;margin:0 0 12px 0;">NOME DO EVENTO AQUI</h2>
                                <p class="tpl-location" style="font-size:14px;color:rgba(250,250,250,0.7);margin:0;display:flex;align-items:center;gap:6px;">📍 Local do Evento • 19h</p>
                            </div>
                            <div style="position:absolute;bottom:24px;left:24px;right:24px;display:flex;justify-content:space-between;align-items:center;z-index:10;">
                                <span style="font-size:12px;color:#CCFF00;font-weight:600;">@espacodopovo</span>
                                <span style="font-size:11px;color:rgba(250,250,250,0.5);">DESDE 2007</span>
                            </div>
                        </div>

                        <!-- Template: Editorial -->
                        <div id="template-editorial" class="anrp-template-canvas" style="aspect-ratio:1;background:#0A0A0A;border-radius:8px;overflow:hidden;position:relative;font-family:'Space Grotesk',sans-serif;display:none;">
                            <div style="position:absolute;inset:0;background:linear-gradient(135deg, #1a1a2e 0%, #0A0A0A 50%, #16213e 100%);"></div>
                            <!-- Padrão geométrico -->
                            <div style="position:absolute;top:-50px;right:-50px;width:300px;height:300px;border:40px solid rgba(204,255,0,0.1);border-radius:50%;"></div>
                            <div style="position:absolute;bottom:-100px;left:-100px;width:400px;height:400px;border:60px solid rgba(204,255,0,0.05);border-radius:50%;"></div>
                            <div style="position:absolute;top:24px;left:24px;right:24px;display:flex;justify-content:space-between;align-items:center;z-index:10;">
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <div style="width:48px;height:48px;background:#CCFF00;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="#0A0A0A"><path d="M12 2 L14 9 L21 10 L14 11 L12 18 L10 11 L3 10 L10 9 Z"/></svg>
                                    </div>
                                    <div><div style="font-size:14px;font-weight:700;color:#FAFAFA;">ESPAÇO</div><div style="font-size:14px;font-weight:700;color:#CCFF00;">do POVO</div></div>
                                </div>
                                <div style="background:#CCFF00;padding:6px 16px;font-size:11px;font-weight:700;color:#0A0A0A;text-transform:uppercase;letter-spacing:1px;">EDITORIAL</div>
                            </div>
                            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;z-index:10;width:85%;padding:20px 0;">
                                <div style="width:60px;height:4px;background:#CCFF00;margin:0 auto 24px;"></div>
                                <h2 class="tpl-title" style="font-size:26px;font-weight:700;color:#FAFAFA;line-height:1.3;margin:0 0 20px 0;">A PERIFERIA NÃO PRECISA DE CARIDADE, PRECISA DE OPORTUNIDADE</h2>
                                <div style="width:60px;height:4px;background:#CCFF00;margin:0 auto;"></div>
                            </div>
                            <div style="position:absolute;bottom:24px;left:24px;right:24px;display:flex;justify-content:space-between;align-items:center;z-index:10;">
                                <span style="font-size:12px;color:#CCFF00;font-weight:600;">@espacodopovo</span>
                                <span style="font-size:11px;color:rgba(250,250,250,0.5);">DESDE 2007</span>
                            </div>
                        </div>

                        <!-- Template: Colunista -->
                        <div id="template-colunista" class="anrp-template-canvas" style="aspect-ratio:1;background:#0A0A0A;border-radius:8px;overflow:hidden;position:relative;font-family:'Space Grotesk',sans-serif;display:none;">
                            <!-- Fundo: Imagem destacada do post -->
                            <div class="tpl-bg" style="position:absolute;inset:0;background-size:cover;background-position:center;"></div>
                            <!-- Overlay gradiente -->
                            <div style="position:absolute;inset:0;background:linear-gradient(180deg, rgba(10,10,10,0.5) 0%, rgba(10,10,10,0.3) 30%, rgba(10,10,10,0.7) 70%, rgba(10,10,10,0.95) 100%);"></div>
                            <!-- Header com logo -->
                            <div style="position:absolute;top:24px;left:24px;right:24px;display:flex;justify-content:space-between;align-items:flex-start;z-index:10;">
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <div style="width:48px;height:48px;background:#CCFF00;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="#0A0A0A"><path d="M12 2 L14 9 L21 10 L14 11 L12 18 L10 11 L3 10 L10 9 Z"/></svg>
                                    </div>
                                    <div><div style="font-size:14px;font-weight:700;color:#FAFAFA;">ESPAÇO</div><div style="font-size:14px;font-weight:700;color:#CCFF00;">do POVO</div></div>
                                </div>
                                <div style="background:#CCFF00;padding:6px 16px;font-size:11px;font-weight:700;color:#0A0A0A;text-transform:uppercase;letter-spacing:1px;">COLUNA</div>
                            </div>
                            <!-- Conteúdo inferior -->
                            <div style="position:absolute;bottom:0;left:0;right:0;padding:24px;z-index:10;">
                                <h2 class="tpl-title" style="font-size:26px;font-weight:700;color:#FAFAFA;line-height:1.2;margin:0 0 20px 0;">Título da coluna sobre assunto relevante</h2>
                                <div style="width:80px;height:4px;background:#CCFF00;margin-bottom:16px;border-radius:2px;"></div>
                                <!-- Info do autor -->
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <div class="tpl-avatar" style="width:48px;height:48px;background:#CCFF00;border-radius:50%;background-size:cover;background-position:center;border:2px solid #CCFF00;"></div>
                                    <div>
                                        <div class="tpl-author-name" style="font-size:16px;font-weight:700;color:#FAFAFA;">Joildo Santos</div>
                                        <div class="tpl-author-role" style="font-size:12px;color:#CCFF00;">Colunista</div>
                                    </div>
                                </div>
                            </div>
                            <!-- Footer -->
                            <div style="position:absolute;bottom:24px;right:24px;z-index:10;">
                                <span style="font-size:11px;color:rgba(250,250,250,0.5);">@espacodopovo • DESDE 2007</span>
                            </div>
                        </div>

                        <!-- Template: Breaking News -->
                        <div id="template-breaking" class="anrp-template-canvas" style="aspect-ratio:1;background:#0A0A0A;border-radius:8px;overflow:hidden;position:relative;font-family:'Space Grotesk',sans-serif;display:none;">
                            <div class="tpl-bg" style="position:absolute;inset:0;background-size:cover;background-position:center;"></div>
                            <div style="position:absolute;inset:0;background:rgba(10,10,10,0.85);"></div>
                            <!-- Barra vermelha urgente -->
                            <div style="position:absolute;top:0;left:0;right:0;height:60px;background:#DC2626;display:flex;align-items:center;justify-content:center;gap:12px;z-index:10;">
                                <span style="font-size:24px;">🔴</span>
                                <span style="font-size:18px;font-weight:700;color:#FFF;text-transform:uppercase;letter-spacing:2px;">URGENTE</span>
                                <span style="font-size:24px;">🔴</span>
                            </div>
                            <div style="position:absolute;top:80px;left:24px;display:flex;align-items:center;gap:12px;z-index:10;">
                                <div style="width:40px;height:40px;background:#CCFF00;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="#0A0A0A"><path d="M12 2 L14 9 L21 10 L14 11 L12 18 L10 11 L3 10 L10 9 Z"/></svg>
                                </div>
                                <div><div style="font-size:12px;font-weight:700;color:#FAFAFA;">ESPAÇO</div><div style="font-size:12px;font-weight:700;color:#CCFF00;">do POVO</div></div>
                            </div>
                            <div style="position:absolute;top:50%;left:24px;right:24px;transform:translateY(-50%);z-index:10;">
                                <h2 class="tpl-title" style="font-size:32px;font-weight:700;color:#FAFAFA;line-height:1.15;text-transform:uppercase;margin:0;">TÍTULO DA NOTÍCIA URGENTE AQUI</h2>
                            </div>
                            <div style="position:absolute;bottom:24px;left:24px;right:24px;display:flex;justify-content:space-between;align-items:center;z-index:10;">
                                <span style="font-size:12px;color:#CCFF00;font-weight:600;">@espacodopovo</span>
                                <span class="tpl-time" style="font-size:12px;color:rgba(250,250,250,0.7);">Atualizado há 5 min</span>
                            </div>
                        </div>

                        <!-- Template: Lista/Ranking -->
                        <div id="template-lista" class="anrp-template-canvas" style="aspect-ratio:1;background:#0A0A0A;border-radius:8px;overflow:hidden;position:relative;font-family:'Space Grotesk',sans-serif;display:none;">
                            <div style="position:absolute;inset:0;background:linear-gradient(180deg, #0F172A 0%, #0A0A0A 100%);"></div>
                            <div style="position:absolute;top:24px;left:24px;right:24px;display:flex;justify-content:space-between;align-items:center;z-index:10;">
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <div style="width:48px;height:48px;background:#CCFF00;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="#0A0A0A"><path d="M12 2 L14 9 L21 10 L14 11 L12 18 L10 11 L3 10 L10 9 Z"/></svg>
                                    </div>
                                    <div><div style="font-size:14px;font-weight:700;color:#FAFAFA;">ESPAÇO</div><div style="font-size:14px;font-weight:700;color:#CCFF00;">do POVO</div></div>
                                </div>
                            </div>
                            <div style="position:absolute;top:100px;left:24px;right:24px;z-index:10;">
                                <div class="tpl-title" style="font-size:22px;font-weight:700;color:#FAFAFA;text-transform:uppercase;margin-bottom:20px;">5 COISAS QUE VOCÊ PRECISA SABER</div>
                                <div style="display:flex;flex-direction:column;gap:12px;">
                                    <div style="display:flex;align-items:center;gap:12px;padding:12px;background:rgba(204,255,0,0.1);border-left:4px solid #CCFF00;">
                                        <span style="font-size:24px;font-weight:700;color:#CCFF00;">1</span>
                                        <span class="tpl-item1" style="font-size:14px;color:#FAFAFA;">Primeiro item da lista</span>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:12px;padding:12px;background:rgba(204,255,0,0.05);border-left:4px solid rgba(204,255,0,0.5);">
                                        <span style="font-size:24px;font-weight:700;color:rgba(204,255,0,0.7);">2</span>
                                        <span class="tpl-item2" style="font-size:14px;color:rgba(250,250,250,0.8);">Segundo item da lista</span>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:12px;padding:12px;background:rgba(204,255,0,0.03);border-left:4px solid rgba(204,255,0,0.3);">
                                        <span style="font-size:24px;font-weight:700;color:rgba(204,255,0,0.5);">3</span>
                                        <span class="tpl-item3" style="font-size:14px;color:rgba(250,250,250,0.6);">Terceiro item da lista</span>
                                    </div>
                                </div>
                            </div>
                            <div style="position:absolute;bottom:24px;left:24px;right:24px;display:flex;justify-content:space-between;align-items:center;z-index:10;">
                                <span style="font-size:12px;color:#CCFF00;font-weight:600;">@espacodopovo</span>
                                <span style="font-size:11px;color:rgba(250,250,250,0.5);">DESDE 2007</span>
                            </div>
                        </div>

                        <!-- Template: Antes/Depois -->
                        <div id="template-comparativo" class="anrp-template-canvas" style="aspect-ratio:1;background:#0A0A0A;border-radius:8px;overflow:hidden;position:relative;font-family:'Space Grotesk',sans-serif;display:none;">
                            <div style="position:absolute;inset:0;display:flex;">
                                <div class="tpl-bg-left" style="width:50%;height:100%;background:#1a1a2e;background-size:cover;background-position:center;position:relative;">
                                    <div style="position:absolute;inset:0;background:rgba(0,0,0,0.4);"></div>
                                </div>
                                <div class="tpl-bg-right" style="width:50%;height:100%;background:#0A0A0A;background-size:cover;background-position:center;position:relative;">
                                    <div style="position:absolute;inset:0;background:rgba(0,0,0,0.4);"></div>
                                </div>
                            </div>
                            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:4px;height:80%;background:#CCFF00;z-index:10;"></div>
                            <div style="position:absolute;top:24px;left:50%;transform:translateX(-50%);display:flex;align-items:center;gap:8px;z-index:10;">
                                <div style="width:40px;height:40px;background:#CCFF00;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="#0A0A0A"><path d="M12 2 L14 9 L21 10 L14 11 L12 18 L10 11 L3 10 L10 9 Z"/></svg>
                                </div>
                            </div>
                            <div style="position:absolute;top:80px;left:24px;z-index:10;">
                                <div style="background:rgba(204,255,0,0.2);border:1px solid #CCFF00;padding:6px 16px;font-size:12px;font-weight:700;color:#CCFF00;text-transform:uppercase;">ANTES</div>
                            </div>
                            <div style="position:absolute;top:120px;left:12px;right:50%;padding:12px;z-index:10;">
                                <div class="tpl-before-text" style="font-size:13px;font-weight:600;color:#FAFAFA;line-height:1.4;text-align:center;">Situação antiga</div>
                            </div>
                            <div style="position:absolute;top:80px;right:24px;z-index:10;">
                                <div style="background:#CCFF00;padding:6px 16px;font-size:12px;font-weight:700;color:#0A0A0A;text-transform:uppercase;">DEPOIS</div>
                            </div>
                            <div style="position:absolute;bottom:24px;left:24px;right:24px;text-align:center;z-index:10;">
                                <div class="tpl-title" style="font-size:18px;font-weight:700;color:#FAFAFA;margin-bottom:8px;">TRANSFORMAÇÃO NA COMUNIDADE</div>
                                <span style="font-size:12px;color:#CCFF00;font-weight:600;">@espacodopovo</span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- Controles -->
        <div>
            <!-- Seletor de Template -->
            <div class="anrp-card">
                <div class="anrp-card-header"><h3>🎨 Template</h3></div>
                <div class="anrp-card-body" style="padding:12px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <button type="button" class="anrp-template-btn active" data-template="noticia" style="padding:12px 8px;background:var(--cria-lime);color:#0A0A0A;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;">📰 Notícia</button>
                        <button type="button" class="anrp-template-btn" data-template="citacao" style="padding:12px 8px;background:var(--slate-700);color:#FFF;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;">💬 Citação</button>
                        <button type="button" class="anrp-template-btn" data-template="dados" style="padding:12px 8px;background:var(--slate-700);color:#FFF;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;">📊 Dados</button>
                        <button type="button" class="anrp-template-btn" data-template="evento" style="padding:12px 8px;background:var(--slate-700);color:#FFF;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;">📅 Evento</button>
                        <button type="button" class="anrp-template-btn" data-template="editorial" style="padding:12px 8px;background:var(--slate-700);color:#FFF;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;">✍️ Editorial</button>
                        <button type="button" class="anrp-template-btn" data-template="colunista" style="padding:12px 8px;background:var(--slate-700);color:#FFF;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;">👤 Colunista</button>
                        <button type="button" class="anrp-template-btn" data-template="breaking" style="padding:12px 8px;background:var(--slate-700);color:#FFF;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;">🔴 Urgente</button>
                        <button type="button" class="anrp-template-btn" data-template="lista" style="padding:12px 8px;background:var(--slate-700);color:#FFF;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;">📋 Lista</button>
                        <button type="button" class="anrp-template-btn" data-template="comparativo" style="padding:12px 8px;background:var(--slate-700);color:#FFF;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;grid-column:span 2;">⚖️ Comparativo</button>
                    </div>
                </div>
            </div>

            <!-- Carregar Post -->
            <div class="anrp-card anrp-mt-md">
                <div class="anrp-card-header"><h3>📄 Carregar Post</h3></div>
                <div class="anrp-card-body">
                    <select id="anrp-post-select" class="anrp-form-select" style="width:100%;">
                        <option value="">Selecione...</option>
                        <?php foreach ($posts as $p): ?>
                        <option value="<?php echo $p->ID; ?>" data-title="<?php echo esc_attr($p->post_title); ?>" data-image="<?php echo esc_attr(get_the_post_thumbnail_url($p->ID, 'large')); ?>">
                            <?php echo esc_html(mb_substr($p->post_title, 0, 40)); ?>...
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Campos Dinâmicos -->
            <div class="anrp-card anrp-mt-md">
                <div class="anrp-card-header"><h3>✏️ Conteúdo</h3></div>
                <div class="anrp-card-body">
                    <div class="anrp-form-group">
                        <label class="anrp-form-label">Título/Texto Principal</label>
                        <textarea id="field-title" class="anrp-form-textarea" rows="3" placeholder="Digite o título..."><?php echo $preload_data ? esc_textarea($preload_data['title']) : 'DIGITE SEU TÍTULO AQUI'; ?></textarea>
                    </div>
                    
                    <!-- Campos de Autor (para Citação e Colunista) -->
                    <div class="anrp-form-group field-author" style="display:none;">
                        <label class="anrp-form-label">Nome do Autor</label>
                        <input type="text" id="field-author-name" class="anrp-form-input" placeholder="Nome do autor" value="<?php echo $preload_data ? esc_attr($preload_data['author_name']) : ''; ?>">
                    </div>
                    <div class="anrp-form-group field-author-role" style="display:none;">
                        <label class="anrp-form-label">Cargo/Função</label>
                        <input type="text" id="field-author-role" class="anrp-form-input" placeholder="Ex: CEO CRIA S/A" value="<?php echo $preload_data ? esc_attr($preload_data['author_role']) : ''; ?>">
                    </div>
                    <div class="anrp-form-group field-author-photo" style="display:none;">
                        <label class="anrp-form-label">Foto do Autor</label>
                        <?php if ($preload_data && $preload_data['author_avatar']): ?>
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                            <img src="<?php echo esc_url($preload_data['author_avatar']); ?>" style="width:50px;height:50px;border-radius:50%;object-fit:cover;">
                            <span style="color:var(--slate-400);font-size:12px;">Foto atual do autor</span>
                        </div>
                        <?php endif; ?>
                        <button type="button" id="upload-author-photo-btn" class="anrp-btn anrp-btn-secondary anrp-btn-sm" style="width:100%;">
                            📤 Enviar Foto do Autor
                        </button>
                    </div>
                    
                    <div class="anrp-form-group field-number" style="display:none;">
                        <label class="anrp-form-label">Número/Estatística</label>
                        <input type="text" id="field-number" class="anrp-form-input" placeholder="Ex: 72%" value="72%">
                    </div>
                    <div class="anrp-form-group field-source" style="display:none;">
                        <label class="anrp-form-label">Fonte</label>
                        <input type="text" id="field-source" class="anrp-form-input" placeholder="Fonte dos dados">
                    </div>
                    <div class="anrp-form-group field-date" style="display:none;">
                        <label class="anrp-form-label">Data do Evento</label>
                        <input type="date" id="field-date" class="anrp-form-input">
                    </div>
                    <div class="anrp-form-group field-location" style="display:none;">
                        <label class="anrp-form-label">Local</label>
                        <input type="text" id="field-location" class="anrp-form-input" placeholder="Local do evento">
                    </div>
                    
                    <!-- Campos para Lista -->
                    <div class="anrp-form-group field-list-items" style="display:none;">
                        <label class="anrp-form-label">Itens da Lista (um por linha)</label>
                        <textarea id="field-list-items" class="anrp-form-textarea" rows="5" placeholder="Primeiro item
Segundo item
Terceiro item">Primeiro item da lista
Segundo item da lista
Terceiro item da lista</textarea>
                        <small style="color:var(--slate-400);font-size:11px;display:block;margin-top:4px;">Digite um item por linha (máximo 5 itens)</small>
                    </div>
                    
                    <!-- Campos para Comparativo -->
                    <div class="anrp-form-group field-before-text" style="display:none;">
                        <label class="anrp-form-label">Texto do ANTES</label>
                        <textarea id="field-before-text" class="anrp-form-textarea" rows="3" placeholder="Descreva o estado anterior...">Situação antiga</textarea>
                    </div>
                    <div class="anrp-form-group field-before-image" style="display:none;">
                        <label class="anrp-form-label">Imagem do ANTES</label>
                        <button type="button" id="upload-before-image-btn" class="anrp-btn anrp-btn-secondary anrp-btn-sm" style="width:100%;">
                            📤 Enviar Imagem do ANTES
                        </button>
                        <small style="color:var(--slate-400);font-size:11px;display:block;margin-top:4px;">Lado esquerdo do comparativo</small>
                    </div>
                </div>
            </div>

            <!-- Formato da Imagem -->
            <div class="anrp-card anrp-mt-md">
                <div class="anrp-card-header"><h3>📐 Formato</h3></div>
                <div class="anrp-card-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <button type="button" class="anrp-format-btn active" data-format="square" data-ratio="1" data-width="1080" data-height="1080" style="padding:10px 8px;background:var(--cria-lime);color:#0A0A0A;border:none;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer;">
                            ⬜ Quadrado<br><small style="opacity:0.7;">1080×1080</small>
                        </button>
                        <button type="button" class="anrp-format-btn" data-format="portrait" data-ratio="0.8" data-width="1080" data-height="1350" style="padding:10px 8px;background:var(--slate-700);color:#FFF;border:none;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer;">
                            📱 Portrait<br><small style="opacity:0.7;">1080×1350</small>
                        </button>
                        <button type="button" class="anrp-format-btn" data-format="story" data-ratio="0.5625" data-width="1080" data-height="1920" style="padding:10px 8px;background:var(--slate-700);color:#FFF;border:none;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer;">
                            📲 Story<br><small style="opacity:0.7;">1080×1920</small>
                        </button>
                        <button type="button" class="anrp-format-btn" data-format="landscape" data-ratio="1.91" data-width="1200" data-height="628" style="padding:10px 8px;background:var(--slate-700);color:#FFF;border:none;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer;">
                            🖼️ Landscape<br><small style="opacity:0.7;">1200×628</small>
                        </button>
                    </div>
                    <p style="font-size:11px;color:var(--slate-400);margin-top:12px;text-align:center;">
                        Formato atual: <strong id="format-info" style="color:var(--cria-lime);">1080×1080px</strong>
                    </p>
                </div>
            </div>

            <!-- Imagem de Fundo -->
            <div class="anrp-card anrp-mt-md">
                <div class="anrp-card-header"><h3>🖼️ Imagem</h3></div>
                <div class="anrp-card-body">
                    <button type="button" id="upload-bg-btn" class="anrp-btn anrp-btn-secondary" style="width:100%;">
                        📤 Enviar Imagem de Fundo
                    </button>
                </div>
            </div>

            <!-- Download -->
            <div class="anrp-card anrp-mt-md" style="background:linear-gradient(135deg, var(--cria-lime), var(--cria-lime-dark));">
                <div class="anrp-card-body">
                    <button type="button" id="download-btn" class="anrp-btn" style="width:100%;background:#0A0A0A;color:#CCFF00;font-weight:700;padding:16px;font-size:16px;">
                        ⬇️ Baixar Imagem
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div style="margin-top:48px;padding-top:24px;border-top:1px solid var(--slate-700);display:flex;justify-content:space-between;align-items:center;">
        <span style="color:var(--slate-400);font-size:13px;">CRIA Releituras • Editor de Imagens</span>
        <span style="color:var(--slate-500);font-size:12px;">DESDE 2007</span>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
jQuery(document).ready(function($) {
    var currentTemplate = 'noticia';
    var authorPhotoUrl = '<?php echo $preload_data ? esc_js($preload_data['author_avatar']) : ''; ?>';
    var bgImageUrl = '<?php echo $preload_data ? esc_js($preload_data['image']) : ''; ?>';
    
    // Dados pré-carregados
    var preloadData = <?php echo $preload_data ? json_encode($preload_data) : 'null'; ?>;
    
    // Se tem dados pré-carregados, aplicar
    if (preloadData) {
        // Aplicar imagem de fundo (imagem destacada do post)
        if (preloadData.image) {
            bgImageUrl = preloadData.image;
            // Templates normais usam .tpl-bg
            $('.tpl-bg').css('background-image', 'url(' + preloadData.image + ')');
            // Template comparativo: imagem do post vai para o lado DEPOIS (direita)
            $('.tpl-bg-right').css('background-image', 'url(' + preloadData.image + ')');
        }
        // Aplicar título
        if (preloadData.title) {
            $('#field-title').val(preloadData.title);
            $('.tpl-title').text(preloadData.title.toUpperCase());
        }
        // Aplicar avatar do autor (separado da imagem de fundo)
        if (preloadData.author_avatar) {
            authorPhotoUrl = preloadData.author_avatar;
            $('.tpl-avatar').css('background-image', 'url(' + preloadData.author_avatar + ')');
        }
        if (preloadData.author_name) {
            $('#field-author-name').val(preloadData.author_name);
            $('.tpl-author-name').text(preloadData.author_name);
            $('.tpl-author').text('— ' + preloadData.author_name.toUpperCase());
        }
        if (preloadData.author_role) {
            $('#field-author-role').val(preloadData.author_role);
            $('.tpl-author-role').text(preloadData.author_role);
        }
        // Selecionar post no dropdown
        $('#anrp-post-select').val(preloadData.id);
    }
    
    // Trocar template
    $('.anrp-template-btn').on('click', function() {
        var template = $(this).data('template');
        currentTemplate = template;
        
        // Atualizar botões
        $('.anrp-template-btn').removeClass('active').css({'background':'var(--slate-700)','color':'#FFF'});
        $(this).addClass('active').css({'background':'var(--cria-lime)','color':'#0A0A0A'});
        
        // Mostrar template
        $('.anrp-template-canvas').hide();
        $('#template-' + template).show();
        
        // Mostrar/esconder campos
        $('.field-author, .field-author-role, .field-author-photo, .field-number, .field-source, .field-date, .field-location, .field-list-items, .field-before-text, .field-before-image').hide();
        
        if (template === 'citacao') {
            $('.field-author').show();
        }
        if (template === 'colunista') {
            $('.field-author, .field-author-role, .field-author-photo').show();
        }
        if (template === 'dados') {
            $('.field-number, .field-source').show();
        }
        if (template === 'evento') {
            $('.field-date, .field-location').show();
        }
        if (template === 'lista') {
            $('.field-list-items').show();
        }
        if (template === 'comparativo') {
            $('.field-before-text, .field-before-image').show();
        }
    });
    
    // Atualizar título em tempo real
    $('#field-title').on('input', function() {
        var text = $(this).val() || 'DIGITE SEU TÍTULO AQUI';
        $('.anrp-template-canvas .tpl-title').text(text.toUpperCase());
    });
    
    // Atualizar nome do autor
    $('#field-author-name').on('input', function() {
        var text = $(this).val() || 'Autor';
        $('.tpl-author-name').text(text);
        // Para citação (formato diferente)
        $('.tpl-author').text('— ' + text.toUpperCase());
    });
    
    // Atualizar cargo/função do autor
    $('#field-author-role').on('input', function() {
        var text = $(this).val() || 'Colunista';
        $('.tpl-author-role').text(text);
    });
    
    // Upload foto do autor (apenas para o avatar, não para o fundo)
    $('#upload-author-photo-btn').on('click', function() {
        var frame = wp.media({
            title: 'Selecionar Foto do Autor',
            button: { text: 'Usar foto' },
            multiple: false
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            authorPhotoUrl = attachment.url;
            // Aplica apenas no avatar, não no fundo
            $('.tpl-avatar').css('background-image', 'url(' + attachment.url + ')');
        });
        
        frame.open();
    });
    
    // Atualizar número/estatística
    $('#field-number').on('input', function() {
        var text = $(this).val() || '72%';
        $('.tpl-number').text(text);
    });
    
    // Atualizar fonte
    $('#field-source').on('input', function() {
        var text = $(this).val() || 'Fonte: CRIA Labs';
        $('.tpl-source').text('Fonte: ' + text);
    });
    
    // Atualizar data do evento
    $('#field-date').on('change', function() {
        var date = new Date($(this).val());
        if (!isNaN(date)) {
            var day = date.getDate();
            var months = ['JAN','FEV','MAR','ABR','MAI','JUN','JUL','AGO','SET','OUT','NOV','DEZ'];
            var month = months[date.getMonth()];
            $('.tpl-day').text(day);
            $('.tpl-month').text(month);
        }
    });
    
    // Atualizar local
    $('#field-location').on('input', function() {
        var text = $(this).val() || 'Local do Evento';
        $('.tpl-location').html('📍 ' + text + ' • 19h');
    });
    
    // Atualizar itens da lista
    $('#field-list-items').on('input', function() {
        var items = $(this).val().split('\n').filter(item => item.trim() !== '').slice(0, 5);
        $('.tpl-item1').text(items[0] || 'Primeiro item da lista');
        $('.tpl-item2').text(items[1] || 'Segundo item da lista');
        $('.tpl-item3').text(items[2] || 'Terceiro item da lista');
    });
    
    // Atualizar texto do ANTES (comparativo)
    $('#field-before-text').on('input', function() {
        var text = $(this).val() || 'Situação antiga';
        $('.tpl-before-text').text(text);
    });
    
    // Upload imagem do ANTES (comparativo)
    var beforeImageUrl = '';
    $('#upload-before-image-btn').on('click', function() {
        var frame = wp.media({
            title: 'Selecionar Imagem do ANTES',
            button: { text: 'Usar imagem' },
            multiple: false
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            beforeImageUrl = attachment.url;
            $('.tpl-bg-left').css('background-image', 'url(' + attachment.url + ')');
        });
        
        frame.open();
    });
    
    // Carregar post via AJAX
    $('#anrp-post-select').on('change', function() {
        var postId = $(this).val();
        if (!postId) return;
        
        // Buscar dados completos via AJAX
        $.post(ajaxurl, {
            action: 'anrp_get_post_data_for_editor',
            post_id: postId,
            nonce: '<?php echo wp_create_nonce('anrp_ajax_nonce'); ?>'
        }).done(function(resp) {
            if (resp.success) {
                var data = resp.data;
                
                // Aplicar título
                if (data.title) {
                    $('#field-title').val(data.title).trigger('input');
                }
                
                // Aplicar imagem de fundo (imagem destacada do post)
                if (data.image) {
                    bgImageUrl = data.image;
                    // Templates normais usam .tpl-bg
                    $('.tpl-bg').css('background-image', 'url(' + data.image + ')');
                    // Template comparativo: imagem do post vai para o lado DEPOIS (direita)
                    $('.tpl-bg-right').css('background-image', 'url(' + data.image + ')');
                }
                
                // Aplicar dados do autor
                if (data.author_name) {
                    $('#field-author-name').val(data.author_name).trigger('input');
                }
                if (data.author_role) {
                    $('#field-author-role').val(data.author_role).trigger('input');
                }
                // Avatar do autor (separado da imagem de fundo)
                if (data.author_avatar) {
                    authorPhotoUrl = data.author_avatar;
                    $('.tpl-avatar').css('background-image', 'url(' + data.author_avatar + ')');
                }
            }
        });
    });
    
    // Formato da imagem
    var currentFormat = {
        name: 'square',
        width: 1080,
        height: 1080,
        ratio: 1
    };
    
    $('.anrp-format-btn').on('click', function() {
        var $btn = $(this);
        var format = $btn.data('format');
        var ratio = parseFloat($btn.data('ratio'));
        var width = parseInt($btn.data('width'));
        var height = parseInt($btn.data('height'));
        
        // Atualizar estado
        currentFormat = { name: format, width: width, height: height, ratio: ratio };
        
        // Atualizar botões
        $('.anrp-format-btn').removeClass('active').css({'background':'var(--slate-700)','color':'#FFF'});
        $btn.addClass('active').css({'background':'var(--cria-lime)','color':'#0A0A0A'});
        
        // Atualizar aspect-ratio do canvas
        $('.anrp-template-canvas').css('aspect-ratio', ratio);
        
        // Atualizar info
        $('#format-info').text(width + '×' + height + 'px');
    });
    
    // Upload imagem
    $('#upload-bg-btn').on('click', function() {
        var frame = wp.media({
            title: 'Selecionar Imagem',
            button: { text: 'Usar imagem' },
            multiple: false
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            bgImageUrl = attachment.url;
            $('.tpl-bg, .tpl-bg-left').css('background-image', 'url(' + attachment.url + ')');
        });
        
        frame.open();
    });
    
    // Download com formato correto
    $('#download-btn').on('click', function() {
        var $btn = $(this);
        var $canvas = $('#template-' + currentTemplate);
        
        $btn.prop('disabled', true).text('Gerando...');
        
        // Calcular scale baseado no tamanho atual do canvas
        var canvasWidth = $canvas.width();
        var targetWidth = currentFormat.width;
        var scale = targetWidth / canvasWidth;
        
        html2canvas($canvas[0], {
            scale: scale,
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#0A0A0A',
            width: $canvas.width(),
            height: $canvas.height()
        }).then(function(canvas) {
            var link = document.createElement('a');
            var formatName = currentFormat.name;
            link.download = 'cria-' + currentTemplate + '-' + formatName + '-' + Date.now() + '.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
            $btn.prop('disabled', false).text('⬇️ Baixar Imagem');
        }).catch(function(err) {
            console.error(err);
            alert('Erro ao gerar imagem');
            $btn.prop('disabled', false).text('⬇️ Baixar Imagem');
        });
    });
});
</script>
