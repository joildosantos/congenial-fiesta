<?php
/**
 * JEP Content Rewriter
 *
 * Orchestrates all LLM-powered content operations: RSS rewrite, cold-content
 * rewrite, relevance scoring, image-prompt generation and weekly summaries.
 *
 * @package JEP_Automacao_Editorial
 * @subpackage LLM
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Content_Rewriter
 *
 * Wraps JEP_LLM_Manager with domain-specific prompts and response parsing
 * for the Jornal Espaço do Povo editorial workflow.
 */
class JEP_Content_Rewriter {

	/**
	 * Shared LLM manager instance.
	 *
	 * @var JEP_LLM_Manager
	 */
	private $llm;

	/**
	 * Constructor. Instantiates the underlying LLM manager.
	 */
	public function __construct() {
		$this->llm = new JEP_LLM_Manager();
	}

	// -------------------------------------------------------------------------
	// Public rewrite methods
	// -------------------------------------------------------------------------

	/**
	 * Rewrite an RSS item for publication on Jornal Espaço do Povo.
	 *
	 * @param string $title      Original item title.
	 * @param string $content    Original item content / body text.
	 * @param string $excerpt    Original item excerpt.
	 * @param string $source_url Canonical URL of the source article.
	 * @param array  $categories Associated category labels.
	 *
	 * @return array {
	 *     @type string   $titulo_a        Primary title (≤80 chars).
	 *     @type string   $titulo_b        Alternative title (≤80 chars).
	 *     @type string   $excerpt         Two-line summary (≤160 chars).
	 *     @type string   $conteudo_html   Full HTML body (3–500 words).
	 *     @type string[] $hashtags        5 hashtags without the # prefix.
	 *     @type string[] $categorias_wp   Suggested WordPress category names.
	 * }
	 *
	 * @throws Exception On LLM failure or malformed JSON response.
	 */
	public function rewrite_rss_item( $title, $content, $excerpt, $source_url, $categories = array() ) {
		$system_prompt = $this->get_system_prompt_base();

		$categories_str = is_array( $categories ) ? implode( ', ', $categories ) : (string) $categories;

		// Truncate content to avoid excessive token usage.
		$content_trimmed = wp_trim_words( wp_strip_all_tags( $content ), 400, '' );

		$user_prompt = sprintf(
			"Reescreva esta notícia para o Jornal Espaço do Povo, veículo de comunicação popular das periferias brasileiras.\n\n" .
			"TÍTULO ORIGINAL: %s\n" .
			"CONTEÚDO: %s\n" .
			"FONTE: %s\n" .
			"CATEGORIAS: %s\n\n" .
			"Retorne JSON com:\n" .
			"- titulo_a: título principal (máx 80 chars, direto, sem clickbait)\n" .
			"- titulo_b: título alternativo (diferente ângulo, máx 80 chars)\n" .
			"- excerpt: resumo em 2 linhas (máx 160 chars)\n" .
			"- conteudo_html: artigo completo em HTML (parágrafos <p>, use <strong> para destaques, mínimo 3 parágrafos, máx 500 palavras)\n" .
			"- hashtags: array de 5 hashtags relevantes sem #\n" .
			"- categorias_wp: array de categorias WordPress sugeridas\n\n" .
			"Retorne APENAS o JSON, sem texto antes ou depois.",
			esc_html( $title ),
			$content_trimmed,
			esc_url_raw( $source_url ),
			$categories_str
		);

		$raw_response = $this->llm->complete( $user_prompt, $system_prompt, 'rss_rewrite', 2048 );
		$data         = $this->parse_json_response( $raw_response );

		return $this->sanitize_rewrite_response( $data );
	}

