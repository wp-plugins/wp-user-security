<?php
/*
	Plugin Name: WP User Security Plugin
	Plugin URI: http://www.tussendoor.nl
	Description: Reset the user(s) password by interval for optimal security
	Version: 1.0.1
	Author: Tussendoor internet & marketing
	Author URI: http://www.tussendoor.nl
	Tested up to: 4.2
*/

if ( ! defined('WP_USER_SECURITY_DIR')) define('WP_USER_SECURITY_DIR', dirname(__FILE__));
if ( ! defined('WP_USER_SECURITY_URL')) define('WP_USER_SECURITY_URL', plugins_url('wp-user-security'));

class WordpressUserSecurity {

	private $message 	= false;
	private $msg_class 	= '';

	public function __construct() {
		add_action('init', array($this, 'init'));
		add_action('admin_init', array($this, 'admin_init'));
		add_action('admin_menu', array($this, 'admin_menu'));
		add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
		add_action('wp_user_security_reset_pwd', array($this, 'change_passwords'));
		add_filter('cron_schedules', array($this, 'cron_add_schedule'));

		register_activation_hook( __FILE__, array($this, 'activation_action'));
		register_deactivation_hook(__FILE__, array($this, 'deactivation_action'));
	}

	public function init() {
		load_plugin_textdomain( 'wp_user_security', false, 'wp-user-security/languages/' );
	}

	public function admin_init() {
		if ( !is_plugin_active( 'worker/init.php' ) && (!class_exists('MWP_Configuration_Conf') || !class_exists('MWP_Configuration_Service')) ):
			add_action( 'admin_notices', array($this, 'activate_managewp') );
		endif;
	}

	public function admin_menu() {
		add_menu_page(__('Security', 'wp_user_security'), __('Security', 'wp_user_security'), 'manage_options', 'wp_user_security', array(&$this, 'settings'), WP_USER_SECURITY_URL.'/resources/managewp-security_white.png');
		add_submenu_page ('wp_user_security', __('Settings', 'wp_user_security'), __('Settings', 'wp_user_security'), 'manage_options', 'wp_user_security_settings', array(&$this, 'settings'));
		remove_submenu_page('wp_user_security', 'wp_user_security');
	}

	public function admin_enqueue_scripts() {
		wp_register_style('wp_user_security_admin', WP_USER_SECURITY_URL . '/resources/admin.css');
		wp_enqueue_style('wp_user_security_admin');
	}

	public function settings() {
		if( $_POST ) {
			update_option('wp_user_security_cron', $_POST['wp_user_security_cron']);
			update_option('wp_user_security_users', $_POST['wp_user_security_users']);
			$success = $this->set_cron();
			if( !$success ):
				$this->message 		= __('Error occurred trying to save the settings.', 'wp_user_security');
				$this->msg_class 	= 'error';
			else:
				$this->message 		= __('Settings successfully saved.', 'wp_user_security');
				$this->msg_class 	= 'updated';
			endif;
		}
		$this->change_passwords();
		require_once(WP_USER_SECURITY_DIR . '/views/admin/settings.php');
	}

	public function activate_managewp() {
	    echo '<div class="update-nag" style="padding: 1px 12px;">';
	        echo '<p>'.sprintf(__( 'We recommend you to use the %s ManageWP %s services when using this plugin.', 'wp_user_security' ), '<a href="https://managewp.com/?utm_source=A&utm_medium=Link&utm_campaign=A&utm_mrl=2230">', '</a>').'</p>';
	    echo '</div>';
	}

	private function show_message() {
		if( $this->message ):
			echo '<div class="'.$this->msg_class.'">';
				echo '<p>'.$this->message.'</p>';
			echo '</div>';
		endif;
	}

	private function change_passwords() {
		$cron 	= get_option('wp_user_security_cron');
		$users 	= get_option('wp_user_security_users');
		if( $cron && $cron !== 'disabled' ):
			foreach ($users as $user_id):
				wp_set_password( $this->gen_pwd(), $user_id );
			endforeach;
		endif;
	}

	public function activation_action() {
		$cron 	= get_option('wp_user_security_cron');
		if( $cron && $cron !== '' ):
			$this->set_cron();
		endif;
	}

	public function deactivation_action() {
		wp_clear_scheduled_hook( 'wp_user_security_reset_pwd' );
	}
	
	public function cron_add_schedule( $schedules ) {

		$schedules['weekly'] = array(
			'interval' => 604800,
			'display' => __( 'Once Weekly' )
		);

		$schedules['monthly'] = array(
			'interval' => 2592000,
			'display' => __( 'Once Monthly' )
		);

		return $schedules;
	}

	private function set_cron() {
		$cron 		= get_option('wp_user_security_cron');
		$schedules 	= wp_get_schedules();

		if( $cron == 'daily' || $cron == 'weekly' || $cron == 'monthly' && isset($schedules[$cron]) ):
			wp_clear_scheduled_hook( 'wp_user_security_reset_pwd' );
			wp_schedule_event( time(), $cron, 'wp_user_security_reset_pwd' );
			return true;
		elseif( $cron == 'disabled' ):
			wp_clear_scheduled_hook( 'wp_user_security_reset_pwd' );
			return true;
		else:
			wp_clear_scheduled_hook( 'wp_user_security_reset_pwd' );
			return false;
		endif;
	}

	private function gen_pwd( $length = 100 ) {
		$return = '';
		$chars 	=  'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-=!@#$%^&*()_+,./?;:[]{}|';
		$max 	= strlen($chars) - 1;

  		for ($i=0; $i < $length; $i++)
    		$return .= substr(str_shuffle($chars), 0, 1);
		return $return;
	} 
}

new WordpressUserSecurity();
?>