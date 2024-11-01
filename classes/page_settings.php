<?php
defined('_SITEGUARDING_BKUP') or die;

class FUNC_BKUP_settings
{
	public static $settings_list = array(
		'autobackup_sql',
		'autobackup_files',
		'autoremove',
	);
    
    public static function PageHTML()  
    {
        wp_enqueue_style( 'plgbkup_LoadStyle_UI' );
        wp_enqueue_script( 'plgbkup_LoadJS_UI', '', array(), false, true );
        
        $params = FUNC_BKUP_general::Get_SQL_Params(self::$settings_list);
        
        ?>

        <?php
            FUNC_BKUP_general::Wait_CSS_Loader();
        ?>
        
        <div id="main" class="ui main container" style="float: left;margin-top:20px;display:none;">
        
            <?php
            if (self::CheckActions()) $params = FUNC_BKUP_general::Get_SQL_Params(self::$settings_list);
            ?>
        
            <h2 class="ui dividing header">Settings</h2>
            


            <form method="post" id="plgwpagp_decision_page" action="admin.php?page=plgbkup_settings_page">
            
                <div class="ui form">
                
                  <div class="field">
                    <label>Location on the server to store backups</label>
                    <input type="text" value="<?php echo _SITEGUARDING_BKUP_FOLDER; ?>" disabled>
                  </div>
                
                  <div class="field">
                    <label>Autobackup (database)</label>
                    <select name="autobackup_sql" class="ui search dropdown">
                      <option value="0"<?php if ($params['autobackup_sql'] == 0) echo ' selected="selected"';?>>Autobackup for database is disabled</option>
                      <option value="1"<?php if ($params['autobackup_sql'] == 1) echo ' selected="selected"';?>>Everyday</option>
                      <option value="3"<?php if ($params['autobackup_sql'] == 3) echo ' selected="selected"';?>>Every 3 days</option>
                      <option value="7"<?php if ($params['autobackup_sql'] == 7) echo ' selected="selected"';?>>Once per week (recommended)</option>
                      <option value="30"<?php if ($params['autobackup_sql'] == 30) echo ' selected="selected"';?>>Once per month</option>
                    </select>
                  </div>
                  
                  <div class="field">
                    <label>Autobackup (files)</label>
                    <select name="autobackup_files" class="ui search dropdown">
                      <option value="0"<?php if ($params['autobackup_files'] == 0) echo ' selected="selected"';?>>Autobackup for website files is disabled</option>
                      <option value="1"<?php if ($params['autobackup_files'] == 1) echo ' selected="selected"';?>>Everyday</option>
                      <option value="3"<?php if ($params['autobackup_files'] == 3) echo ' selected="selected"';?>>Every 3 days</option>
                      <option value="7"<?php if ($params['autobackup_files'] == 7) echo ' selected="selected"';?>>Once per week</option>
                      <option value="30"<?php if ($params['autobackup_files'] == 30) echo ' selected="selected"';?>>Once per month (recommended)</option>
                    </select>
                  </div>
                  
                  <div class="field">
                    <label>Autoremove old backups</label>
                    <select name="autoremove" class="ui search dropdown">
                      <option value="0"<?php if ($params['autoremove'] == 0) echo ' selected="selected"';?>>Don't remove backup files</option>
                      <option value="10"<?php if ($params['autoremove'] == 10) echo ' selected="selected"';?>>Keeps 10 latest backup files</option>
                      <option value="20"<?php if ($params['autoremove'] == 20) echo ' selected="selected"';?>>Keeps 20 latest backup files (recommended)</option>
                      <option value="30"<?php if ($params['autoremove'] == 30) echo ' selected="selected"';?>>Keeps 30 latest backup files</option>
                      <option value="40"<?php if ($params['autoremove'] == 40) echo ' selected="selected"';?>>Keeps 40 latest backup files</option>
                      <option value="50"<?php if ($params['autoremove'] == 50) echo ' selected="selected"';?>>Keeps 50 latest backup files</option>
                    </select>
                  </div>
                  
                </div>


            
            <br />
            <button type="submit" class="medium positive ui button">Save Settings   </button>

    		<?php
    		wp_nonce_field( '19B47D2A16F7' );
    		?>
            <input type="hidden" name="action" value="save_settings"/>

            </form>
            
            <script>
            jQuery(document).ready(function(){
                
                jQuery('select.dropdown').dropdown();
                
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
					if(running) setTimeout("fromBlur()",5);

				}
			}

            </script>
        </div>
        <?php
    } 
    
    
    public static function CheckActions()
    {
        if (!isset($_REQUEST['action']) || !check_admin_referer( '19B47D2A16F7' )) return;
        
        $action = trim($_REQUEST['action']);
        
        if ($action == 'save_settings')
        {
            $data = array();
            
            foreach (self::$settings_list as $row)
            {
                $data[$row] = $_POST[$row];
            }
            
            FUNC_BKUP_general::Set_SQL_Params($data);
                
            $msg_data = array(
                'type' => 'ok',
                'size' => 'small',
                'content' => 'Settings saved.',
           );
           FUNC_BKUP_general::Print_MessageBox($msg_data);
           
           return true;
        }
    }
    
    
}

?>