	/**
	 * Rewrite a cold-content (pauta fria) item with deeper editorial treatment.
	 *
	 * @param string $title         Topic title.
	 * @param string $summary       Brief summary or research notes.
	 * @param string $territory     Geographic/community territory label.
	 * @param array  $research_data Additional structured research context.
	 *
	 * @return array Same structure as rewrite_rss_item() but richer content (5–7 paragraphs).
	 *
	 * @throws Exception On LLM failure or malformed JSON response.
	 */
	public function rewrite_cold_content( $title, $summary, $territory, $research_data = array() ) {
		$system_prompt = $this->get_system_prompt_base();

		$research_str = '';
		if ( ! empty( $research_data ) ) {
			$research_str = "\nDADOS DE PESQUISA ADICIONAIS:\n";
			foreach ( $research_data as $key => $value ) {
				if ( is_string( $value ) || is_numeric( $value ) ) {
					$research_str .= "- {$key}: {$value}\n";
				}
			}
		}

		$user_prompt = sprintf(
			"Você está trabalhando em uma pauta fria (cold content) para o Jornal Espaço do Povo.\n\n" .
			"TÍTULO DA PAUTA: %s\n" .
			"RESUMO: %s\n" .
			"TERRITÓRIO: %s\n" .
			"%s\n" .
			"Crie um artigo jornalístico completo com profundidade editorial. Inclua contexto histórico, " .
			"impacto na comunidade e perspectiva dos moradores quando possível.\n\n" .
			"Retorne JSON com:\n" .
			"- titulo_a: título principal (máx 80 chars, direto, sem clickbait)\n" .
			"- titulo_b: título alternativo (diferente ângulo, máx 80 chars)\n" .
			"- excerpt: resumo em 2 linhas (máx 160 chars)\n" .
			"- conteudo_html: artigo completo em HTML (parágrafos <p>, use <strong> para destaques, " .
			"mínimo 5 parágrafos e máximo 7, entre 600 e 900 palavras, inclua contexto e impacto local)\n" .
			"- hashtags: array de 5 hashtags relevantes sem #\n" .
			"- categorias_wp: array de categorias WordPress sugeridas\n\n" .
			"Retorne APENAS o JSON, sem texto antes ou depois.",
			esc_html( $title ),
			esc_html( $summary ),
			esc_html( $territory ),
			$research_str
		);

		$raw_response = $this->llm->complete( $user_prompt, $system_prompt, 'cold_content_rewrite', 3000 );
		$data         = $this->parse_json_response( $raw_response );

		return $this->sanitize_rewrite_response( $data );
	}

	/**
	 * Score how relevant a piece of content is for the target periferias audience.
	 *
	 * @param string   $title    Content title.
	 * @param string   $content  Content body text.
	 * @param string[] $keywords Optional additional keywords to emphasise.
	 *
	 * @return int Relevance score from 0 to 10.
	 */
	public function score_relevance( $title, $content, $keywords = array() ) {
		$system_prompt = $this->get_system_prompt_base();

		$keywords_str    = ! empty( $keywords ) ? "\nPalavras-chave extras: " . implode( ', ', $keywords ) : '';
		$content_trimmed = wp_trim_words( wp_strip_all_tags( $content ), 150, '' );

		$user_prompt = sprintf(
			"Avalie a relevância do conteúdo abaixo para leitores de periferias e comunidades vulneráveis do Brasil.\n\n" .
			"TÍTULO: %s\n" .
			"CONTEÚDO: %s\n" .
			"%s\n\n" .
			"Responda APENAS com um número inteiro de 0 a 10, onde:\n" .
			"0 = completamente irrelevante para periferias\n" .
			"5 = moderadamente relevante\n" .
			"10 = altamente relevante e urgente para comunidades periféricas\n\n" .
			"Responda SOMENTE com o número, nada mais.",
			esc_html( $title ),
			$content_trimmed,
			$keywords_str
		);

		try {
			$raw_response = $this->llm->complete( $user_prompt, $system_prompt, 'score_relevance', 16 );
			$score        = (int) trim( preg_replace( '/[^0-9]/', '', $raw_response ) );
			return min( 10, max( 0, $score ) );
		} catch ( Exception $e ) {
			JEP_Logger::warning( 'llm', 'Content Rewriter: falha ao pontuar relevância — ' . $e->getMessage() );
			return 0;
		}
	}

