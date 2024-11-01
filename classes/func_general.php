<?php
defined('_SITEGUARDING_BKUP') or die;

class FUNC_BKUP_general
{
    public static $SITEGUARDING_SERVER = 'http://www.siteguarding.com/ext/antivirus/index.php';
    public static $LINKS = array(
        'get_backup_service' => 'https://www.siteguarding.com/en/importance-of-website-backup',
        'contact_support' => 'https://www.siteguarding.com/en/contacts',
        'upgrade_to_premium' => 'https://www.siteguarding.com/en/buy-service/antivirus-complete-website-protection',
        
        'clean_website' => 'https://www.siteguarding.com/en/services/malware-removal-service',
        'blacklist_removal' => 'https://www.siteguarding.com/en/website-blacklist-removal-service',
        'free_website_check' => 'https://www.siteguarding.com/en/sitecheck',
        'protect_your_website' => 'https://www.siteguarding.com/en/protect-your-website',
        'learn_bruteforce' => 'https://www.siteguarding.com/en/bruteforce-attack',
        'backup_service' => 'https://www.siteguarding.com/en/importance-of-website-backup',
    );
    
    public static function Init()  
    {
		if (!defined('ABSPATH') || strlen(ABSPATH) < 8) 
		{
			$scan_path = dirname(dirname(dirname(dirname(__FILE__))));
		}
        else $scan_path = ABSPATH;
        
        if (!defined('SG_SITE_ROOT')) define('SG_SITE_ROOT', $scan_path.DIRSEP);

        
        $plugin_dir = dirname(dirname(__FILE__)).DIRSEP;
        
        include_once($plugin_dir.'classes'.DIRSEP.'page_dashboard.php');
        include_once($plugin_dir.'classes'.DIRSEP.'page_settings.php');
        include_once($plugin_dir.'classes'.DIRSEP.'page_extensions.php');
        
        if (session_status() == PHP_SESSION_NONE) 
        {
            @session_start();
        }
        
        if (!file_exists($plugin_dir.'tmp')) mkdir($plugin_dir.'tmp');
        if (!file_exists($plugin_dir.'tmp'.DIRSEP.'.htaccess')) self::CreateFile($plugin_dir.'tmp'.DIRSEP.'.htaccess', "<Limit GET POST>\norder deny,allow\ndeny from all\n</Limit>");
        
        
        if (!file_exists(SG_SITE_ROOT.'webanalyze')) 
        {
            mkdir(SG_SITE_ROOT.'webanalyze');
            self::CreateFile(SG_SITE_ROOT.'webanalyze'.DIRSEP.'index.html', '<html><body bgcolor="#FFFFFF"></body></html>');
        }
        
        if (!file_exists(SG_SITE_ROOT.'webanalyze'.DIRSEP.'backup.php')) copy($plugin_dir.'backup.php', SG_SITE_ROOT.'webanalyze'.DIRSEP.'backup.php');
        $file_backup_config = SG_SITE_ROOT.'webanalyze'.DIRSEP.'backup_config.php';
        if (!file_exists($file_backup_config)) 
        {
            // Create backup_config.php
            $file_content = '<?php'."\n".
                'define("DEBUG_LOG", false);'."\n".
                'define("MAX_FILE_SIZE", 20971520);	// in bytes'."\n".
                'define("BACKUP_EXCLUDE_FILE_EXTENSIONS", "*.avi;*.zip;*.tar;*.gz;*.bak;error_log;");	// *.jpg;*.mp3;	separate by ; (and ; in the end) '."\n".
                '$BACKUP_EXCLUDE_FOLDERS =  array("/webanalyze/", "/tmp/", "/cache/", "/wp-content/cache/", "/backups/", "/var/cache/", "/var/session/", "/var/tmp/");'."\n".
                '?>'."\n";
            self::CreateFile($file_backup_config, $file_content);
        }
        
        $file_monitor_config = SG_SITE_ROOT.'webanalyze'.DIRSEP.'config.php';
        if (!file_exists($file_monitor_config)) 
        {
            // Create config.php
            $file_content = '<?php'."\n".
                '/* created by simple backup plugin */'."\n".
                'define("WEBSITE_ID", "0");'."\n".
                'define("WEBSITE_KEY", "'.md5(time().'-'.__FILE__.'-'.rand(0, 10000)).'");'."\n".
                '?>'."\n";
            self::CreateFile($file_monitor_config, $file_content);
        }
        
        if (!file_exists(SG_SITE_ROOT.'webanalyze')) 
        {
            $_SESSION['session_plgbkup_alert_message'] = "Can't create folder: ".SG_SITE_ROOT."webanalyze Please create it manually.";
        }
        
        
        $backup_folder = dirname(dirname(dirname(dirname(__FILE__)))).DIRSEP.'simple-backup'.DIRSEP;
        if (!defined('_SITEGUARDING_BKUP_FOLDER')) define('_SITEGUARDING_BKUP_FOLDER', $backup_folder);
        if (!file_exists($backup_folder))
        {
            mkdir($backup_folder);
        }
            
        if (!file_exists(_SITEGUARDING_BKUP_FOLDER.'.htaccess')) self::CreateFile(_SITEGUARDING_BKUP_FOLDER.'.htaccess', "<Limit GET POST>\norder deny,allow\ndeny from all\n</Limit>");
        
        // Set some values automatically
        $settings_list = array('autobackup_sql', 'autobackup_files', 'autoremove');
        $params = FUNC_BKUP_general::Get_SQL_Params($settings_list);
        if (!isset($params['autobackup_sql'])) FUNC_BKUP_general::Set_SQL_Params( array('autobackup_sql' => 0) );
        if (!isset($params['autobackup_files'])) FUNC_BKUP_general::Set_SQL_Params( array('autobackup_files' => 0) );
        if (!isset($params['autoremove'])) FUNC_BKUP_general::Set_SQL_Params( array('autoremove' => 0) );
    } 
    
    
    public static function BackupSQL()
    {
        include_once(SG_SITE_ROOT.'webanalyze'.DIRSEP.'config.php');
        
        $task = 'backup_sql';
        $session_key = strtoupper(md5(WEBSITE_KEY));
        $folder = _SITEGUARDING_BKUP_FOLDER;
        
		return self::SendRequest($task, $session_key, $folder);
        //include_once(SG_SITE_ROOT.'webanalyze'.DIRSEP.'backup.php');
    }
	
