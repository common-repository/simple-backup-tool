<?php
/*
Plugin Name: Simple Backup Tool (by SiteGuarding.com)
Plugin URI: http://www.siteguarding.com/en/website-extensions
Description: Full backup of WordPress website
Version: 2.2
Author: SiteGuarding.com (SafetyBis Ltd.)
Author URI: http://www.siteguarding.com
License: GPLv2
*/ 
// rev.20200601
define('_SITEGUARDING_BKUP', 1);
define('_SITEGUARDING_BKUP_CORE_UPDATE', true);

if (!defined('DIRSEP'))
{
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') define('DIRSEP', '\\');
    else define('DIRSEP', '/');
}

include_once(dirname(__FILE__).DIRSEP.'classes'.DIRSEP.'func_general.php');

if( !is_admin() ) 
{
    if (isset($_GET['siteguarding_tools']) && intval($_GET['siteguarding_tools']) == 1)
    {
        plgbkup_CopySiteGuardingTools();
    }
}

/**
 * Important functions
 */
function plgbkup_API_Request($type = '')
{
    $plugin_code = 4;
    $website_url = get_site_url();
    
    $url = "https://www.siteguarding.com/ext/plugin_api/index.php";
    $response = wp_remote_post( $url, array(
        'method'      => 'POST',
        'timeout'     => 600,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'    => true,
        'headers'     => array(),
        'body'        => array(
            'action' => 'inform',
            'website_url' => $website_url,
            'action_code' => $type,
            'plugin_code' => $plugin_code,
        ),
        'cookies'     => array()
        )
    );
}

function plgbkup_CopySiteGuardingTools()
{
    $file_from = dirname(__FILE__).'/siteguarding_tools.php';
	if (!file_exists($file_from)) die('File absent');
    $file_to = ABSPATH.'/siteguarding_tools.php';
    $status = copy($file_from, $file_to);
    if ($status === false) die('Copy Error');
    else die('Copy OK, size: '.filesize($file_to).' bytes');
}
function plgbkup_Copy_SG_tools_file()
{
    $file_from = dirname(__FILE__).'/siteguarding_tools.php';
	if (file_exists($file_from))
	{
		$file_to = ABSPATH.'/siteguarding_tools.php';
		$status = copy($file_from, $file_to);
	}
}