	/**
	 * Generate an image-generation prompt for the given article data.
	 *
	 * @param string   $title      Article title.
	 * @param string   $excerpt    Brief article excerpt.
	 * @param string[] $categories Associated category labels.
	 *
	 * @return string Descriptive image prompt suitable for Stable Diffusion / DALL-E.
	 */
	public function generate_image_prompt( $title, $excerpt, $categories = array() ) {
		$system_prompt = $this->get_system_prompt_base();

		$categories_str = is_array( $categories ) ? implode( ', ', $categories ) : (string) $categories;

		$user_prompt = sprintf(
			"Crie um prompt descritivo em inglês para geração de imagem jornalística.\n\n" .
			"TÍTULO DO ARTIGO: %s\n" .
			"RESUMO: %s\n" .
			"CATEGORIAS: %s\n\n" .
			"O prompt deve:\n" .
			"- Descrever uma cena fotográfica realista e respeitosa\n" .
			"- Representar comunidades periféricas brasileiras com dignidade\n" .
			"- Evitar estereótipos negativos\n" .
			"- Ser adequado para foto-jornalismo\n" .
			"- Ter entre 50 e 100 palavras\n\n" .
			"Retorne APENAS o prompt em inglês, sem explicações.",
			esc_html( $title ),
			esc_html( $excerpt ),
			$categories_str
		);

		try {
			return trim( $this->llm->complete( $user_prompt, $system_prompt, 'image_prompt', 256 ) );
		} catch ( Exception $e ) {
			JEP_Logger::warning( 'llm', 'Content Rewriter: falha ao gerar prompt de imagem — ' . $e->getMessage() );
			return "Brazilian community photo, documentary photography, urban periphery, dignified portrait";
		}
	}

	/**
	 * Generate a formatted Telegram summary message for the weekly stats digest.
	 *
	 * @param array $stats {
	 *     @type int    $posts_published  Number of posts published this week.
	 *     @type int    $items_processed  Number of RSS/cold items processed.
	 *     @type array  $top_categories   Most-used category names.
	 *     @type float  $avg_score        Average relevance score this week.
	 * }
	 *
	 * @return string Telegram-formatted message with Markdown.
	 */
	public function generate_weekly_summary_text( $stats = array() ) {
		$defaults = array(
			'posts_published' => 0,
			'items_processed' => 0,
			'top_categories'  => array(),
			'avg_score'       => 0.0,
		);

		$stats = array_merge( $defaults, $stats );

		$system_prompt = $this->get_system_prompt_base();

		$categories_str = is_array( $stats['top_categories'] ) ? implode( ', ', $stats['top_categories'] ) : '';

		$user_prompt = sprintf(
			"Gere uma mensagem de resumo semanal para o Telegram do Jornal Espaço do Povo.\n\n" .
			"DADOS DA SEMANA:\n" .
			"- Posts publicados: %d\n" .
			"- Itens processados: %d\n" .
			"- Categorias em destaque: %s\n" .
			"- Pontuação média de relevância: %.1f/10\n\n" .
			"A mensagem deve:\n" .
			"- Usar formatação Markdown do Telegram (negrito com **, itálico com _)\n" .
			"- Ter tom positivo e motivador para a equipe\n" .
			"- Incluir emojis relevantes\n" .
			"- Ter no máximo 300 caracteres\n" .
			"- Começar com 📊 *Resumo Semanal JEP*\n\n" .
			"Retorne APENAS a mensagem formatada.",
			(int) $stats['posts_published'],
			(int) $stats['items_processed'],
			$categories_str,
			(float) $stats['avg_score']
		);

		try {
			return trim( $this->llm->complete( $user_prompt, $system_prompt, 'weekly_summary', 512 ) );
		} catch ( Exception $e ) {
			JEP_Logger::warning( 'llm', 'Content Rewriter: falha ao gerar resumo semanal — ' . $e->getMessage() );

			// Fallback: build the summary manually without LLM.
			return sprintf(
				"📊 *Resumo Semanal JEP*\n\n" .
				"✅ Posts publicados: *%d*\n" .
				"📰 Itens processados: *%d*\n" .
				"⭐ Pontuação média: *%.1f/10*",
				(int) $stats['posts_published'],
				(int) $stats['items_processed'],
				(float) $stats['avg_score']
			);
		}
	}

