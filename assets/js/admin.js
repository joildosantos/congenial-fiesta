/**
 * Auto News Rewriter Pro - Admin JavaScript v3.0
 */

(function($) {
    'use strict';

    window.ANRP = window.ANRP || {};

    // Toast System
    ANRP.Toast = {
        container: null,
        init: function() {
            if (!this.container) {
                this.container = $('<div class="anrp-toast-container"></div>');
                $('body').append(this.container);
            }
        },
        show: function(message, type, duration) {
            this.init();
            type = type || 'info';
            duration = duration || 4000;
            const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
            const toast = $(`<div class="anrp-toast ${type}"><span>${icons[type]}</span><span>${message}</span><button class="anrp-toast-close">×</button></div>`);
            this.container.append(toast);
            toast.find('.anrp-toast-close').on('click', function() { toast.fadeOut(200, function() { $(this).remove(); }); });
            setTimeout(function() { toast.fadeOut(300, function() { $(this).remove(); }); }, duration);
        },
        success: function(msg) { this.show(msg, 'success'); },
        error: function(msg) { this.show(msg, 'error', 6000); },
        warning: function(msg) { this.show(msg, 'warning'); },
        info: function(msg) { this.show(msg, 'info'); }
    };

    // Modal System
    ANRP.Modal = {
        open: function(selector) { $(selector).addClass('active'); $('body').css('overflow', 'hidden'); },
        close: function(selector) { $(selector).removeClass('active'); $('body').css('overflow', ''); },
        init: function() {
            $(document).on('click', '.anrp-modal-close, .anrp-modal-overlay', function(e) {
                if (e.target === this) ANRP.Modal.close('.anrp-modal-overlay');
            });
            $(document).on('keydown', function(e) { if (e.key === 'Escape') ANRP.Modal.close('.anrp-modal-overlay.active'); });
        }
    };

    // Tabs System
    ANRP.Tabs = {
        init: function() {
            $(document).on('click', '.anrp-tab-btn', function(e) {
                e.preventDefault();
                const target = $(this).data('tab');
                const container = $(this).closest('.anrp-tabs');
                container.find('.anrp-tab-btn').removeClass('active');
                $(this).addClass('active');
                container.find('.anrp-tab-panel').removeClass('active');
                container.find(`[data-panel="${target}"]`).addClass('active');
            });
        }
    };

    // Article Processor
    ANRP.ArticleProcessor = {
        steps: ['scraping', 'rewriting', 'image', 'publishing'],
        currentStep: 0,

        init: function() {
            const self = this;
            $('#anrp-new-article-form').on('submit', function(e) {
                e.preventDefault();
                self.process($(this));
            });
            $('#publish_option').on('change', function() {
                if ($(this).val() === 'schedule') $('.schedule-date-row').slideDown();
                else $('.schedule-date-row').slideUp();
            });
        },

        process: function($form) {
            const self = this;
            const url = $('#article-url').val();
            if (!url || url.length < 10) {
                ANRP.Toast.error('Por favor, informe uma URL válida.');
                return;
            }
            $('#anrp-new-article-form').slideUp();
            $('#processing-status').slideDown();
            self.currentStep = 0;
            self.updateProgress(0);
            self.sendToServer($form);
        },

        sendToServer: function($form) {
            const self = this;
            const formData = $form.serializeArray();
            formData.push({ name: 'action', value: 'anrp_process_article' });
            formData.push({ name: 'nonce', value: anrp_ajax.nonce });

            let step = 0;
            const interval = setInterval(function() {
                if (step < self.steps.length) {
                    self.updateStep(self.steps[step]);
                    step++;
                }
            }, 800);

            $.post(anrp_ajax.ajax_url, formData).done(function(response) {
                clearInterval(interval);
                self.updateProgress(100);
                setTimeout(function() {
                    if (response.success) self.showSuccess(response.data);
                    else self.showError(response.data?.message || 'Erro no processamento');
                }, 500);
            }).fail(function() {
                clearInterval(interval);
                self.showError('Erro de conexão com o servidor.');
            });
        },

        updateStep: function(step) {
            const stepIndex = this.steps.indexOf(step);
            if (stepIndex >= 0) {
                this.currentStep = stepIndex;
                this.updateProgress((stepIndex + 1) / this.steps.length * 100);
                $('.anrp-progress-step').removeClass('active completed');
                $('.anrp-progress-step').each(function(i) {
                    if (i < stepIndex) $(this).addClass('completed');
                    else if (i === stepIndex) $(this).addClass('active');
                });
            }
        },

        updateProgress: function(percent) { $('.anrp-progress-fill').css('width', percent + '%'); },

        showSuccess: function(data) {
            $('.anrp-progress').hide();
            const html = `
                <div class="anrp-alert anrp-alert-success">
                    <div style="font-size:24px;margin-right:12px;">✓</div>
                    <div>
                        <div style="font-weight:600;margin-bottom:4px;">Notícia processada com sucesso!</div>
                        <div style="font-size:13px;">Título: ${data.post_title || 'N/A'} • Status: ${data.post_status || 'Rascunho'}</div>
                    </div>
                </div>
                <div class="anrp-btn-group anrp-mt-lg">
                    <a href="${data.edit_url || '#'}" class="anrp-btn anrp-btn-primary"><span class="dashicons dashicons-edit"></span> Editar</a>
                    <a href="${data.post_url || '#'}" target="_blank" class="anrp-btn anrp-btn-secondary"><span class="dashicons dashicons-visibility"></span> Ver</a>
                    <button type="button" class="anrp-btn anrp-btn-instagram" id="share-instagram-btn" data-post-id="${data.post_id}"><span class="dashicons dashicons-instagram"></span> Instagram</button>
                    <button type="button" class="anrp-btn anrp-btn-outline" id="new-article-btn"><span class="dashicons dashicons-plus-alt"></span> Nova</button>
                </div>`;
            $('#result-message').html(html).show();
            $(document).trigger('anrp:article_published', data);
        },

        showError: function(message) {
            $('.anrp-progress').hide();
            const html = `
                <div class="anrp-alert anrp-alert-error">
                    <div style="font-size:24px;margin-right:12px;">✕</div>
                    <div>
                        <div style="font-weight:600;margin-bottom:4px;">Erro no processamento</div>
                        <div style="font-size:13px;">${message}</div>
                    </div>
                </div>
                <div class="anrp-btn-group anrp-mt-lg">
                    <button type="button" class="anrp-btn anrp-btn-primary" id="retry-btn"><span class="dashicons dashicons-update"></span> Tentar Novamente</button>
                </div>`;
            $('#result-message').html(html).show();
        }
    };

    // Instagram Share (TOS Compliant)
    ANRP.InstagramShare = {
        postData: null,

        init: function() {
            const self = this;
            $(document).on('click', '#share-instagram-btn', function() { self.openShareModal($(this).data('post-id')); });
            $(document).on('click', '#copy-caption-btn', function() { self.copyCaption(); });
            $(document).on('click', '#download-image-btn', function() { self.downloadImage(); });
            $(document).on('click', '#open-instagram-btn', function() { window.open('https://www.instagram.com/', '_blank'); });
            $(document).on('input', '#instagram-caption', function() { $('#caption-char-count').text($(this).val().length + '/2200'); });
        },

        openShareModal: function(postId) {
            const self = this;
            $.post(anrp_ajax.ajax_url, { action: 'anrp_get_instagram_data', nonce: anrp_ajax.nonce, post_id: postId }).done(function(resp) {
                if (resp.success) {
                    self.postData = resp.data;
                    self.renderModal(resp.data);
                    ANRP.Modal.open('#instagram-share-modal');
                } else {
                    ANRP.Toast.error('Erro ao carregar dados.');
                }
            });
        },

        renderModal: function(data) {
            const caption = this.generateCaption(data);
            const title = (data.title || 'TÍTULO').toUpperCase();
            const bgImage = data.image_url || '';
            
            const modalHtml = `
                <div class="anrp-modal-overlay" id="instagram-share-modal">
                    <div class="anrp-modal anrp-modal-lg">
                        <div class="anrp-modal-header">
                            <h3>📸 Compartilhar no Instagram</h3>
                            <button class="anrp-modal-close">×</button>
                        </div>
                        <div class="anrp-modal-body">
                            <div class="anrp-instagram-share">
                                <div class="anrp-instagram-header">
                                    <div class="anrp-instagram-logo">📷</div>
                                    <div class="anrp-instagram-title">Publicar no Instagram</div>
                                </div>
                                
                                <!-- Preview usando Design System Espaço do Povo -->
                                <div id="anrp-share-canvas" style="position:relative;width:400px;height:400px;margin:0 auto;background:#0A0A0A;overflow:hidden;border-radius:8px;font-family:'Space Grotesk',system-ui,-apple-system,sans-serif;">
                                    
                                    <!-- Background image -->
                                    <div id="share-bg-layer" style="position:absolute;top:0;left:0;right:0;bottom:0;background-image:url('${bgImage}');background-size:cover;background-position:center;"></div>
                                    
                                    <!-- Overlay gradiente inteligente - mais leve no centro, sólido na base -->
                                    <div style="position:absolute;top:0;left:0;right:0;bottom:0;background:linear-gradient(180deg, rgba(10,10,10,0.6) 0%, rgba(10,10,10,0.15) 30%, rgba(10,10,10,0.15) 50%, rgba(10,10,10,0.8) 75%, rgba(10,10,10,0.95) 100%);"></div>
                                    
                                    <!-- Header com Logo -->
                                    <div style="position:absolute;top:0;left:0;right:0;height:60px;padding:12px 24px;display:flex;align-items:center;gap:12px;z-index:10;">
                                        <!-- Círculo lime com estrela 4 pontas -->
                                        <div style="width:40px;height:40px;background:#CCFF00;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                            <svg width="22" height="22" viewBox="0 0 24 24" fill="#0A0A0A">
                                                <path d="M12 2 L14 9 L21 10 L14 11 L12 18 L10 11 L3 10 L10 9 Z"/>
                                            </svg>
                                        </div>
                                        <!-- Texto da marca ao lado -->
                                        <div style="display:flex;flex-direction:column;gap:0;">
                                            <span style="font-size:12px;font-weight:700;color:#FAFAFA;letter-spacing:0.02em;line-height:1.2;">ESPAÇO</span>
                                            <span style="font-size:12px;font-weight:700;color:#CCFF00;letter-spacing:0.02em;line-height:1.2;">do POVO</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Área do conteúdo (footer) -->
                                    <div style="position:absolute;bottom:0;left:0;right:0;padding:24px;z-index:10;">
                                        <!-- Título -->
                                        <h2 id="share-title-preview" style="font-family:'Space Grotesk',system-ui,sans-serif;font-size:18px;font-weight:700;color:#FAFAFA;line-height:1.15;text-transform:uppercase;letter-spacing:-0.01em;margin:0 0 12px 0;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">
                                            ${title}
                                        </h2>
                                        
                                        <!-- Barra separadora lime - 4px -->
                                        <div style="width:75px;height:3px;background:#CCFF00;margin-bottom:12px;border-radius:1.5px;"></div>
                                        
                                        <!-- Footer -->
                                        <div style="display:flex;justify-content:space-between;align-items:flex-end;">
                                            <div style="display:flex;flex-direction:column;gap:1px;">
                                                <span style="font-family:'DM Sans',system-ui,sans-serif;font-size:10px;font-weight:400;color:rgba(250,250,250,0.5);">Espaço do Povo</span>
                                                <span style="font-family:'DM Sans',system-ui,sans-serif;font-size:12px;font-weight:600;color:#CCFF00;">@espacodopovo</span>
                                            </div>
                                            <span style="font-family:'Space Grotesk',sans-serif;font-size:10px;font-weight:600;color:#CCFF00;letter-spacing:0.05em;">DESDE 2007</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="anrp-instagram-caption-box">
                                    <div class="anrp-instagram-caption-header">
                                        <span class="anrp-instagram-caption-label">✏️ Legenda</span>
                                        <span class="anrp-instagram-char-count" id="caption-char-count">${caption.length}/2200</span>
                                    </div>
                                    <textarea id="instagram-caption" maxlength="2200">${caption}</textarea>
                                </div>
                                <div class="anrp-instagram-actions">
                                    <button class="anrp-btn anrp-btn-primary anrp-btn-lg" id="download-image-btn"><span class="dashicons dashicons-download"></span> Baixar Imagem</button>
                                    <button class="anrp-btn anrp-btn-secondary anrp-btn-lg" id="copy-caption-btn"><span class="dashicons dashicons-clipboard"></span> Copiar Legenda</button>
                                    <button class="anrp-btn anrp-btn-instagram anrp-btn-lg" id="open-instagram-btn"><span class="dashicons dashicons-instagram"></span> Abrir Instagram</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
            $('#instagram-share-modal').remove();
            $('body').append(modalHtml);
            
            // Carregar html2canvas se necessário
            if (typeof html2canvas === 'undefined') {
                var script = document.createElement('script');
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
                document.head.appendChild(script);
            }
        },

        generateCaption: function(data) {
            const title = data.title || '';
            const excerpt = data.excerpt || '';
            const hashtags = data.hashtags || ['notícias', 'informação'];
            let caption = `📰 ${title}\n\n${excerpt}\n\n🔗 Link na bio\n\n`;
            caption += hashtags.map(h => `#${h.replace(/\s+/g, '')}`).join(' ');
            return caption;
        },

        copyCaption: function() {
            const caption = $('#instagram-caption').val();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(caption).then(function() {
                    ANRP.Toast.success('Legenda copiada!');
                    $('body').append('<div class="anrp-copy-success">✓ Copiado!</div>');
                });
            } else {
                const ta = document.createElement('textarea');
                ta.value = caption;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                ANRP.Toast.success('Legenda copiada!');
            }
        },

        downloadImage: function() {
            const canvas = document.getElementById('anrp-share-canvas');
            const postId = this.postData ? this.postData.post_id : Date.now();
            
            if (canvas && typeof html2canvas !== 'undefined') {
                ANRP.Toast.info('Gerando imagem...');
                
                html2canvas(canvas, {
                    scale: 2.7, // 400px * 2.7 = 1080px
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: '#0A0A0A'
                }).then(function(renderedCanvas) {
                    const link = document.createElement('a');
                    link.download = 'instagram-post-' + postId + '.png';
                    link.href = renderedCanvas.toDataURL('image/png');
                    link.click();
                    ANRP.Toast.success('Imagem baixada!');
                }).catch(function(err) {
                    console.error('html2canvas error:', err);
                    ANRP.Toast.error('Erro ao gerar imagem.');
                });
            } else if (this.postData && this.postData.image_url) {
                // Fallback para imagem original
                const link = document.createElement('a');
                link.href = this.postData.image_url;
                link.download = 'instagram-post-' + postId + '.jpg';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                ANRP.Toast.success('Download iniciado!');
            }
        }
    };

    // Feed Manager
    ANRP.FeedManager = {
        init: function() {
            const self = this;
            if ($('#anrp-feeds-list').length) this.loadFeeds();
            $('#anrp-add-feed-form').on('submit', function(e) { e.preventDefault(); self.addFeed($(this)); });
            $(document).on('click', '.preview-feed-btn', function() { self.previewFeed($(this).closest('.anrp-feed-item').data('url')); });
            $(document).on('click', '.delete-feed-btn', function() { if (confirm('Remover este feed?')) self.deleteFeed($(this).data('id')); });
            $(document).on('click', '.toggle-feed-btn', function() { self.toggleFeed($(this).data('id'), !$(this).data('active')); });
            $(document).on('click', '.check-feed-btn', function() { self.checkFeedNow($(this).data('id')); });
            
            // Handler para usar item do feed para reescrita
            $(document).on('click', '.use-feed-item-btn', function() {
                const url = $(this).data('url');
                const title = $(this).data('title');
                
                // Fechar modal
                ANRP.Modal.close('#feed-preview-modal');
                $('#feed-preview-modal').remove();
                
                // Redirecionar para página de nova matéria com a URL preenchida
                const newArticleUrl = anrp_ajax.admin_url + 'admin.php?page=anrp-new-article&url=' + encodeURIComponent(url);
                window.location.href = newArticleUrl;
            });
        },

        loadFeeds: function() {
            const self = this;
            const $list = $('#anrp-feeds-list');
            $list.html('<div class="anrp-loading"><div class="anrp-spinner"></div><div class="anrp-loading-text">Carregando...</div></div>');
            $.post(anrp_ajax.ajax_url, { action: 'anrp_get_feeds', nonce: anrp_ajax.nonce }).done(function(resp) {
                if (resp.success && resp.data.feeds.length > 0) self.renderFeeds(resp.data.feeds);
                else $list.html('<div class="anrp-empty-state"><div class="anrp-empty-icon">📡</div><div class="anrp-empty-title">Nenhum feed cadastrado</div><div class="anrp-empty-desc">Adicione feeds RSS para monitorar fontes.</div></div>');
            });
        },

        renderFeeds: function(feeds) {
            const $list = $('#anrp-feeds-list');
            $list.empty();
            
            // Atualizar contador
            $('#anrp-feeds-count').text(feeds.length + ' feed' + (feeds.length !== 1 ? 's' : ''));
            
            feeds.forEach(function(feed) {
                const statusBadge = feed.active == 1 ? '<span class="anrp-badge anrp-badge-success">Ativo</span>' : '<span class="anrp-badge anrp-badge-neutral">Inativo</span>';
                const lastChecked = feed.last_checked ? new Date(feed.last_checked).toLocaleString('pt-BR') : 'Nunca';
                $list.append(`
                    <div class="anrp-feed-item" data-id="${feed.id}" data-url="${feed.feed_url}">
                        <div class="anrp-feed-icon">📡</div>
                        <div class="anrp-feed-content">
                            <div class="anrp-feed-title">${feed.feed_name || 'Sem nome'} ${statusBadge}</div>
                            <div class="anrp-feed-url">${feed.feed_url}</div>
                            <div class="anrp-feed-meta"><span>🕐 ${lastChecked}</span><span>⚙️ ${feed.auto_publish == 1 ? 'Auto' : 'Manual'}</span></div>
                        </div>
                        <div class="anrp-feed-actions">
                            <button class="anrp-btn anrp-btn-sm anrp-btn-secondary preview-feed-btn" title="Ver itens">👁</button>
                            <button class="anrp-btn anrp-btn-sm anrp-btn-secondary check-feed-btn" data-id="${feed.id}" title="Verificar">🔄</button>
                            <button class="anrp-btn anrp-btn-sm ${feed.active == 1 ? 'anrp-btn-secondary' : 'anrp-btn-success'} toggle-feed-btn" data-id="${feed.id}" data-active="${feed.active}">${feed.active == 1 ? 'Desativar' : 'Ativar'}</button>
                            <button class="anrp-btn anrp-btn-sm anrp-btn-danger delete-feed-btn" data-id="${feed.id}">🗑</button>
                        </div>
                    </div>`);
            });
        },

        addFeed: function($form) {
            const self = this;
            const $btn = $form.find('button[type="submit"]');
            $btn.prop('disabled', true).text('Validando...');
            $.post(anrp_ajax.ajax_url, {
                action: 'anrp_save_feed', nonce: anrp_ajax.nonce,
                feed_url: $form.find('[name="feed_url"]').val(),
                feed_name: $form.find('[name="feed_name"]').val(),
                auto_publish: $form.find('[name="auto_publish"]').is(':checked') ? 1 : 0,
                schedule_type: $form.find('[name="schedule_type"]').val()
            }).done(function(resp) {
                if (resp.success) { ANRP.Toast.success('Feed adicionado!'); $form[0].reset(); self.loadFeeds(); }
                else ANRP.Toast.error(resp.data?.message || 'Erro');
            }).always(function() { $btn.prop('disabled', false).text('Adicionar Feed'); });
        },

        deleteFeed: function(id) { const self = this; $.post(anrp_ajax.ajax_url, { action: 'anrp_delete_feed', nonce: anrp_ajax.nonce, feed_id: id }).done(function() { ANRP.Toast.success('Removido!'); self.loadFeeds(); }); },
        toggleFeed: function(id, active) { const self = this; $.post(anrp_ajax.ajax_url, { action: 'anrp_toggle_feed', nonce: anrp_ajax.nonce, feed_id: id, active: active ? 1 : 0 }).done(function() { ANRP.Toast.success(active ? 'Ativado!' : 'Desativado!'); self.loadFeeds(); }); },
        
        previewFeed: function(feedUrl) {
            const $modal = $(`<div class="anrp-modal-overlay active" id="feed-preview-modal"><div class="anrp-modal anrp-modal-lg"><div class="anrp-modal-header"><h3>📰 Itens do Feed</h3><button class="anrp-modal-close">×</button></div><div class="anrp-modal-body"><div class="anrp-loading"><div class="anrp-spinner"></div></div></div></div></div>`);
            $('body').append($modal);
            $.post(anrp_ajax.ajax_url, { action: 'anrp_preview_feed', nonce: anrp_ajax.nonce, feed_url: feedUrl }).done(function(resp) {
                if (resp.success && resp.data.items) {
                    let html = '<div class="anrp-feed-preview-list">';
                    if (resp.data.items.length === 0) {
                        html += '<p style="padding:20px;text-align:center;color:#666;">Nenhum item encontrado no feed.</p>';
                    } else {
                        resp.data.items.forEach(function(item, index) { 
                            html += `<div class="anrp-feed-preview-item" style="padding:16px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                                <div style="flex:1;">
                                    <strong style="display:block;margin-bottom:6px;line-height:1.4;">${item.title}</strong>
                                    <div style="font-size:12px;color:#666;margin-bottom:8px;">${item.description || ''}</div>
                                    <div style="font-size:11px;color:#999;">${item.date || ''} • <a href="${item.link}" target="_blank" style="color:#0073aa;">Ver original</a></div>
                                </div>
                                <div style="flex-shrink:0;">
                                    <button class="anrp-btn anrp-btn-sm anrp-btn-primary use-feed-item-btn" 
                                            data-url="${item.link}" 
                                            data-title="${item.title.replace(/"/g, '&quot;')}"
                                            title="Usar este item para criar nova matéria">
                                        ✍️ Reescrever
                                    </button>
                                </div>
                            </div>`; 
                        });
                    }
                    html += '</div>';
                    $modal.find('.anrp-modal-body').html(html);
                } else {
                    $modal.find('.anrp-modal-body').html('<p style="padding:20px;text-align:center;color:#c00;">Erro ao carregar itens do feed.</p>');
                }
            }).fail(function() {
                $modal.find('.anrp-modal-body').html('<p style="padding:20px;text-align:center;color:#c00;">Erro de conexão.</p>');
            });
        },

        checkFeedNow: function(id) {
            ANRP.Toast.info('Verificando...');
            $.post(anrp_ajax.ajax_url, { action: 'anrp_check_feed_now', nonce: anrp_ajax.nonce, feed_id: id }).done(function(resp) {
                if (resp.success) ANRP.Toast.success('Verificado! ' + (resp.data.new_items || 0) + ' novos.');
            });
        }
    };

    // History Manager
    ANRP.HistoryManager = {
        currentPage: 1, currentFilter: 'all',
        init: function() {
            const self = this;
            if ($('#anrp-history-table').length) this.loadHistory();
            $('#anrp-history-filter').on('change', function() { self.currentFilter = $(this).val(); self.currentPage = 1; self.loadHistory(); });
            $(document).on('click', '.anrp-pagination-btn', function() { self.currentPage = parseInt($(this).data('page')); self.loadHistory(); });
            $(document).on('click', '.delete-history-btn', function() { if (confirm('Remover?')) self.deleteRecord($(this).data('id')); });
        },
        loadHistory: function() {
            const self = this;
            $.post(anrp_ajax.ajax_url, { action: 'anrp_get_history', nonce: anrp_ajax.nonce, page: this.currentPage, filter: this.currentFilter }).done(function(resp) {
                if (resp.success) { self.renderHistory(resp.data.history); self.renderPagination(resp.data.page, resp.data.pages); $('#anrp-history-count').text(resp.data.total + ' registros'); }
            });
        },
        renderHistory: function(history) {
            const $tbody = $('#anrp-history-table tbody');
            $tbody.empty();
            if (!history || !history.length) { $tbody.html('<tr><td colspan="5" class="anrp-text-center">Nenhum registro</td></tr>'); return; }
            history.forEach(function(item) {
                const sc = item.status === 'publish' ? 'success' : 'warning';
                $tbody.append(`<tr><td>${item.new_title || '-'}</td><td><a href="${item.original_url}" target="_blank">${item.original_url.substring(0,40)}...</a></td><td><span class="anrp-badge anrp-badge-${sc}">${item.status}</span></td><td>${new Date(item.published_date).toLocaleString('pt-BR')}</td><td>${item.post_id ? `<a href="${anrp_ajax.admin_url}post.php?post=${item.post_id}&action=edit" class="anrp-btn anrp-btn-sm anrp-btn-secondary">Editar</a>` : ''} <button class="anrp-btn anrp-btn-sm anrp-btn-ghost delete-history-btn" data-id="${item.id}">🗑</button></td></tr>`);
            });
        },
        renderPagination: function(current, total) {
            const $p = $('#anrp-history-pagination');
            $p.empty();
            if (total <= 1) return;
            for (let i = 1; i <= total; i++) {
                if (i === 1 || i === total || (i >= current - 2 && i <= current + 2)) $p.append(`<button class="anrp-btn anrp-btn-sm ${i === current ? 'anrp-btn-primary' : 'anrp-btn-secondary'} anrp-pagination-btn" data-page="${i}">${i}</button>`);
                else if (i === current - 3 || i === current + 3) $p.append('<span style="padding:0 8px;">...</span>');
            }
        },
        deleteRecord: function(id) { const self = this; $.post(anrp_ajax.ajax_url, { action: 'anrp_delete_history', nonce: anrp_ajax.nonce, record_id: id }).done(function() { ANRP.Toast.success('Removido!'); self.loadHistory(); }); }
    };

    // Settings
    ANRP.Settings = {
        init: function() {
            $('.anrp-settings-form [data-autosave]').on('change', function() {
                const opt = $(this).attr('name');
                const val = $(this).is(':checkbox') ? ($(this).is(':checked') ? 1 : 0) : $(this).val();
                $.post(anrp_ajax.ajax_url, { action: 'anrp_save_option', nonce: anrp_ajax.nonce, option: opt, value: val }).done(function(r) { if (r.success) ANRP.Toast.success('Salvo!'); });
            });
            $('#test-gemini-btn').on('click', function() {
                const $btn = $(this);
                $btn.prop('disabled', true).text('Testando...');
                $.post(anrp_ajax.ajax_url, { action: 'anrp_test_gemini_key', nonce: anrp_ajax.nonce, key: $('#anrp_gemini_key').val(), model: $('#anrp_gemini_model').val() }).done(function(r) {
                    if (r.success) ANRP.Toast.success('API OK!');
                    else ANRP.Toast.error(r.data?.message || 'Erro');
                }).always(function() { $btn.prop('disabled', false).text('Testar'); });
            });
            $('#upload-logo-btn').on('click', function(e) {
                e.preventDefault();
                if (typeof wp !== 'undefined' && wp.media) {
                    const frame = wp.media({ title: 'Selecionar Logo', multiple: false, library: { type: 'image' } });
                    frame.on('select', function() {
                        const att = frame.state().get('selection').first().toJSON();
                        $('#anrp_logo_url').val(att.url);
                        $('#logo-preview').attr('src', att.url).show();
                        $.post(anrp_ajax.ajax_url, { action: 'anrp_save_option', nonce: anrp_ajax.nonce, option: 'anrp_logo_url', value: att.url });
                    });
                    frame.open();
                }
            });
            this.initTagsInput();
        },
        initTagsInput: function() {
            const $cont = $('#anrp-tags-container'), $inp = $('#anrp-tag-input'), $hid = $('#anrp_default_tags');
            function render() {
                const val = $hid.val() || '';
                const tags = val.split(',').map(t => t.trim()).filter(t => t);
                $cont.find('.anrp-tag-chip').remove();
                tags.forEach(function(tag) { $inp.before(`<span class="anrp-tag-chip" style="background:#f1f5f9;padding:4px 10px;border-radius:20px;font-size:13px;display:inline-flex;align-items:center;gap:6px;">${tag}<button type="button" style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:16px;" data-tag="${tag}">×</button></span>`); });
            }
            function add(tag) {
                tag = tag.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
                if (!tag) return;
                const val = $hid.val() || '';
                const tags = val.split(',').map(t => t.trim()).filter(t => t);
                if (!tags.includes(tag)) { tags.push(tag); $hid.val(tags.join(', ')).trigger('change'); render(); }
                $inp.val('');
            }
            $inp.on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ',' || e.key === ' ') { e.preventDefault(); add($(this).val()); }
                else if (e.key === 'Backspace' && !$(this).val()) { const val = $hid.val() || ''; const t = val.split(',').filter(x => x.trim()); t.pop(); $hid.val(t.join(', ')).trigger('change'); render(); }
            });
            $cont.on('click', '.anrp-tag-chip button', function() { const rem = $(this).data('tag'); const val = $hid.val() || ''; const t = val.split(',').map(x => x.trim()).filter(x => x && x !== rem); $hid.val(t.join(', ')).trigger('change'); render(); });
            render();
        }
    };

    // Post List Actions (Reescrever e Compartilhar)
    ANRP.PostActions = {
        init: function() {
            const self = this;
            
            // Handler para Reescrever Novamente
            $(document).on('click', '.anrp-rewrite-post', function(e) {
                e.preventDefault();
                const postId = $(this).data('post-id');
                if (confirm('Deseja reescrever este post? O conteúdo atual será substituído.')) {
                    self.rewritePost(postId, $(this));
                }
            });
            
            // Handler para Compartilhar Agora
            $(document).on('click', '.anrp-share-now', function(e) {
                e.preventDefault();
                const postId = $(this).data('post-id');
                ANRP.InstagramShare.openShareModal(postId);
            });
            
            // Handler para botões na coluna de ações
            $(document).on('click', '.anrp-action-btn[data-action="share"]', function(e) {
                e.preventDefault();
                const postId = $(this).data('post-id');
                ANRP.InstagramShare.openShareModal(postId);
            });
            
            $(document).on('click', '.anrp-action-btn[data-action="rewrite"]', function(e) {
                e.preventDefault();
                const postId = $(this).data('post-id');
                if (confirm('Deseja reescrever este post?')) {
                    self.rewritePost(postId, $(this));
                }
            });
        },
        
        rewritePost: function(postId, $btn) {
            const originalText = $btn.text();
            $btn.text('Processando...').css('pointer-events', 'none');
            
            $.post(anrp_ajax.ajax_url, {
                action: 'anrp_rewrite_post',
                nonce: anrp_ajax.nonce,
                post_id: postId
            }).done(function(resp) {
                if (resp.success) {
                    ANRP.Toast.success('Post reescrito com sucesso!');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    ANRP.Toast.error(resp.data.message || 'Erro ao reescrever');
                    $btn.text(originalText).css('pointer-events', '');
                }
            }).fail(function() {
                ANRP.Toast.error('Erro de conexão');
                $btn.text(originalText).css('pointer-events', '');
            });
        }
    };

    // Init
    $(document).ready(function() {
        ANRP.Modal.init();
        ANRP.Tabs.init();
        ANRP.ArticleProcessor.init();
        ANRP.InstagramShare.init();
        ANRP.FeedManager.init();
        ANRP.HistoryManager.init();
        ANRP.Settings.init();
        ANRP.PostActions.init();
        $(document).on('click', '#new-article-btn, #retry-btn', function() { location.reload(); });
        if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
        console.log('CRIA Releituras v3.0 ready');
    });

})(jQuery);
