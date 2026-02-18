<?php
/**
 * Plugin Name:       JEP Automacao Editorial
 * Plugin URI:        https://jornalespacodopovo.com.br
 * Description:       Automacao editorial do Jornal Espaco do Povo: integra WordPress com n8n para geracao, aprovacao e distribuicao de conteudo multi-canal.
 * Version:           1.0.0
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

// Constantes do plugin
define( 'JEP_AUTOMACAO_VERSION', '1.0.0' );
define( 'JEP_AUTOMACAO_PLUGIN_FILE', __FILE__ );
define( 'JEP_AUTOMACAO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JEP_AUTOMACAO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JEP_AUTOMACAO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Carrega as dependencias do plugin.
 */
function jep_automacao_autoload( $class_name ) {
	$prefix = 'JEP_';
	if ( strpos( $class_name, $prefix ) !== 0 ) {
		return;
	}

	$class_file = strtolower( str_replace( '_', '-', $class_name ) );
	$file       = JEP_AUTOMACAO_PLUGIN_DIR . 'includes/class-' . $class_file . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
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

// Inicializa.
add_action( 'plugins_loaded', 'jep_automacao' );

/**
 * Ativacao: cria tabela de logs.
 */
register_activation_hook( __FILE__, array( 'JEP_Installer', 'activate' ) );

/**
 * Desativacao.
 */
register_deactivation_hook( __FILE__, array( 'JEP_Installer', 'deactivate' ) );
