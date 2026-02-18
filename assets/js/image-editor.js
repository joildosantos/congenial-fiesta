// assets/js/image-editor.js

jQuery(document).ready(function ($) {
    let canvas = null;
    let logo = null;
    let title = null;
    let watermark = null;
    let background = null;
    let selectedObject = null;

    // Inicializar editor
    function initEditor() {
        canvas = new fabric.Canvas('image-canvas', {
            backgroundColor: '#f0f0f0',
            selection: true,
            preserveObjectStacking: true
        });

        // Carregar imagem de exemplo
        loadSampleImage();

        // Configurar controles
        setupControls();

        // Adicionar logo
        addLogo();

        // Adicionar título
        addTitle();

        // Adicionar marca d'água
        addWatermark();

        // Configurar eventos
        canvas.on('object:selected', function (e) {
            selectedObject = e.target;
            updateControls();
        });

        canvas.on('selection:cleared', function () {
            selectedObject = null;
            updateControls();
        });

        canvas.on('object:moving', updateCanvas);
        canvas.on('object:scaling', updateCanvas);
        canvas.on('object:rotating', updateCanvas);

        // Botão de Excluir direto no elemento (Custom Control)
        const deleteIcon = "data:image/svg+xml,%3C%3Fxml version='1.0' encoding='utf-8'%3F%3E%3C!DOCTYPE svg PUBLIC '-//W3C//DTD SVG 1.1//EN' 'http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd'%3E%3Csvg version='1.1' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512' style='enable-background:new 0 0 512 512;' xml:space='preserve'%3E%3Ccircle style='fill:%23E53935;' cx='256' cy='256' r='256'/%3E%3Cpath style='fill:%23FFFFFF;' d='M363.3,148.7c-6.2-6.2-16.4-6.2-22.6,0L256,233.4l-84.7-84.7c-6.2-6.2-16.4-6.2-22.6,0 c-6.2,6.2-6.2,16.4,0,22.6L233.4,256l-84.7,84.7c-6.2,6.2,6.2,16.4,0,22.6c6.2,6.2,16.4,6.2,22.6,0l84.7-84.7l84.7,84.7 c6.2,6.2,16.4,6.2,22.6,0c6.2-6.2,6.2-16.4,0-22.6L278.6,256l84.7-84.7C369.6,165.1,369.6,154.9,363.3,148.7z'/%3E%3C/svg%3E";

        const delImg = document.createElement('img');
        delImg.src = deleteIcon;

        // Configurar controle customizado se suportado (Fabric > 3.6)
        if (fabric.Object.prototype.controls) {
            fabric.Object.prototype.controls.deleteControl = new fabric.Control({
                x: 0.5,
                y: -0.5,
                offsetY: -16,
                offsetX: 16,
                cursorStyle: 'pointer',
                mouseUpHandler: function (eventData, transform) {
                    const target = transform.target;
                    const canvas = target.canvas;
                    if (!target) return;

                    if (target === logo) logo = null;
                    if (target === title) title = null;
                    if (target === watermark) watermark = null;

                    canvas.remove(target);
                    canvas.discardActiveObject();
                    canvas.requestRenderAll();

                    $('#delete-object').hide();
                    return true;
                },
                render: function (ctx, left, top, styleOverride, fabricObject) {
                    const size = 24;
                    ctx.save();
                    ctx.translate(left, top);
                    ctx.rotate(fabric.util.degreesToRadians(fabricObject.angle));
                    ctx.drawImage(delImg, -size / 2, -size / 2, size, size);
                    ctx.restore();
                }
            });
        }

        // Remove old float button if exists
        $('#anrp-float-delete').remove();
    }

    function loadSampleImage() {
        const sampleImage = ($('#sample-image').length ? $('#sample-image').val() : '') || anrp_ajax.plugin_url + 'assets/images/sample-bg.jpg';

        fabric.Image.fromURL(sampleImage, function (img) {
            // Ajustar tamanho da imagem ao canvas
            const canvasWidth = $('#image-canvas').width();
            const scale = canvasWidth / img.width;

            img.scale(scale);
            img.set({
                left: 0,
                top: 0,
                selectable: false,
                evented: false
            });

            background = img;
            canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas));
            canvas.setWidth(img.width * scale);
            canvas.setHeight(img.height * scale);
        });
    }

    function addLogo() {
        const logoUrl = anrp_ajax.upload_url + 'logo.png';

        fabric.Image.fromURL(logoUrl, function (img) {
            // Redimensionar logo
            const maxWidth = 150;
            const scale = maxWidth / img.width;

            img.scale(scale);
            img.set({
                left: 20,
                top: 20,
                hasControls: true,
                hasBorders: true,
                lockUniScaling: true,
                cornerStyle: 'circle',
                cornerColor: '#007cba',
                cornerSize: 8,
                transparentCorners: false
            });

            logo = img;
            canvas.add(logo);
            canvas.setActiveObject(logo);
        }, {
            crossOrigin: 'anonymous'
        });
    }

    function addTitle() {
        const titleText = new fabric.Text('Título da Notícia Aqui', {
            left: 50,
            top: canvas.height - 150,
            fontSize: 32,
            fontFamily: 'Poppins, Arial',
            fill: '#ffffff',
            backgroundColor: 'rgba(0,0,0,0.7)',
            textAlign: 'center',
            width: canvas.width - 100,
            padding: 20,
            lockRotation: true,
            lockScalingFlip: true,
            hasControls: true,
            hasBorders: true
        });

        title = titleText;
        canvas.add(title);
    }

    function addWatermark() {
        const siteName = (typeof anrp_ajax !== 'undefined' && anrp_ajax.site_name) ? anrp_ajax.site_name : document.title || '';
        const watermarkText = new fabric.Text('© ' + siteName, {
            left: canvas.width - 150,
            top: canvas.height - 30,
            fontSize: 12,
            fontFamily: 'Arial',
            fill: 'rgba(255,255,255,0.5)',
            selectable: true,
            hasControls: true,
            hasBorders: true,
            lockRotation: true,
            lockScalingX: true,
            lockScalingY: true
        });

        watermark = watermarkText;
        canvas.add(watermark);
    }

    function setupControls() {
        // Controles de fonte
        $('#font-size').on('input', function () {
            if (selectedObject && selectedObject.type === 'text') {
                selectedObject.set('fontSize', parseInt($(this).val()));
                canvas.renderAll();
            }
        });

        $('#font-color').on('input', function () {
            if (selectedObject && selectedObject.type === 'text') {
                selectedObject.set('fill', $(this).val());
                canvas.renderAll();
            }
        });

        $('#bg-color').on('input', function () {
            if (selectedObject && selectedObject.type === 'text') {
                selectedObject.set('backgroundColor', $(this).val());
                canvas.renderAll();
            }
        });

        $('#text-align').on('change', function () {
            if (selectedObject && selectedObject.type === 'text') {
                selectedObject.set('textAlign', $(this).val());
                canvas.renderAll();
            }
        });

        // Controles de logo
        $('#logo-opacity').on('input', function () {
            if (logo) {
                logo.set('opacity', parseInt($(this).val()) / 100);
                canvas.renderAll();
            }
        });

        // Botões de ação
        // Botão Carregar (template usa #upload-image)
        $('#upload-image').on('click', function () {
            uploadBackground();
        });

        // Se existir o botão de upload de logo, conectar
        $('#upload-logo').on('click', function () {
            uploadLogo();
        });

        $('#save-template').on('click', function () {
            saveTemplate();
        });

        // Download / Salvar local
        $('#download-image').on('click', function () {
            downloadImage();
        });

        $('#save-image').on('click', function () {
            // atualmente apenas baixa localmente como fallback
            downloadImage();
        });

        // Adicionar imagem sobre o fundo (overlay)
        $('#add-image').on('click', function () {
            addOverlayImage();
        });

        // '#test-image' não existe no template atual — ignorar se ausente
    }

    function updateControls() {
        if (!selectedObject) {
            $('.object-controls').hide();
            return;
        }

        $('.object-controls').show();

        if (selectedObject.type === 'text') {
            $('#font-size').val(selectedObject.fontSize);
            $('#font-color').val(selectedObject.fill);
            $('#bg-color').val(selectedObject.backgroundColor || '#000000');
            $('#text-align').val(selectedObject.textAlign || 'left');

            $('.text-controls').show();
            $('.image-controls').hide();
        } else if (selectedObject.type === 'image') {
            $('#logo-opacity').val(Math.round(selectedObject.opacity * 100));

            $('.text-controls').hide();
            $('.image-controls').show();
        }
        toggleDeleteButton();
    }

    function updateCanvas() {
        // Atualizar coordenadas e tamanhos no painel
        if (selectedObject) {
            $('#pos-x').val(Math.round(selectedObject.left));
            $('#pos-y').val(Math.round(selectedObject.top));

            if (selectedObject.scaleX) {
                $('#size-w').val(Math.round(selectedObject.width * selectedObject.scaleX));
                $('#size-h').val(Math.round(selectedObject.height * selectedObject.scaleY));
            }
        }
    }

    function uploadBackground() {
        // Se wp.media estiver disponível, usar Media Library, senão usar fallback por input file
        if (typeof wp !== 'undefined' && wp.media) {
            const frame = wp.media({
                title: 'Selecionar Imagem de Fundo',
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            frame.on('select', function () {
                const attachment = frame.state().get('selection').first().toJSON();
                loadBackgroundFromUrl(attachment.url);
            });

            frame.open();
            return;
        }

        // Fallback: disparar input file
        $('#background-file').off('change').on('change', function (e) {
            const file = this.files && this.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (evt) {
                loadBackgroundFromUrl(evt.target.result);
            };
            reader.readAsDataURL(file);

            // reset input
            $(this).val('');
        }).trigger('click');
    }

    function uploadLogo() {
        if (typeof wp !== 'undefined' && wp.media) {
            const frame = wp.media({
                title: 'Selecionar Logo',
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            frame.on('select', function () {
                const attachment = frame.state().get('selection').first().toJSON();
                loadLogoFromUrl(attachment.url);
            });

            frame.open();
            return;
        }

        // fallback via input file
        $('#logo-file').off('change').on('change', function (e) {
            const file = this.files && this.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (evt) {
                loadLogoFromUrl(evt.target.result);
            };
            reader.readAsDataURL(file);

            $(this).val('');
        }).trigger('click');
    }

    function addOverlayImage() {
        if (typeof wp !== 'undefined' && wp.media) {
            const frame = wp.media({
                title: 'Selecionar Imagem',
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function () {
                const attachment = frame.state().get('selection').first().toJSON();
                loadOverlayImage(attachment.url);
            });

            frame.open();
            return;
        }

        // fallback via input file
        $('#add-image-file').off('change').on('change', function (e) {
            const file = this.files && this.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (evt) {
                loadOverlayImage(evt.target.result);
            };
            reader.readAsDataURL(file);

            $(this).val('');
        }).trigger('click');
    }

    function loadBackgroundFromUrl(url) {
        fabric.Image.fromURL(url, function (img) {
            const canvasWidth = $('#image-canvas').width();
            const scale = canvasWidth / img.width;

            img.scale(scale);
            img.set({
                left: 0,
                top: 0,
                selectable: false,
                evented: false
            });

            background = img;
            canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas));
            canvas.setWidth(img.width * scale);
            canvas.setHeight(img.height * scale);

            repositionElements();
        });
    }

    function loadLogoFromUrl(url) {
        fabric.Image.fromURL(url, function (img) {
            const maxWidth = 150;
            const scale = maxWidth / img.width;

            img.scale(scale);
            img.set({
                left: logo ? logo.left : 20,
                top: logo ? logo.top : 20,
                hasControls: true,
                hasBorders: true
            });

            if (logo) {
                canvas.remove(logo);
            }

            logo = img;
            canvas.add(logo);
            canvas.setActiveObject(logo);
            canvas.renderAll();
        });
    }

    function loadOverlayImage(url) {
        fabric.Image.fromURL(url, function (img) {
            const maxWidth = canvas.width * 0.6;
            const scale = Math.min(1, maxWidth / img.width);

            img.scale(scale);
            img.set({
                left: (canvas.width - img.width * scale) / 2,
                top: (canvas.height - img.height * scale) / 2,
                hasControls: true,
                hasBorders: true,
                selectable: true,
                lockUniScaling: false,
                cornerStyle: 'circle',
                cornerColor: '#007cba'
            });

            canvas.add(img);
            canvas.setActiveObject(img);
            canvas.renderAll();
        }, { crossOrigin: 'anonymous' });
    }

    // Adicionar texto customizado via botão
    jQuery(document).on('click', '#add-text', function () {
        if (!canvas) return;
        const txt = new fabric.IText('Texto aqui', {
            left: 60,
            top: 60,
            fontSize: 28,
            fontFamily: 'Poppins, Arial',
            fill: '#ffffff',
            backgroundColor: 'rgba(0,0,0,0.5)'
        });
        canvas.add(txt);
        canvas.setActiveObject(txt);
        canvas.renderAll();
    });

    // Filtros simples para a imagem de fundo
    jQuery(document).on('click', '.filter-btn', function () {
        const filter = jQuery(this).data('filter');
        if (!background) return;

        const filters = [];
        if (filter === 'grayscale') {
            filters.push(new fabric.Image.filters.Grayscale());
        } else if (filter === 'sepia') {
            filters.push(new fabric.Image.filters.Sepia());
        } else if (filter === 'brightness') {
            filters.push(new fabric.Image.filters.Brightness({ brightness: 0.08 }));
        } else if (filter === 'remove') {
            background.filters = [];
            background.applyFilters();
            canvas.renderAll();
            return;
        }

        background.filters = filters;
        background.applyFilters();
        canvas.renderAll();
    });

    // Excluir objeto selecionado
    jQuery(document).on('click', '#delete-object, #delete-selected', function (e) {
        e.preventDefault();

        const target = canvas ? (canvas.getActiveObject() || selectedObject) : null;
        if (!target) return; // Se nada selecionado, ignorar

        // Verificar se é um componente chave
        if (target === logo) logo = null;
        if (target === title) title = null;
        if (target === watermark) watermark = null;

        canvas.remove(target);
        selectedObject = null;
        canvas.discardActiveObject();
        canvas.requestRenderAll();

        // Ocultar botões
        $('#delete-object').hide();
        // Se usar botão flutuante
        $('#anrp-float-delete').hide();
    });

    // Botão Limpar Canvas - remove tudo menos background, se desejar
    jQuery(document).on('click', '#clear-canvas', function () {
        if (!canvas) return;
        if (!confirm('Deseja limpar todos os elementos (mantendo fundo)?')) return;

        const objects = canvas.getObjects();
        // Filtrar background e talvez logo? Aqui remove tudo que não for background
        objects.forEach(function (o) {
            if (o !== background) {
                canvas.remove(o);
            }
        });
        canvas.discardActiveObject();
        canvas.renderAll();
    });

    // Trocar fundo usando botão
    jQuery(document).on('click', '#replace-background', function () {
        $('#background-file').trigger('click');
    });

    // Mostrar / ocultar botão excluir conforme seleção
    function toggleDeleteButton() {
        if (selectedObject) {
            $('#delete-object').show();
        } else {
            $('#delete-object').hide();
        }
    }

    function repositionElements() {
        if (title) {
            title.set({
                top: canvas.height - 150,
                width: canvas.width - 100
            });
        }

        if (watermark) {
            watermark.set({
                left: canvas.width - 150,
                top: canvas.height - 30
            });
        }

        canvas.renderAll();
        toggleDeleteButton();
    }

    function saveTemplate() {
        const templateData = {
            name: $('#template-name').val() || 'Novo Template',
            config: {
                logo: {
                    position: {
                        x: logo ? Math.round(logo.left) : 20,
                        y: logo ? Math.round(logo.top) : 20
                    },
                    size: {
                        width: logo ? Math.round(logo.width * logo.scaleX) : 100,
                        height: logo ? Math.round(logo.height * logo.scaleY) : 50
                    },
                    opacity: logo ? Math.round(logo.opacity * 100) : 100
                },
                title: {
                    position: {
                        x: title ? Math.round(title.left) : 50,
                        y: title ? Math.round(title.top) : 0
                    },
                    font_size: title ? title.fontSize : 32,
                    font_color: title ? title.fill : '#ffffff',
                    background_color: title ? title.backgroundColor : 'rgba(0,0,0,0.7)',
                    padding: 20,
                    max_width: title ? title.width : 700,
                    align: title ? title.textAlign : 'center'
                },
                watermark: {
                    text: watermark ? watermark.text : '© ' + $('#site-name').val(),
                    position: 'bottom-right',
                    font_size: watermark ? watermark.fontSize : 12,
                    color: watermark ? watermark.fill : 'rgba(255,255,255,0.5)'
                }
            }
        };

        $.post(anrp_ajax.ajax_url, {
            action: 'anrp_save_template',
            nonce: anrp_ajax.nonce,
            template: templateData
        }, function (response) {
            if (response.success) {
                alert('Template salvo com sucesso!');
            }
        });
    }

    function generateTestImage() {
        const canvasData = canvas.toDataURL('image/jpeg');
        const preview = $('#image-preview');

        preview.attr('src', canvasData);
        preview.show();

        // Fazer download da imagem
        const link = document.createElement('a');
        link.download = 'template-preview.jpg';
        link.href = canvasData;
        link.click();
    }

    function downloadImage() {
        if (!canvas) return;
        // Usar toBlob para melhor compatibilidade com navegadores (e Safari)
        canvas.toBlob(function (blob) {
            if (!blob) return;
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'anrp-image.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
        }, 'image/png');
    }

    // Inicializar quando a página carregar
    initEditor();
});