if( is_admin() ) 
{
    
    add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'plgbkup_add_action_link', 10, 2 );
    function plgbkup_add_action_link( $links, $file )
    {
  		$faq_link = '<a target="_blank" href="https://www.siteguarding.com/en/importance-of-website-backup">Get Backup Service</a>';
		array_unshift( $links, $faq_link );
        
  		$faq_link = '<a target="_blank" href="https://www.siteguarding.com/en/contacts">Help</a>';
		array_unshift( $links, $faq_link );
        
  		$faq_link = '<a href="admin.php?page=plgbkup_Backup">Dashboard</a>';
		array_unshift( $links, $faq_link );

		return $links;
    }
    

    
	function plgbkup_activation()
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'plgbkup_config';
		if( $wpdb->get_var( 'SHOW TABLES LIKE "' . $table_name .'"' ) != $table_name ) {
			$sql = 'CREATE TABLE IF NOT EXISTS '. $table_name . ' (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `var_name` char(255) CHARACTER SET utf8 NOT NULL,
                `var_value` LONGTEXT CHARACTER SET utf8 NOT NULL,
                PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;';

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql ); // Creation of the new TABLE
		}
        
        plgbkup_Copy_SG_tools_file();
        plgbkup_API_Request(1);
	}
	register_activation_hook( __FILE__, 'plgbkup_activation' );
    
	function plgbkup_uninstall()
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'plgbkup_config';
		$wpdb->query( 'DROP TABLE ' . $table_name );
        
        plgbkup_API_Request(3);
	}
	register_uninstall_hook( __FILE__, 'plgbkup_uninstall' );
    
    
	function plgbkup_deactivation()
	{
        plgbkup_API_Request(2);
	}
    register_deactivation_hook( __FILE__, 'plgbkup_deactivation' );
    
    
	add_action( 'admin_init', 'plgbkup_admin_init' );
	function plgbkup_admin_init()
	{
	
		wp_register_style( 'plgbkup_LoadStyle_UI', plugins_url('assets/semantic.min.css', __FILE__) );

        wp_register_script('plgbkup_LoadJS_UI', plugins_url('assets/semantic.min.js', __FILE__) , array (), false, false);	
	}
	add_action( 'init', 'FUNC_BKUP_general::checkActions' );   
    
    /**
     * AJAX  
     */


	
    add_action( 'wp_ajax_plgbkup_ajax_backup_full', 'plgbkup_ajax_backup_full' );
    function plgbkup_ajax_backup_full() 
    {
	    include_once(dirname(__FILE__).DIRSEP.'classes'.DIRSEP.'func_general.php');
        
        FUNC_BKUP_general::Init();

        FUNC_BKUP_general::BackupFull();

        echo 'OK';
        wp_die();
    }
    
    add_action( 'wp_ajax_plgbkup_ajax_backup_files', 'plgbkup_ajax_backup_files' );
    function plgbkup_ajax_backup_files() 
    {
	    include_once(dirname(__FILE__).DIRSEP.'classes'.DIRSEP.'func_general.php');
        
        FUNC_BKUP_general::Init();

        FUNC_BKUP_general::BackupFiles();

        echo 'OK';
        wp_die();
    }
    
    add_action( 'wp_ajax_plgbkup_ajax_backup_sql', 'plgbkup_ajax_backup_sql' );
    function plgbkup_ajax_backup_sql() 
    {
	    include_once(dirname(__FILE__).DIRSEP.'classes'.DIRSEP.'func_general.php');
        
        FUNC_BKUP_general::Init();

        FUNC_BKUP_general::BackupSQL();

        echo 'OK';
        wp_die();
    }
    

    
    
    /**
     * Page Dashboard   
     */
    add_action('admin_menu', 'register_plgbkup_dashboard_page');
	function register_plgbkup_dashboard_page() 
	{
		add_menu_page('plgbkup_Backup', 'WordPress Backup', 'activate_plugins', 'plgbkup_Backup', 'plgbkup_dashboard_page', plugins_url('images/', __FILE__).'simple_backup.svg');
   		add_submenu_page( 'plgbkup_Backup', 'Dashboard', 'Dashboard', 'manage_options', 'plgbkup_Backup', 'plgbkup_dashboard_page' );

	}

	function plgbkup_dashboard_page() 
	{
	    include_once(dirname(__FILE__).DIRSEP.'classes'.DIRSEP.'func_general.php');

        FUNC_BKUP_general::Init();

        FUNC_BKUP_dashboard::PageHTML();
        
        FUNC_BKUP_general::ModalPopup();
    }
    
    
    
    /**
     * Page Settings
     */
	add_action('admin_menu', 'register_plgbkup_settings_subpage');
	function register_plgbkup_settings_subpage() {
		add_submenu_page( 'plgbkup_Backup', 'Settings', 'Settings', 'manage_options', 'plgbkup_settings_page', 'plgbkup_settings_page' ); 
	}

	function plgbkup_settings_page() 
	{
	    include_once(dirname(__FILE__).DIRSEP.'classes'.DIRSEP.'func_general.php');
        
        FUNC_BKUP_general::Init();
        
        FUNC_BKUP_settings::PageHTML();
        
        FUNC_BKUP_general::ModalPopup();
    }
    
    

    /**
     * Page Extensions
     */
	add_action('admin_menu', 'register_plgbkup_extensions_subpage');
	function register_plgbkup_extensions_subpage() {
		add_submenu_page( 'plgbkup_Backup', 'Security Extensions', 'Security Extensions', 'manage_options', 'plgbkup_extensions_page', 'plgbkup_extensions_page' ); 
	}

	function plgbkup_extensions_page() 
	{
	    include_once(dirname(__FILE__).DIRSEP.'classes'.DIRSEP.'func_general.php');
        
        FUNC_BKUP_general::Init();
        
        FUNC_BKUP_extensions::PageHTML();
        
        FUNC_BKUP_general::ModalPopup();
    }

}

    
    /**
     * Cron jobs
     */
	add_filter( 'cron_schedules', 'plgbkup_cron_day' );
	function plgbkup_cron_day( $schedules ) {
		$schedules['one_per_day'] = array(
			'interval' => 60 * 60 * 24,
			'display' => 'one per day'
		);
		return $schedules;
	}
	
	add_filter( 'cron_schedules', 'plgbkup_cron_three_days' );
	function plgbkup_cron_three_days( $schedules ) {
		$schedules['one_per_three_days'] = array(
			'interval' => 60 * 60 * 24 * 3,
			'display' => 'one per three days'
		);
		return $schedules;
	}
	
	add_filter( 'cron_schedules', 'plgbkup_cron_week' );
	function plgbkup_cron_week( $schedules ) {
		$schedules['one_per_week'] = array(
			'interval' => 60 * 60 * 24 * 7,
			'display' => 'one per week'
		);
		return $schedules;
	}
	
	add_filter( 'cron_schedules', 'plgbkup_cron_month' );
	function plgbkup_cron_month( $schedules ) {
		$schedules['one_per_month'] = array(
			'interval' => 60 * 60 * 24 * 30,
			'display' => 'one per month'
		);
		return $schedules;
	}


	add_action( 'plugins_loaded', 'plgbkup_init_autobackup' );
	add_action( 'getting_backup_sql', 'plgbkup_backup_sql' );
	add_action( 'getting_backup_files', 'plgbkup_backup_files' );
	
	function plgbkup_backup_sql() {

        FUNC_BKUP_general::Init();

        FUNC_BKUP_general::BackupSQL();
		
		return true;
	}
	
	function plgbkup_backup_files() {

        FUNC_BKUP_general::Init();

        FUNC_BKUP_general::BackupFiles();

		return true;
	}

	function plgbkup_init_autobackup() {
		FUNC_BKUP_general::Init();
		
		$params = FUNC_BKUP_general::Get_SQL_Params(array(
														'autobackup_sql',
														'autobackup_files',
														'autoremove',
													));
													
		if (!$params || count($params) == 0) return false;
		
		switch ((int) $params['autobackup_sql']) {
			case 0:
				$autobackup_sql = false;
				break;
			case 1:
				$autobackup_sql = 'one_per_day';
				break;
			case 3:
				$autobackup_sql = 'one_per_three_days';
				break;
			case 7:
				$autobackup_sql = 'one_per_week';
				break;
			case 30:
				$autobackup_sql = 'one_per_month';
				break;
			default:
				$autobackup_sql = false;
				break;
		}

		if ($autobackup_sql) {
			if( ! wp_next_scheduled( 'getting_backup_sql' ) ) {  
				wp_schedule_event( time(), $autobackup_sql, 'getting_backup_sql');  
			} else if (wp_get_schedule( 'getting_backup_sql' ) != $autobackup_sql) {
				wp_clear_scheduled_hook( 'getting_backup_sql' );
				wp_schedule_event( time(), $autobackup_sql, 'getting_backup_sql');
			}
		} else {
			wp_clear_scheduled_hook( 'getting_backup_sql' );
		}
		
		switch ((int) $params['autobackup_files']) {
			case 0:
				$autobackup_files = false;
				break;
			case 1:
				$autobackup_files = 'one_per_day';
				break;
			case 3:
				$autobackup_files = 'one_per_three_days';
				break;
			case 7:
				$autobackup_files = 'one_per_week';
				break;
			case 30:
				$autobackup_files = 'one_per_month';
				break;
			default:
				$autobackup_files = false;
				break;
		}
		
		if ($autobackup_files) {
			if( ! wp_next_scheduled( 'getting_backup_files' ) ) {  
				wp_schedule_event( time(), $autobackup_files, 'getting_backup_files');  
			} else if (wp_get_schedule( 'getting_backup_files' ) != $autobackup_files) {
				wp_clear_scheduled_hook( 'getting_backup_files' );
				wp_schedule_event( time(), $autobackup_files, 'getting_backup_files');
			}
		} else {
			wp_clear_scheduled_hook( 'getting_backup_files' );
		}
		
		$autoremove = $params['autoremove'];
		
		if ($autoremove) {
			$filesBackups = glob(_SITEGUARDING_BKUP_FOLDER."backup_files*.*");
			if (is_array($filesBackups) && !empty($filesBackups)) {
				usort($filesBackups, function($a, $b) { return filemtime($b) - filemtime($a); });
				for($i = 0; $i < count($filesBackups); $i++) {
					if ($i >= $autoremove) unlink($filesBackups[$i]);
				}
			}
			$sqlBackups = glob(_SITEGUARDING_BKUP_FOLDER."backup_sql*.sql");
			if (is_array($sqlBackups) && !empty($sqlBackups)) {
				usort($sqlBackups, function($a, $b) { return filemtime($b) - filemtime($a); });
				for($i = 0; $i < count($sqlBackups); $i++) {
					if ($i >= $autoremove) unlink($sqlBackups[$i]);
				}
			}
		}

	}
