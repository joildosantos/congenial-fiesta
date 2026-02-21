/**
 * JEP Automacao - Scripts do painel administrativo.
 */

/* global jepAdmin, jQuery */
(function ($) {
	'use strict';

	/**
	 * Exibe feedback de resposta inline.
	 *
	 * @param {jQuery} $target Elemento onde exibir.
	 * @param {string} message Mensagem.
	 * @param {string} type    'success' ou 'error'.
	 */
	function showFeedback($target, message, type) {
		$target
			.text(message)
			.removeClass('success error')
			.addClass('jep-feedback ' + type)
			.show();

		setTimeout(function () {
			$target.fadeOut(400, function () {
				$(this).text('').removeClass('jep-feedback success error').show();
			});
		}, 5000);
	}

	/**
	 * Disparo manual de workflow.
	 */
	$(document).on('click', '.jep-trigger-btn', function () {
		var $btn      = $(this);
		var workflow  = $btn.data('workflow');
		var $feedback = $btn.siblings('.jep-feedback');

		if (!$feedback.length) {
			$feedback = $('<span class="jep-feedback"></span>').insertAfter($btn);
		}

		$btn.prop('disabled', true).prepend('<span class="jep-spinner"></span> ');

		$.ajax({
			url: jepAdmin.ajaxUrl,
			method: 'POST',
			data: {
				action:   'jep_trigger_workflow',
				nonce:    jepAdmin.nonce,
				workflow: workflow,
			},
			success: function (response) {
				if (response.success) {
					showFeedback($feedback, response.data.message, 'success');
				} else {
					showFeedback($feedback, response.data.message || 'Erro desconhecido.', 'error');
				}
			},
			error: function () {
				showFeedback($feedback, 'Erro de comunicacao com o servidor.', 'error');
			},
			complete: function () {
				$btn.prop('disabled', false).find('.jep-spinner').remove();
			},
		});
	});

	/**
	 * Teste de webhook.
	 */
	$(document).on('click', '#jep-test-webhook', function () {
		var $btn    = $(this);
		var $result = $('#jep-test-result');

		$btn.prop('disabled', true).prepend('<span class="jep-spinner"></span> ');

		$.ajax({
			url: jepAdmin.ajaxUrl,
			method: 'POST',
			data: {
				action: 'jep_test_webhook',
				nonce:  jepAdmin.nonce,
			},
			success: function (response) {
				if (response.success) {
					showFeedback($result, response.data.message, 'success');
				} else {
					showFeedback($result, response.data.message || 'Erro.', 'error');
				}
			},
			error: function () {
				showFeedback($result, 'Erro de comunicacao.', 'error');
			},
			complete: function () {
				$btn.prop('disabled', false).find('.jep-spinner').remove();
			},
		});
	});

	/**
	 * Limpar logs - 30 dias.
	 */
	$(document).on('click', '#jep-clear-logs-30', function () {
		if (!window.confirm('Remover logs com mais de 30 dias?')) {
			return;
		}

		var $btn = $(this);
		$btn.prop('disabled', true);

		$.ajax({
			url: jepAdmin.ajaxUrl,
			method: 'POST',
			data: {
				action: 'jep_clear_logs',
				nonce:  jepAdmin.nonce,
				days:   30,
			},
			success: function (response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				}
			},
			complete: function () {
				$btn.prop('disabled', false);
			},
		});
	});

	/**
	 * Limpar todos os logs.
	 */
	$(document).on('click', '#jep-clear-logs-all', function () {
		if (!window.confirm('Remover TODOS os logs? Esta acao nao pode ser desfeita.')) {
			return;
		}

		var $btn = $(this);
		$btn.prop('disabled', true);

		$.ajax({
			url: jepAdmin.ajaxUrl,
			method: 'POST',
			data: {
				action: 'jep_clear_logs',
				nonce:  jepAdmin.nonce,
				days:   0,
			},
			success: function (response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				}
			},
			complete: function () {
				$btn.prop('disabled', false);
			},
		});
	});

	/**
	 * Modal de contexto do log.
	 */
	$(document).on('click', '.jep-show-context', function (e) {
		e.preventDefault();

		var context = $(this).data('context');
		var pretty  = '';

		try {
			pretty = JSON.stringify(JSON.parse(context), null, 2);
		} catch (err) {
			pretty = context;
		}

		$('#jep-context-content').text(pretty);
		$('#jep-context-modal').css('display', 'flex');
	});

	$('#jep-close-modal, #jep-context-modal').on('click', function (e) {
		if (e.target === this) {
			$('#jep-context-modal').hide();
		}
	});

	/**
	 * Testar provedor LLM.
	 */
	$(document).on('click', '.jep-test-llm', function () {
		var $btn      = $(this);
		var providerId = $btn.data('id');
		var $feedback = $btn.siblings('.jep-llm-test-result');

		if (!$feedback.length) {
			$feedback = $('<span class="jep-llm-test-result"></span>').insertAfter($btn);
		}

		$btn.prop('disabled', true).prepend('<span class="jep-spinner"></span> ');

		$.ajax({
			url: jepAdmin.ajaxUrl,
			method: 'POST',
			data: {
				action:      'jep_test_llm_provider',
				nonce:       jepAdmin.nonce,
				provider_id: providerId,
			},
			success: function (response) {
				if (response.success) {
					var msg = response.data.response || response.data.message || 'OK';
					if (response.data.latency_ms) {
						msg = 'OK (' + response.data.latency_ms + ' ms) — ' + msg;
					}
					showFeedback($feedback, msg, 'success');
				} else {
					var err = response.data && (response.data.response || response.data.message);
					showFeedback($feedback, err || 'Erro ao testar provedor.', 'error');
				}
			},
			error: function () {
				showFeedback($feedback, 'Erro de comunicacao com o servidor.', 'error');
			},
			complete: function () {
				$btn.prop('disabled', false).find('.jep-spinner').remove();
			},
		});
	});

	/**
	 * Verificar conexao com o bot Telegram.
	 */
	$(document).on('click', '#jep-telegram-get-me', function () {
		var $btn    = $(this);
		var $result = $('#jep-telegram-me-result');

		$btn.prop('disabled', true).prepend('<span class="jep-spinner"></span> ');

		$.ajax({
			url: jepAdmin.ajaxUrl,
			method: 'POST',
			data: {
				action: 'jep_telegram_bot_info',
				nonce:  jepAdmin.nonce,
			},
			success: function (response) {
				if (response.success) {
					var bot = response.data;
					var msg = '@' + (bot.username || '?') + ' — ' + (bot.first_name || '');
					showFeedback($result, msg, 'success');
				} else {
					showFeedback($result, response.data.message || 'Erro.', 'error');
				}
			},
			error: function () {
				showFeedback($result, 'Erro de comunicacao com o servidor.', 'error');
			},
			complete: function () {
				$btn.prop('disabled', false).find('.jep-spinner').remove();
			},
		});
	});

}(jQuery));
