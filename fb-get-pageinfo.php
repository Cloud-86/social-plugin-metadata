<?php
/**
 * Plugin Name: Facebook page info
 * Description: Display facebook related page information
 * Version:     1.0.0
 * Author:      ole1986
 * License: MIT
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Text Domain: fb-get-pageinfo
 */

defined('ABSPATH') or die('No script kiddies please!');

class Ole1986_FacebokPageInfo extends WP_Widget
{
    static $FB_CLOUD86_GATEWAY = "https://test.cloud86.de/facebook-gateway/";
    static $WP_OPTION_PAGES = 'fb_get_page_info';

    private $title = '';
    private $fb_page;
    private $fb_show_page;

    /**
     * constructor overload of the WP_Widget class to initialize the media widget
     */
    public function __construct()
    {
        parent::__construct('fb-get-pageinfo', __('Facebook page info Widget', 'fb-get-pageinfo'), ['description' => __('Used to output several information gathered from a facebook page', 'fb-get-pageinfo')]);

        //add_action('wp_ajax_fb_get_pages', [$this, 'fb_get_pages']);
        add_action('wp_ajax_fb_save_pages', [$this, 'fb_save_pages']);
    }

    /**
     * Display the widget onto the frontend
     */
    public function widget($args, $instance)
    {
        $this->parseSettings($instance);

        $pages = get_option(self::$WP_OPTION_PAGES, []);

        $currentPage = array_pop(
            array_filter(
                $pages,
                function ($v) {
                    return $v['id'] == $this->fb_page;
                }
            )
        );

        if ($currentPage != null) {
            $result = $this->fbGraphRequest($currentPage['id'] . '/?fields=hours&access_token=' . $currentPage['access_token']);
        }

        // before and after widget arguments are defined by themes
        echo $args['before_widget'];
        echo $args['before_title'] . $this->title . $args['after_title'];
        ?>

        <style>
            .fb-pageinfo-hours { display: flex; flex-direction: column; }
            .fb-pageinfo-days { display: flex; justify-content: space-between; }
        </style>
        <div id="fb-pageinfo-widget">
            <?php if (!empty($currentPage) && !empty($result['hours'])) : ?>
                <?php if ($this->fb_show_page) : ?>
                    <h4 class="fb-pageinfo-title"><?php echo $currentPage['name']; ?></h4>
                <?php endif; ?>
                <div class="fb-pageinfo-hours">
                    <?php $this->displayHoursFromPage($result); ?>
                </div>
            <?php elseif (empty($result['error'])) : ?>
                <div style="text-align: center"><?php _e('Currently there are no business hours given in Facebook', 'fb-get-pageinfo') ?></div>
            <?php else: ?>
                <div><?php _e('Facebook page info Widget', 'fb-get-pageinfo') ?></div>
                <?php if (!empty($result['error'])) : ?>
                    <div><small><?php echo $result['error']['message'] ?></small></div>
                <?php endif; ?>
                
            <?php endif; ?>
        </div>

        <?php
        echo $args['after_widget'];
    }

    /**
     * Parse the hours taken from facebook graph api and output in proper HTML format
     * 
     * @param array $page the page properties received from facebook api
     */
    public function displayHoursFromPage($page)
    {
        if (empty($page['hours'])) {
            return '';
        }
        
        $result = [];

        array_walk(
            $page['hours'],
            function ($item, $k) use (&$result) {
                if (preg_match('/(\w{3})_(\d+)_(open|close)/', $k, $m)) {
                    if (empty($result[$m[1]])) {
                        $result[$m[1]] = [];
                    }

                    if (empty($result[$m[1]][$m[2]])) {
                        $result[$m[1]][$m[2]] = [
                        'open' => '',
                        'close' => ''
                        ];
                    }
                    $result[$m[1]][$m[2]][$m[3]] = $item;
                }
            }
        );

        $mapDayNames = [
            'mon' => __('Monday', 'fb-get-pageinfo'),
            'tue' => __('Tuesday', 'fb-get-pageinfo'),
            'wed' => __('Wednesday', 'fb-get-pageinfo'),
            'thu' => __('Thursday', 'fb-get-pageinfo'),
            'fri' => __('Friday', 'fb-get-pageinfo'),
            'sat' => __('Saturday', 'fb-get-pageinfo'),
            'sun' => __('Sunday', 'fb-get-pageinfo'),
        ];

        foreach ($result as $k => $v) {
            echo '<div class="fb-pageinfo-days">';
            echo "<div>" . $mapDayNames[$k] . "</div>";
            echo "<div class='fb-pageinfo-hours-times'>";
            foreach ($v as $value) {
                echo "<div>".$value['open']." - ".$value['close']."</div>";
            }
            echo '</div>';
            echo '</div>';
        }
    }