	public static function SendRequest($task, $sess_key, $folder) {
		if (is_file(SG_SITE_ROOT.'webanalyze'.DIRSEP.'backup.php')) {
			return self::get_url_response(get_site_url() . "/webanalyze/backup.php?task=$task&session_key=$sess_key&folder=$folder");
		}
		return false;
	}
	
	public static function get_url_response($url) {
		if (function_exists('curl_init')) {
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_HEADER, 0);
			curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1; rv:31.0) Gecko/20100101 Firefox/31.0'); 
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_URL, $url); 
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
			$code = curl_exec($curl); 
			curl_close($curl); 
			if (!$code) {
				$code = file_get_contents($url);
			}
		} elseif (function_exists('file_get_contents')) {
			$code = file_get_contents($url);
		} else {
			$code = false;
		}
			return $code;
	}
	
    
    public static function BackupFiles()
    {
        include_once(SG_SITE_ROOT.'webanalyze'.DIRSEP.'config.php');
        
        $task = 'backup_files';
        $session_key = strtoupper(md5(WEBSITE_KEY));
        $folder = _SITEGUARDING_BKUP_FOLDER;
		
        return self::SendRequest($task, $session_key, $folder);
        //include_once(SG_SITE_ROOT.'webanalyze'.DIRSEP.'backup.php');
    }
    
    public static function BackupFull()
    {
        self::BackupSQL();
        self::BackupFiles();
    }
    
    
    
    public static function Is_FULL()
    {
        include_once(SG_SITE_ROOT.'webanalyze'.DIRSEP.'config.php');
        if (intval(WEBSITE_ID) > 0) return true;
        else return false;
    }
    
    
    
    
    public static function Get_Backups_Local()
    {
        $a = array();
        /*
                                <tr><th>Filename</th>
                                <th>Size (Mb)</th>
                                <th>Date</th>
                                <th>Type</th>
        
        */
        
        foreach (glob(_SITEGUARDING_BKUP_FOLDER."backup_sql*.sql") as $filename) 
        {
            $tmp_row = array();
            $tmp_row['filename'] = basename($filename);
            $tmp_row['size'] = round(filesize($filename) / 1024 / 1024, 2);
            $tmp = explode("__", $tmp_row['filename']);
            $tmp = explode("_", $tmp[1]);
            $tmp_row['date'] = $tmp[0].'-'.$tmp[1].'-'.$tmp[2].' '.$tmp[3].':'.$tmp[4].':'.$tmp[5];
            $tmp_row['type'] = 'database';
            
            $a[ strtotime($tmp_row['date']) ] = $tmp_row;
        }
        
        foreach (glob(_SITEGUARDING_BKUP_FOLDER."backup_files*.*") as $filename) 
        {
            $tmp_row = array();
            $tmp_row['filename'] = basename($filename);
            $tmp_row['size'] = round(filesize($filename) / 1024 / 1024, 2);
            $tmp = explode("__", $tmp_row['filename']);
            $tmp = explode("_", $tmp[1]);
            $tmp_row['date'] = $tmp[0].'-'.$tmp[1].'-'.$tmp[2].' '.$tmp[3].':'.$tmp[4].':'.$tmp[5];
            $tmp_row['type'] = 'files';
            
            $a[ strtotime($tmp_row['date']) ] = $tmp_row;
        }
        
        krsort($a);
        return $a;
    }



    
    public static function SaveLog($log_file, $content)
    {
        $log_filesize = filesize($log_file);
        if ( $log_filesize > self::$MAX_LOG_FILE*1024*1024)
        {
            // Cut log file
    	    $log_file_tmp = $log_file.".tmp";
            
            $fp1 = fopen($log_file, "rb");
            $fp2 = fopen($log_file_tmp, "wb");
            
            $pos = $log_filesize * 0.7;     // 30%
            fseek($fp1, $pos);
            
            while (!feof($fp1)) {
                $buffer = fread($fp1, 4096 * 32);
                fwrite($fp2, $buffer);
            }
            
            fclose($fp1);
            fclose($fp2);
        }
        
        $fp = fopen($log_file, 'a');
        fwrite($fp, $content."\n");
        fclose($fp);
    }

    
    
    public static function CreateFile($file, $content)
    {
        if (file_exists($file)) unlink($file);
        $fp = fopen($file, 'w');
        $status = fwrite($fp, $content);
        fclose($fp);
    
        return $status;
    }
    
    
    public static function ReadFile($file, $json_decode = false)
    {
        $handle = fopen($file, "r");
        $contents = fread($handle, filesize($file));
        fclose($handle);
        
        if ($json_decode) $contents = (array)json_decode($contents, true);
    
        return $contents;
    }
    
    
    
    public static function GetDomain()
    {
        return self::PrepareDomain(get_site_url());
        
    }
    
	public static function PrepareDomain($domain)
	{
	    $host_info = parse_url($domain);
	    if ($host_info == NULL) return false;
	    $domain = $host_info['host'];
	    if ($domain[0] == "w" && $domain[1] == "w" && $domain[2] == "w" && $domain[3] == ".") $domain = str_replace("www.", "", $domain);
	    //$domain = str_replace("www.", "", $domain);
	    
	    return $domain;
	}
    
    


	public static function DownloadFromWordpress_Link($link)
	{
	    return self::DownloadRemoteFile($link, dirname(dirname(__FILE__)).DIRSEP.'tmp'.DIRSEP.'update.zip');
	}
    
	public static function DownloadRemoteFile($link, $file)
	{
		$dst = fopen($file, 'w');
		$ch = curl_init();
		 curl_setopt($ch, CURLOPT_URL, $link );
		 curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:38.0) Gecko/20100101 Firefox/38.0");
		 //curl_setopt($ch, CURLOPT_HEADER, true);
		 curl_setopt($ch, CURLOPT_TIMEOUT, 3600);
		 curl_setopt($ch, CURLOPT_TIMEOUT_MS, 3600000);
		 curl_setopt($ch, CURLOPT_FILE, $dst);
		 //!dont need curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
         //curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 sec
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 10000); // 10 sec
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
		//*** maybe need */curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

		 $a = curl_exec($ch);
		 curl_close($ch);
         if ($a === false) return false;
         else return true;
	}

    
    
    public static function Get_SQL_Params($var_name_arr = array())
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'plgbkup_config';
        
        $ppbv_table = $wpdb->get_results("SHOW TABLES LIKE '".$table_name."'" , ARRAY_N);
        if(!isset($ppbv_table[0])) return false;
        
        if (count($var_name_arr) > 0) 
        {
            foreach ($var_name_arr as $k => $v) 
            {
                $var_name_arr[$k] = "'".$v."'";
            }
            $sql_where = "WHERE var_name IN (".implode(",", $var_name_arr).")";
        }
        else $sql_where = '';
        $rows = $wpdb->get_results( 
        	"
        	SELECT *
        	FROM ".$table_name."
        	".$sql_where
        );
        
        $a = array();
        if (count($rows))
        {
            foreach ( $rows as $row ) 
            {
            	$a[trim($row->var_name)] = trim($row->var_value);
            }
        }
    
        return $a;
    }
    
    
    public static function Set_SQL_Params($data = array())
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'plgbkup_config';
    
        if (count($data) == 0) return;   
        
        foreach ($data as $k => $v)
        {
            $tmp = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $table_name . ' WHERE var_name = %s LIMIT 1;', $k ) );
            
            if ($tmp == 0)
            {
                // Insert    
                $wpdb->insert( $table_name, array( 'var_name' => $k, 'var_value' => $v ) ); 
            }
            else {
                // Update
                $data = array('var_value'=>$v);
                $where = array('var_name' => $k);
                $wpdb->update( $table_name, $data, $where );
            }
        } 
    }
    

	public static function PrintSteps($data)
	{
	   switch (count($data))
       {
            case 1:
                $count_txt = 'one';
                break;
                
            case 2:
                $count_txt = 'two';
                break;
                
            case 3:
                $count_txt = 'three';
                break;
                
            case 4:
                $count_txt = 'four';
                break;
                
            case 5:
                $count_txt = 'five';
                break;
                
            case 6:
                $count_txt = 'six';
                break;
                
            case 7:
                $count_txt = 'seven';
                break;
                
            case 8:
                $count_txt = 'eight';
                break;
                
            case 9:
                $count_txt = 'nine';
                break;
       }
	   ?>
            <div class="ui <?php echo $count_txt; ?> steps">
                <?php
                foreach ($data as $row)
                {
                    ?>
                      <div class="<?php echo $row['active']; ?> step">
                        <i class="<?php echo $row['icon']; ?> icon"></i>
                        <div class="content">
                          <div class="title"><?php echo $row['title']; ?></div>
                          <div class="description"><?php echo $row['description']; ?></div>
                        </div>
                      </div>
                    <?php
                }
                ?>
            </div>
        <?php
    }


    

	public static function QuickLinks()
	{
	   ?>
            <div class="ui four item massive menu">
              <a class="item" href="admin.php?page=plgbkup_settings_page"><i class="cogs icon"></i>Settings</a>
              <a class="item" target="_blank" href="<?php echo self::$LINKS['contact_support']; ?>"><i class="comment outline icon"></i>Help</a>
              <a class="item" href="admin.php?page=plgbkup_extensions_page"><i class="shield alternate icon"></i>Security extensions</a>
              <a class="item" target="_blank" href="<?php echo self::$LINKS['get_backup_service']; ?>"><i class="cloud icon green"></i>Secured backup service</a>
            </div>
        <?php
    }

	public static function BannerArea()
	{
        ?>
        <div class="ui large centered sg_center ad">
            <a href="https://www.siteguarding.com/en/protect-your-website" target="_blank"><img src="<?php echo plugins_url('images/rek1.png', dirname(__FILE__)); ?>" /></a>&nbsp;
            <a href="https://www.siteguarding.com/en/secure-web-hosting" target="_blank"><img src="<?php echo plugins_url('images/rek2.png', dirname(__FILE__)); ?>" /></a>&nbsp;
            <a href="https://www.siteguarding.com/en/importance-of-website-backup" target="_blank"><img src="<?php echo plugins_url('images/rek3.png', dirname(__FILE__)); ?>" /></a>
        </div>
        <?php
    }
    



	public static function ModalPopup()
	{
        if (isset($_SESSION['session_plgbkup_alert_message']))
        {
            ?>
            <script>
            jQuery(document).ready(function(){
                jQuery('.modal').modal('show');
            });
            
            </script>
            <div class="tiny ui modal">
              <div class="header c_red">Alert</div>
              <div class="content">
                <p><?php echo $_SESSION['session_plgbkup_alert_message']; ?></p>
              </div>
              <div class="actions">
                <button class="medium ui cancel button">Close</button>
              </div>
            </div>
            <?php
            
            unset($_SESSION['session_plgbkup_alert_message']);
        }
    }
    
    
    

	public static function Print_LabelBox($data)
	{
	   /*
       $data = array(
            'type' => '',
            'content' => '',
            'color' => ''
       );
       */
       if (isset($data['type']))
       {
            switch ($data['type'])
            {
                case 'error':
                    $data['color'] = 'red';
                    break;
                    
                case 'info':
                    $data['color'] = 'blue';
                    break;
                    
                case 'ok':
                    $data['color'] = 'green';
                    break;
                    
                case 'warning':
                    $data['color'] = 'yellow';
                    break;
            }
       }
        ?>
        <div class="ui <?php echo $data['color']; ?> circular label"><?php echo $data['content']; ?></div>
        <?php
    }



	public static function Print_MessageBox($data)
	{
	   /*
       $data = array(
            'type' => '',
            'size' => '',
            'icon' => '',
            'header' => '',
            'content' => '',
            'color' => '',
            'button' => array(
                'url' => '',
                'txt' => '',
                'target' => 1
                )
       );
       */
       
       if (isset($data['type']))
       {
            switch ($data['type'])
            {
                case 'error':
                    $data['color'] = 'red';
                    $data['icon'] = 'exclamation triangle';
                    break;
                    
                case 'info':
                    $data['color'] = 'blue';
                    $data['icon'] = 'exclamation';
                    break;
                    
                case 'ok':
                    $data['color'] = 'green';
                    $data['icon'] = 'check square outline';
                    break;
                    
                case 'warning':
                    $data['color'] = 'yellow';
                    $data['icon'] = 'exclamation circle';
                    break;
            }
       }
       
	   if (!isset($data['size'])) $data['size'] = 'large';
	   if (isset($data['icon'])) 
       {
            $data['icon_class'] = 'icon';
            $data['icon_html'] = '<i class="'.$data['icon'].' icon"></i>';
       }
       else $data['icon_class'] = $data['icon_html'] = '';
       
       if (isset($data['button']) && !isset($data['button']['target'])) $data['button']['target'] = 1;

	   ?>
            <div class="ui <?php echo $data['color']; ?> <?php echo $data['icon_class']; ?> <?php echo $data['size']; ?> message">
                <?php echo $data['icon_html']; ?>
                <div class="content">
                  <?php if (isset($data['header'])) echo '<div class="header">'.$data['header'].'</div>'; ?>
                  <?php if (isset($data['button'])) { ?> <a class="mini ui <?php echo $data['color']; ?> button right floated" <?php if ($data['button']['target'] == 1) echo 'target="_blank"'; ?> href="<?php echo $data['button']['url']; ?>"><?php echo $data['button']['txt']; ?></a> <?php } ?>
                  <?php echo $data['content']; ?>
                </div>
            </div>
        <?php
    }
    
    

    
    
    
    
    public static function Print_HelpBlock()
    {
        ?>
            <div class="ui icon green message">
                <i class="medkit icon"></i>
                <div class="content">
                    <h2 class="ui header sg_center">Something happened with your website? We can help.</h2>
                    <p>******Over 450.000 clients use our services daily. Based on extensive experience focused in Information Security we can assure you the best service and the best prices in the Globe. Before your business is a victim by intruders and think of your Business reputation damage. Consider to outsource your overall website security to professionals. Even if you have been a victim, we can take care of all the needed processes to bring back everything in place such as Google's reputation loss recovery, email delay processes, etc. Our Research & Development experts scan daily thousands of attacks and update all firewalls and customers on the fly.</p>
                    <p class="sg_center">
                        <a class="medium positive ui button" href="<?php echo self::$LINKS['contact_support']; ?>" target="_blank">Get Professional Help</a>
                    </p>
                </div>
            </div>
        <?php
    }


    
    
    
	public static function SendEmail($email, $result, $subject = '')
	{
		$to  = $email; // note the comma
		
		// subject
		if ($subject == '') $subject = 'AntiVirus Report ['.date("Y-m-d H:i:s").']';
		
		// message
        $body_message = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>SiteGuarding - Professional Web Security Services!</title>
</head>
<body bgcolor="#ECECEC" style="background-color:#ECECEC;">
<table cellpadding="0" cellspacing="0" width="100%" align="center" border="0" bgcolor="#ECECEC" style="background-color: #fff;">
  <tr>
    <td width="100%" align="center" bgcolor="#ECECEC" style="padding: 5px 30px 20px 30px;">
      <table width="750" border="0" align="center" cellpadding="0" cellspacing="0" bgcolor="#fff" style="background-color: #fff;">
        <tr>
          <td width="750" bgcolor="#fff"><table width="750" border="0" cellspacing="0" cellpadding="0" bgcolor="#fff" style="background-color: #fff;">
            <tr>
              <td width="350" height="60" bgcolor="#fff" style="padding: 5px; background-color: #fff;"><a href="http://www.siteguarding.com/" target="_blank"><img src="data:image/png;base64,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" alt="SiteGuarding - Protect your website from unathorized access, malware and other threat" height="60" border="0" style="display:block" /></a></td>
              <td width="400" height="60" align="right" bgcolor="#fff" style="background-color: #fff;">
              <table border="0" cellspacing="0" cellpadding="0" bgcolor="#fff" style="background-color: #fff;">
                <tr>
                  <td style="font-family:Arial, Helvetica, sans-serif; font-size:11px;"><a href="http://www.siteguarding.com/en/login" target="_blank" style="color:#656565; text-decoration: none;">Login</a></td>
                  <td width="15"></td>
                  <td width="1" bgcolor="#656565"></td>
                  <td width="15"></td>
                  <td style="font-family:Arial, Helvetica, sans-serif; font-size:11px;"><a href="http://www.siteguarding.com/en/prices" target="_blank" style="color:#656565; text-decoration: none;">Services</a></td>
                  <td width="15"></td>
                  <td width="1" bgcolor="#656565"></td>
                  <td width="15"></td>
                  <td style="font-family:Arial, Helvetica, sans-serif; font-size:11px;"><a href="http://www.siteguarding.com/en/what-to-do-if-your-website-has-been-hacked" target="_blank" style="color:#656565; text-decoration: none;">Security Tips</a></td>            
                  <td width="15"></td>
                  <td width="1" bgcolor="#656565"></td>
                  <td width="15"></td>
                  <td style="font-family:Arial, Helvetica, sans-serif;  font-size:11px;"><a href="http://www.siteguarding.com/en/contacts" target="_blank" style="color:#656565; text-decoration: none;">Contacts</a></td>
                  <td width="30"></td>
                </tr>
              </table>
              </td>
            </tr>
          </table></td>
        </tr>

        <tr>
          <td width="750" height="2" bgcolor="#D9D9D9"></td>
        </tr>
        <tr>
          <td width="750" bgcolor="#fff" ><table width="750" border="0" cellspacing="0" cellpadding="0" bgcolor="#fff" style="background-color:#fff;">
            <tr>
              <td width="750" height="30"></td>
            </tr>
            <tr>
              <td width="750">
                <table width="750" border="0" cellspacing="0" cellpadding="0" bgcolor="#fff" style="background-color:#fff;">
                <tr>
                  <td width="30"></td>
                  <td width="690" bgcolor="#fff" align="left" style="background-color:#fff; font-family:Arial, Helvetica, sans-serif; color:#000000; font-size:12px;">
                    {MESSAGE_CONTENT}
                    <br>
                    <b>URGENT SUPPORT</b><br>
                    Not sure in the report details? Need urgent help and support. Please contact us <a href="https://www.siteguarding.com/en/contacts" target="_blank">https://www.siteguarding.com/en/contacts</a>
                  </td>
                  <td width="30"></td>
                </tr>
              </table></td>
            </tr>
            <tr>
              <td width="750" height="15"></td>
            </tr>
            <tr>
              <td width="750" height="15"></td>
            </tr>
            <tr>
              <td width="750"><table width="750" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td width="30"></td>
                  <td width="690" align="left" style="font-family:Arial, Helvetica, sans-serif; color:#000000; font-size:12px;"><strong>How can we help?</strong><br />
                    If you have any questions please dont hesitate to contact us. Our support team will be happy to answer your questions 24 hours a day, 7 days a week. You can contact us at <a href="mailto:support@siteguarding.com" style="color:#2C8D2C;"><strong>support@siteguarding.com</strong></a>.<br />
                    <br />
                    Thanks again for choosing SiteGuarding as your security partner!<br />
                    <br />
                    <span style="color:#2C8D2C;"><strong>SiteGuarding Team</strong></span><br />
                    <span style="font-family:Arial, Helvetica, sans-serif; color:#000; font-size:11px;"><strong>We will help you to protect your website from unauthorized access, malware and other threats.</strong></span></td>
                  <td width="30"></td>
                </tr>
              </table></td>
            </tr>
            <tr>
              <td width="750" height="30"></td>
            </tr>
          </table></td>
        </tr>
        <tr>
          <td width="750" height="2" bgcolor="#D9D9D9"></td>
        </tr>
      </table>
      <table width="750" border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td width="750" height="10"></td>
        </tr>
        <tr>
          <td width="750" align="center"><table border="0" cellspacing="0" cellpadding="0">
            <tr>
              <td style="font-family:Arial, Helvetica, sans-serif; color:#ffffff; font-size:10px;"><a href="http://www.siteguarding.com/en/website-daily-scanning-and-analysis" target="_blank" style="color:#656565; text-decoration: none;">Website Daily Scanning</a></td>
              <td width="15"></td>
              <td width="1" bgcolor="#656565"></td>
              <td width="15"></td>
              <td style="font-family:Arial, Helvetica, sans-serif; color:#ffffff; font-size:10px;"><a href="http://www.siteguarding.com/en/malware-backdoor-removal" target="_blank" style="color:#656565; text-decoration: none;">Malware & Backdoor Removal</a></td>
              <td width="15"></td>
              <td width="1" bgcolor="#656565"></td>
              <td width="15"></td>
              <td style="font-family:Arial, Helvetica, sans-serif; color:#ffffff; font-size:10px;"><a href="http://www.siteguarding.com/en/update-scripts-on-your-website" target="_blank" style="color:#656565; text-decoration: none;">Security Analyze & Update</a></td>
              <td width="15"></td>
              <td width="1" bgcolor="#656565"></td>
              <td width="15"></td>
              <td style="font-family:Arial, Helvetica, sans-serif; color:#ffffff; font-size:10px;"><a href="http://www.siteguarding.com/en/website-development-and-promotion" target="_blank" style="color:#656565; text-decoration: none;">Website Development</a></td>
            </tr>
          </table></td>
        </tr>

        <tr>
          <td width="750" height="10"></td>
        </tr>
        <tr>
          <td width="750" align="center" style="font-family: Arial,Helvetica,sans-serif; font-size: 10px; color: #656565;">Add <a href="mailto:support@siteguarding.com" style="color:#656565">support@siteguarding.com</a> to the trusted senders list.</td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
';

		$body_message = str_replace("{MESSAGE_CONTENT}", $result, $body_message);
		
		// To send HTML mail, the Content-type header must be set
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
		
		// Additional headers
		$headers .= 'From: '. $to . "\r\n";
		
		// Mail it
		mail($to, $subject, $body_message, $headers);
	}
    
    
    public static function Wait_CSS_Loader()
    {
        ?>
        
		<div id="loader" style="min-height:600px;position: relative"><img style="position: absolute;top: 0; left: 0; bottom: 0; right: 0; margin:auto;" src="<?php  echo plugins_url('/images/ajax_loader.svg', dirname(__FILE__)); ?>"></div>
		            <script>
            jQuery(document).ready(function(){
                jQuery('.ui.accordion').accordion();
                jQuery('.ui.checkbox').checkbox();
                jQuery('#main').css('opacity','0');
                jQuery('#main').css('display','block');
                jQuery('#loader').css('display','none');
				fromBlur();
            });
			
			var i = 0;
			
			function fromBlur() {
				running = true;
					if (running){
					
						jQuery('#main').css("opacity", i);
						
						i = i + 0.02;

					if(i > 1) {
						running = false;
						i = 0;
					}
					if(running) setTimeout("fromBlur()", 5);

				}
			}
            </script>
            
            <?php
    }
	
	public static function checkActions() {
		
		self::Init();

		if (!isset($_GET['action']) || !isset($_GET['nonce']) || (!wp_verify_nonce( $_GET['nonce'], 'simple_bkp' ))) return false;
		$file = _SITEGUARDING_BKUP_FOLDER . $_GET['filename'];

		if (!is_file($file)) return false;
		switch ($_GET['action']) {
			case 'download':
				header("Content-Type: application/octet-stream");
				header('Content-Transfer-Encoding: binary');
				header("Content-length: ".filesize($file));
				header("Cache-Control: no-cache");
				header("Pragma: no-cache");
				header("Content-disposition: attachment; filename=\"".basename($file)."\";");
				$handler = fopen($file,"rb");
				while(!feof($handler)){
					print(fread($handler, 1024*8));
					@ob_flush();
					@flush();
				}
				fclose($handler);
				break;
			case 'delete':
				unlink($file);
				break;
			case 'restore':
				if (stripos($file, 'backup_files') !== false) {
					if (!defined('ABSPATH') || strlen(ABSPATH) < 8) 
					{
						$scan_path = dirname(dirname(dirname(dirname(__FILE__))));
					}
					else $scan_path = ABSPATH;	
					if (pathinfo($file, PATHINFO_EXTENSION) == 'zip') {
						if(class_exists('ZipArchive')){
							$zip = new ZipArchive();
							if($zip->open($file)){
								$zip->extractTo($scan_path);
								$zip->close();
							}		
						}
						wp_redirect(admin_url() . 'admin.php?page=plgbkup_Backup&success=Files');
						exit;
					} else if (pathinfo($file, PATHINFO_EXTENSION) == 'tar') {
						$result = self::execute("tar -xvf \"".$file."\" -C \"/\"");
						if ($result) {
							wp_redirect(admin_url() . 'admin.php?page=plgbkup_Backup&success=Files');
							exit;
						} else {
							wp_redirect(admin_url() . 'admin.php?page=plgbkup_Backup&fault=Files');
							exit;
						}
					}
				}
				if (stripos($file, 'backup_sql') !== false) {
					set_time_limit(0);
					ignore_user_abort(true);
					
					$dbHost = DB_HOST;
					$dbUser = DB_USER;
					$dbPass = DB_PASSWORD;
					$dbName = DB_NAME;

					$progressFilename = $file . '_filepointer'; 
					$errorFilename = $file . '_error';
					
					$link = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);

					$fp = fopen($file, 'r');
					if( file_exists($errorFilename) ){
						die('<pre> previous error: '.file_get_contents($errorFilename));
					}
					if (!file_exists($progressFilename)) {
						
						mysqli_query($link, 'SET foreign_key_checks = 0');
						if ($result = mysqli_query($link, "SHOW TABLES"))
						{
							while($row = mysqli_fetch_array($result, MYSQLI_NUM))
							{
								mysqli_query($link, 'DROP TABLE IF EXISTS '.$row[0]);
							}
						}
						
					}

					$filePosition = 0;
					if( file_exists($progressFilename) ){
						$filePosition = file_get_contents($progressFilename);
						fseek($fp, $filePosition);
					}

					$queryCount = 0;
					$query = '';
					while ($line = fgets($fp, 1024000)) {
						if (substr($line, 0, 2) == '--' OR trim($line) == '' ){
							continue;
						}

						$query .= $line;
						if( substr(trim($query), -1) == ';' ){
							if( !mysqli_query($link, $query) ){
								$error = 'Error performing query \'<strong>' . $query . '\': ' . 
								file_put_contents($errorFilename, $error."\n");

							}
							$query = '';
							file_put_contents($progressFilename, ftell($fp));
							$queryCount++;
						}
					}

					if( feof($fp) ){
						unlink($progressFilename);
						wp_redirect(admin_url() . 'admin.php?page=plgbkup_Backup&success=Database');
						exit;
					}else{
						wp_redirect(admin_url() . 'admin.php?page=plgbkup_Backup&in_progress='.(round(ftell($fp) / filesize($file), 2) * 100 ). '&c='.$queryCount);
						exit;
					}

				}
				
		}
			
	}
	
	
	public static function execute($code){
		$output = "";
		$code = $code." 2>&1";

		if(is_callable('system') && function_exists('system')){
			ob_start();
			@system($code);
			$output = ob_get_contents();
			ob_end_clean();
			if(!empty($output)) return $output;
		}
		elseif(is_callable('shell_exec') && function_exists('shell_exec')){
			$output = @shell_exec($code);
			if(!empty($output)) return $output;
		}
		elseif(is_callable('exec') && function_exists('exec')){
			@exec($code,$res);
			if(!empty($res)) foreach($res as $line) $output .= $line;
			if(!empty($output)) return $output;
		}
		elseif(is_callable('passthru') && function_exists('passthru')){
			ob_start();
			@passthru($code);
			$output = ob_get_contents();
			ob_end_clean();
			if(!empty($output)) return $output;
		}
		elseif(is_callable('proc_open') && function_exists('proc_open')){
			$desc = array(
				0 => array("pipe", "r"),
				1 => array("pipe", "w"),
				2 => array("pipe", "w"));
			$proc = @proc_open($code, $desc, $pipes, getcwd(), array());
			if(is_resource($proc)){
				while($res = fgets($pipes[1])){
					if(!empty($res)) $output .= $res;
				}
				while($res = fgets($pipes[2])){
					if(!empty($res)) $output .= $res;
				}
			}
			@proc_close($proc);
			if(!empty($output)) return $output;
		}
		elseif(is_callable('popen') && function_exists('popen')){
			$res = @popen($code, 'r');
			if($res){
				while(!feof($res)){
					$output .= fread($res, 2096);
				}
				pclose($res);
			}
			if(!empty($output)) return $output;
		}
		return "";
	}
	
}



?>