	/**
	 * Parse a raw LLM text response that is expected to contain a JSON object.
	 *
	 * Strips surrounding Markdown code fences (```json … ``` or ``` … ```) before
	 * decoding, to handle common model output formatting habits.
	 *
	 * @param string $response Raw text from the LLM.
	 *
	 * @return array Decoded associative array.
	 *
	 * @throws Exception When valid JSON cannot be extracted from the response.
	 */
	public function parse_json_response( $response ) {
		$cleaned = trim( $response );

		// Strip opening/closing markdown code fences.
		if ( preg_match( '/^```(?:json)?\s*([\s\S]+?)\s*```$/i', $cleaned, $matches ) ) {
			$cleaned = $matches[1];
		}

		// Remove any BOM or zero-width characters.
		$cleaned = preg_replace( '/^\xEF\xBB\xBF/', '', $cleaned );
		$cleaned = trim( $cleaned );

		$data = json_decode( $cleaned, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			// Attempt a second pass: extract the first {...} block.
			if ( preg_match( '/\{[\s\S]+\}/', $cleaned, $json_match ) ) {
				$data = json_decode( $json_match[0], true );
			}

			if ( JSON_ERROR_NONE !== json_last_error() ) {
				throw new Exception(
					'Falha ao decodificar JSON da resposta LLM: ' . json_last_error_msg() .
					'. Resposta recebida: ' . substr( $response, 0, 300 )
				);
			}
		}

		if ( ! is_array( $data ) ) {
			throw new Exception( 'JSON decodificado não é um objeto/array válido.' );
		}

		return $data;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Return the base system prompt that underpins all content operations.
	 *
	 * @return string
	 */
	private function get_system_prompt_base() {
		return 'Você é um editor de conteúdo jornalístico brasileiro especializado em comunicação popular, '
			. 'focado em periferias e comunidades vulneráveis do Brasil. '
			. 'Escreva sempre de forma clara, direta e acessível para leitores com ensino médio. '
			. 'Use linguagem inclusiva e respeitosa. '
			. 'Evite jargões técnicos. '
			. 'Nunca invente fatos. '
			. 'Sempre baseie-se no conteúdo fornecido.';
	}

	/**
	 * Sanitize and normalise a decoded rewrite JSON payload.
	 *
	 * Ensures all expected keys are present and their values have the correct type.
	 *
	 * @param array $data Decoded JSON array from the LLM.
	 *
	 * @return array Normalised rewrite data.
	 */
	private function sanitize_rewrite_response( $data ) {
		return array(
			'titulo_a'      => isset( $data['titulo_a'] )     ? sanitize_text_field( $data['titulo_a'] )     : '',
			'titulo_b'      => isset( $data['titulo_b'] )     ? sanitize_text_field( $data['titulo_b'] )     : '',
			'excerpt'       => isset( $data['excerpt'] )      ? sanitize_text_field( $data['excerpt'] )      : '',
			'conteudo_html' => isset( $data['conteudo_html'] ) ? wp_kses_post( $data['conteudo_html'] )      : '',
			'hashtags'      => isset( $data['hashtags'] )     && is_array( $data['hashtags'] )
								? array_map( 'sanitize_text_field', $data['hashtags'] ) : array(),
			'categorias_wp' => isset( $data['categorias_wp'] ) && is_array( $data['categorias_wp'] )
								? array_map( 'sanitize_text_field', $data['categorias_wp'] ) : array(),
		);
	}
}
