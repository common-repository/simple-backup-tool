<?php
defined('_SITEGUARDING_BKUP') or die;

class FUNC_BKUP_extensions
{
    public static function PageHTML()  
    {
        wp_enqueue_style( 'plgbkup_LoadStyle_UI' );
        wp_enqueue_script( 'plgbkup_LoadJS_UI', '', array(), false, true );
        
        $items = self::GetExtensionsInfo();
        
        ?>

        <?php
            FUNC_BKUP_general::Wait_CSS_Loader();
        ?>
        
        
        <div id="main" class="ui main container" style="float: left;margin-top:20px;display:none">
            <h2 class="ui dividing header">Security extensions</h2>
            
            
            <div class="ui icon green message">
                <i class="thumbs up outline icon"></i>
                <div class="content">
                    <h2 class="ui header sg_center">Try our extensions</h2>
                    <p class="fnt-size-110">Our plugins can add a higher level of security to your WordPress website. You can try all of them absolutely free. <b>Premium customers</b> can get full version of all our plugins. Request your unlock code from <a href="https://www.siteguarding.com/en/contacts" target="_blank">SiteGuarding.com support</a></p>
                    <p class="sg_center">
                        <a class="medium positive ui button" href="<?php echo FUNC_BKUP_general::$LINKS['upgrade_to_premium']; ?>" target="_blank">Upgrade to Premium</a>
                    </p>
                </div>
            </div>
            
            
            <div class="ui three column grid">
            
            <?php
            foreach ($items as $item) {
            ?>
              <div class="column">
                <div class="ui segment full_h">
                    <h3 class="ui dividing header"><img src="<?php echo $item['logo']; ?>"/>
                        <?php echo $item['title']; ?></h3>
                    <div class="ui list">
                        <?php
                        foreach ($item['list'] as $row) {
                        ?>
                            <a class="item"><i class="right triangle icon"></i><div class="content"><div class="description"><?php echo $row; ?></div></div></a>
                        <?php
                        }
                        ?>
                    </div>
                    <p style="text-align: center;"><a class="ui medium positive button" href="<?php echo $item['link']; ?>" target="_blank">Learn more</a></p>
                </div>
              </div>
            <?php
            }
            ?>
              
            </div>
            
            
        </div>
        <?php
    } 
    
    
    
    public static function GetExtensionsInfo()
    {
        $a = array();
        
        $json_file = dirname(dirname(__FILE__)).DIRSEP.'tmp'.DIRSEP.'extensions.json';
        if (file_exists($json_file))
        {
            $a = FUNC_BKUP_general::ReadFile($json_file, true);
        }
        
        return $a;
    }
}

?>