    /**
     * Show the widget form in admin area to manage the widget settings
     * 
     * @param array $instance the settings saved as array
     */
    public function form($instance)
    {
        $this->parseSettings($instance);

        $pages = get_option(self::$WP_OPTION_PAGES, []);

        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'fb-get-pageinfo');?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $this->title ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('fb_page'); ?>"><?php _e('Facebook Page:', 'fb-get-pageinfo');?></label>
            <select name="<?php echo $this->get_field_name('fb_page'); ?>" id="fbPages">
                <option value="">[select page]</option>
                <?php
                foreach ($pages as $value) {
                    echo '<option value='.$value['id'].' '. (($this->fb_page == $value['id']) ? 'selected':'') .'>'.$value['name'].'</option>';
                }
                ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('fb_show_page'); ?>"><?php _e('Show page name:', 'fb-get-pageinfo');?></label>
            <input type="checkbox" id="<?php echo $this->get_field_id('fb_show_page'); ?>" name="<?php echo $this->get_field_name('fb_show_page'); ?>" type="text" <?php echo ($this->fb_show_page ? 'checked' : '') ?> value="1" />
        </p>
        <?php
    }

    /**
     * Parse the widget settings into its current class object
     * 
     * @param array $instance the widget settings
     */
    private function parseSettings($instance)
    {
        $this->title = isset($instance['title']) ? esc_attr($instance['title']) : "";
        $this->fb_page = isset($instance['fb_page']) ? esc_attr($instance['fb_page']) : "";
        $this->fb_show_page = !empty($instance['fb_show_page']) ?true : false;
    }

    /**
     * initialize the widget class and text-domain part
     */
    public static function load()
    {
        load_plugin_textdomain('fb-get-pageinfo', false, dirname(plugin_basename(__FILE__)) . '/lang/');
        // register self as a widget
        register_widget(get_class());
    }

    private function fbGraphRequest($url)
    {
        $path = 'https://graph.facebook.com/';

        $curl_facebook1 = curl_init(); // start curl
        curl_setopt($curl_facebook1, CURLOPT_URL, $path . $url); // set the url variable to curl
        curl_setopt($curl_facebook1, CURLOPT_RETURNTRANSFER, true); // return output as string

        $output = curl_exec($curl_facebook1); // execute curl call
        curl_close($curl_facebook1); // close curl
        $decode_output = json_decode($output, true); // decode the response (without true this will crash)

        return $decode_output;
    }

    /**
     * The ajax call being used to save the pages received by the fb-gateway
     */
    public function fb_save_pages()
    {
        $ok = $this->savePages($_POST['data']);

        header('Content-Type: application/json');
        echo json_encode($ok);
        wp_die();
    }

    /**
     * Save the pages as wordpress option
     * 
     * @param array $new_value all known pages selected by the client
     */
    private function savePages($new_value)
    {
        if (empty($new_value)) {
            delete_option(self::$WP_OPTION_PAGES);
            return false;
        }

        if (get_option(self::$WP_OPTION_PAGES) !== false) {
            // The option already exists, so update it.
            update_option(self::$WP_OPTION_PAGES, $new_value);
        } else {
            add_option(self::$WP_OPTION_PAGES, $new_value, null, 'no');
        }

        return true;
    }

    /**
     * Populate the Settings menu entry
     */
    public static function settings_page()
    {
        add_options_page(__('Facebook page info', 'fb-get-pageinfo'), __('Facebook page info', 'fb-get-pageinfo'), 'manage_options', 'fb-get-pageinfo-plugin', ['Ole1986_FacebokPageInfo', 'settings_page_content']);
    }
    
    /**
     * Populate the settings content used to gather the facebook pages from fb-gateway
     */
    public static function settings_page_content()
    {
        $pages = get_option(self::$WP_OPTION_PAGES, []);
        ?>
        <script>
            var fbSavePages = function(data) {
                var alert = jQuery('#fb-pageinfo-sync');
                var frame = jQuery('#fb-gateway-frame');
                frame.hide();

                alert.removeClass('error').removeClass('updated');
                alert.find('p').text('Syncing...');

                jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', { action: 'fb_save_pages', data })
                    .done(function(response){
                        if (!response) {
                            alert.addClass('error');
                            alert.find('p').html('Something went wrong. Please choose at least one page after login');    
                            frame.show();
                            return;
                        }
                        alert.addClass('updated');
                        alert.find('p').html('Successfully synchronized ' + data.length +' pages. You can now configure <a href="widgets.php">the widget</a>');
                    }).catch(function(e) {
                        alert.addClass('error');
                        alert.find('p').text('We encountered an error. Please try again later...');
                        frame.show();
                    });
            }

            jQuery(function() {
                window.addEventListener("message", (event) => {
                console.log(event);
                // received postMessage from iframe
                if (event.origin.match(/cloud86\.de/)) {
                    fbSavePages(event.data);
                }
                
            }, false);
            });
            
        </script>
        <h2><?php _e('Facebook page info', 'fb-get-pageinfo') ?></h2>
        <div id="fb-pageinfo-sync" class="notice">
            <p><?php _e('Please follow the instruction below to syncronize your facebook pages', 'fb-get-pageinfo') ?></p>
        </div>
        <div id="fb-gateway-frame" style="margin: 1em">
            <iframe src="<?php echo self::$FB_CLOUD86_GATEWAY ?>" width="400px" height="250px">
            </iframe>
        </div>
        <?php
    }
}

add_action('widgets_init', ['Ole1986_FacebokPageInfo', 'load']);
add_action('admin_menu', ['Ole1986_FacebokPageInfo', 'settings_page']);

?>
