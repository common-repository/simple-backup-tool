<?php
defined('_SITEGUARDING_BKUP') or die;

class FUNC_BKUP_dashboard
{
    public static function PageHTML()  
    {
        wp_enqueue_style( 'plgbkup_LoadStyle_UI' );
        wp_enqueue_script( 'plgbkup_LoadJS_UI', '', array(), false, true );
        
        $isFull = FUNC_BKUP_general::Is_FULL();
        
        $backups_local = FUNC_BKUP_general::Get_Backups_Local();
        $backups_remote = array();
        
    
        ?>

        <?php
            FUNC_BKUP_general::Wait_CSS_Loader();
        ?>

        <div id="main" class="ui main container" style="float: left;margin-top:20px;display:none">
            <h2 class="ui dividing header">Simple Backup Dashboard</h2>
            <?php 
			       // FUNC_BKUP_general::Init();

					//FUNC_BKUP_general::BackupSQL();
				if (isset($_GET['success'])) {
					$msg_data = array(
						'type' => 'ok',
						'content' => $_GET['success'] . ' successfully restored!',
					);
					FUNC_BKUP_general::Print_MessageBox($msg_data);
				} else if (isset($_GET['in_progress'])) {
					$msg_data = array(
						'type' => 'info',
						'content' => $_GET['in_progress'].' % completed.<br>' . $_GET['c'] . ' queries processed! Please reload or wait for automatic browser refresh!',
					);
					FUNC_BKUP_general::Print_MessageBox($msg_data);
				}	
			?>
            <div class="ui grid">
                <div class="six wide column center aligned">
                
                        <i class="huge umbrella icon green"></i>
                        <p class="green">Backup plugin is active</p>
                        <?php
                        if ($isFull) 
                        {
                            ?>
                            <div class="ui green horizontal label">secured server & local storage</div>
                            <?php
                        } else {
                            ?>
                            <div class="ui yellow horizontal label">local storage only</div>
                            <?php
                        }
                        ?>
                        

                
                </div>
                
                <div class="ten wide column">
						<p>Backup of the site database and its files is an important procedure, the regularity of which depends on the speed of resource recovery in the event of a force majeure situation and the amount of possible content loss. No website is not insured from technical problems on hosting, hacker hacking, DDoS attacks, problems with incompatibility of plug-ins, careless edits of the code by the owner, etc. Not insured until he has no backups.</p>
                    <?php
                    if ($isFull) 
                    {
                        ?>
                        <p><a href="<?php echo FUNC_BKUP_general::$LINKS['contact_support']; ?>" class="ui button positive medium">Contact Support</a></p>
                        <?php
                    } else {
                        ?>
                        <p><a href="<?php echo FUNC_BKUP_general::$LINKS['get_backup_service']; ?>" class="ui button positive medium">Get Full Service</a></p>
                        <?php
                    }
                    ?>
                </div>
            
            </div>
            
            <?php
                $data = array(
                    array(
                        'active' => 'active',
                        'icon' => 'check green',
                        'title' => 'Files',
                        'description' => 'backup all files and folders',
                    ),
                    array(
                        'active' => 'active',
                        'icon' => 'check green',
                        'title' => 'Database',
                        'description' => 'backup website database',
                    ),
                    array(
                        'active' => 'active',
                        'icon' => 'check green',
                        'title' => 'Schedule',
                        'description' => 'run scheduled backups',
                    ),
                    array(
                        'active' => 'active',
                        'icon' => 'check green',
                        'title' => 'Secured Server',
                        'description' => 'store backups on secured server',
                    ),
                    array(
                        'active' => 'active',
                        'icon' => 'check green',
                        'title' => 'Fix Website',
                        'description' => 'help to restore and fix website',
                    ),
                );
                
                if ($isFull === false)
                {
                    $data[3]['icon'] = 'lock';
                    $data[3]['active'] = 'disabled';
                    $data[4]['icon'] = 'lock';
                    $data[4]['active'] = 'disabled';
                }
                
                FUNC_BKUP_general::PrintSteps($data);
            ?>
            
            
            <?php FUNC_BKUP_general::BannerArea(); ?>
            
            <?php FUNC_BKUP_general::QuickLinks(); ?>
            
            
                    <div class="ui raised segment">
                    
                        <h3 class="ui dividing header"><i class="cloud icon green"></i>Available backups on secured remote server</h3>
                        
                        <?php
                        if (count($backups_remote))
                        {
                        ?>
                            <div class="ui list">
                        <?php
                                foreach ($backups_remote as $row)
                                {
                                    ?>
                                      <div class="item">
                                        <i class="file alternate outline icon"></i>
                                        <div class="content">
                                          <a href="<?php echo $row['report_link']; ?>" target="_blank" class="header">Antivirus report <?php echo $row['date']; ?></a>
                                        </div>
                                      </div>
                                    
                                    <?php
                                }
                                ?>
                                    <p class="sg_center">
                                        <br><b>Need help from professional web security expert?</b><br><br>
                                        <a href="<?php echo FUNC_BKUP_general::$LINKS['clean_website']; ?>" class="medium negative ui button" target="_blank" href="<?php echo FUNC_WAP2_general::$LINKS['clean_website']; ?>">Clean My Website</a>
                                    </p>
                                    <?php
                                    if (FUNC_BKUP_general::IsPRO()) {
                                    ?>
                                        <p><i class="user md green icon"></i>Premium customers can request free cleaning. Please contact <a href="<?php echo FUNC_BKUP_general::$LINKS['contact_support']; ?>" target="_blank">SiteGuarding.com support</a></p>
                                    <?php
                                    }
                            ?>

                            </div>
                        <?php
                        }
                        else {
                            if ($isFull)
                            {
                                $msg_data = array(
                                    'type' => 'info',
                                    'content' => 'We will automatically backup your website. No need any action from your side.',
                                );
                                FUNC_BKUP_general::Print_MessageBox($msg_data);
                            }
                            echo '<i class="frown outline icon red"></i>You don\'t have any backups on secured server. To learn more about secred backup server and connect it to your website, please <a target="_blank" href="'.FUNC_BKUP_general::$LINKS['get_backup_service'].'">click here</a>.';
                        }
                        ?>
                        
                        <h3 class="ui dividing header"><i class="server icon blue"></i>Available backups on local server</h3>
                        <?php
                        if (count($backups_local))
                        {
                        ?>
                            <table class="ui celled table small">
                              <thead>
                                <tr><th>Filename</th>
                                <th>Size (Mb)</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Actions</th>
                              </tr></thead>
                        <?php
                                foreach ($backups_local as $row)
                                {
                                    ?>
                                        <tr>
                                          <td><?php echo $row['filename']; ?></td>
                                          <td><?php echo $row['size']; ?> Mb</td>
                                          <td><?php echo $row['date']; ?></td>
                                          <td><?php echo $row['type']; ?></td>
                                          <td>
                                            <a href="<?php echo admin_url( 'admin.php?page=plgbkup_Backup&action=download&nonce=' . wp_create_nonce('simple_bkp') . '&filename='. $row['filename'] );?>" title="Download Backup"><i class="download icon blue"></i>Download</a>&nbsp;&nbsp;&nbsp;
                                            <a href="<?php echo admin_url( 'admin.php?page=plgbkup_Backup&action=restore&nonce=' . wp_create_nonce('simple_bkp') . '&filename='. $row['filename'] );?>" title="Restore" onclick="return window.confirm('Restore data from this file? Are you sure?');" ><i class="history icon green"></i>Restore</a>&nbsp;&nbsp;&nbsp;
                                            <a href="<?php echo admin_url( 'admin.php?page=plgbkup_Backup&action=delete&nonce=' . wp_create_nonce('simple_bkp') . '&filename='. $row['filename'] );?>" onclick="return window.confirm('Delete this file? Are you sure?');" title="Delete Backup"><i class="trash alternate icon red"></i>Delete</a>
                                          </td>
                                        </tr>
                                    <?php
                                }
                                ?>
                              </tbody>
                            </table>
                        <?php
                        }
                        else echo '<i class="frown outline icon red"></i>You don\'t have any available backups on this server.<br />';
                        ?>
                        
                        <br />
                        
                            <script>
                            function ShowLoader_Refresh(action_code)
                            {
                                jQuery(".ajax_buttons").hide();
                                jQuery(".ajax_loader_backup").show(); 
                                
                                jQuery.post(
                                    ajaxurl, 
                                    {
                                        'action': 'plgbkup_ajax_backup_'+action_code
                                    }, 
                                    function(response){
                                        document.location.href = 'admin.php?page=plgbkup_Backup';
                                    }
                                );  
                            }
                            </script>
                        
                        <p class="sg_center">
                            <a href="javascript:;" class="ajax_buttons ajax_button_backupall positive medium ui button" onclick="ShowLoader_Refresh('full');"><i class="desktop icon"></i>Backup Files & Database</a>
                            <a href="javascript:;" class="ajax_buttons ajax_button_backupfiles positive medium ui button" onclick="ShowLoader_Refresh('files');"><i class="hdd icon"></i>Backup Files only</a>
                            <a href="javascript:;" class="ajax_buttons ajax_button_backupsql positive medium ui button" onclick="ShowLoader_Refresh('sql');"><i class="database icon"></i>Backup Database only</a>
                            
                            <a href="javascript:;" class="ajax_loader_backup medium ui button" style="display: none;">
                                <img width="32" height="32" src="<?php echo plugins_url('images/ajax_loader.svg', dirname(__FILE__)); ?>" />
                            </a>
                        </p>
                      
                    </div>
                    
                    
            
            <?php FUNC_BKUP_general::Print_HelpBlock(); ?>
            
            
        </div>
        
        <?php
    }


}

?>