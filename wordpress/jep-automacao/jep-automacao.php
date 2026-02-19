<?php
/**
 * Plugin Name:       JEP Automacao Editorial
 * Plugin URI:        https://jornalespacodopovo.com.br
 * Description:       Automacao editorial self-contained: LLM pool, pautas frias, RSS diario, aprovacao Telegram A/B, bot interativo, publicacao Instagram, descoberta de fontes, avaliacao de qualidade de prompts.
 * Version:           2.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Jornal Espaco do Povo
 * Author URI:        https://jornalespacodopovo.com.br
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jep-automacao
 * Domain Path:       /languages
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;

define( 'JEP_AUTOMACAO_VERSION', '2.0.0' );
define( 'JEP_AUTOMACAO_PLUGIN_FILE', __FILE__ );
define( 'JEP_AUTOMACAO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JEP_AUTOMACAO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JEP_AUTOMACAO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader modular: busca em subdiretorios antes do legado.
 */
function jep_automacao_autoload( $class_name ) {
	if ( strpos( $class_name, 'JEP_' ) !== 0 ) {
		return;
	}

	$class_file = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

	$search_dirs = array(
		'includes/core/',
		'includes/llm/',
		'includes/content/',
		'includes/telegram/',
		'includes/distribution/',
		'includes/quality/',
		'includes/image/',
		'includes/api/',
		'includes/admin/',
	);

	foreach ( $search_dirs as $dir ) {
		$file = JEP_AUTOMACAO_PLUGIN_DIR . $dir . $class_file;
		if ( file_exists( $file ) ) {
			require_once $file;
			return;
		}
	}
}
spl_autoload_register( 'jep_automacao_autoload' );

/**
 * Instancia principal do plugin.
 *
 * @return JEP_Automacao
 */
function jep_automacao() {
	return JEP_Automacao::instance();
}

add_action( 'plugins_loaded', 'jep_automacao' );

register_activation_hook( __FILE__, array( 'JEP_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'JEP_Installer', 'deactivate' ) );
