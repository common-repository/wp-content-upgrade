<?php
/*
Plugin Name: WP Content Upgrade
Plugin URI: http://kimvinberg.dk
Description: Getting more newsletter signups can be a pain. With this plugin you can get many more. Write content as you normally do and offer a content upgrade.
Version: 1.0.7
Author: Kim Vinberg
Author URI: http://kimvinberg.dk
Text Domain: wp-content-upgrade
*/

/**
 *
 * Class base
 */

class WP_Content_Upgrade {

    /**
     *
     * Call actions / filters / etc. from constrict
     *
     */

    public function __construct() {


	       add_action( 'add_meta_boxes', array( $this, 'add_metabox'  ) );
           add_action( 'save_post',      array( $this, 'save_metabox' ), 10, 2 );

        if ( is_admin() ) {


    		add_action( 'admin_init', array( $this, 'init' ) );
                    /*
                     settings page
                    */
                    add_action( 'admin_menu', array( $this, 'content_upgrade_add_admin_menu') );
                    add_action( 'admin_init', array( $this, 'content_upgrade_settings_init') );
                    add_action( 'wpcuppro_admin_empty_lists_cache', array( $this, 'wpcuppro_renew_lists_cache' ) ); //used for mailchimp list cache

                    // Add the color picker css file
        }
        add_action( 'wp_ajax_wccup_submit', array( $this, 'wccup_submit_function' ) );  // Handle ajax call
        add_action( 'wp_ajax_nopriv_wccup_submit', array( $this, 'wccup_submit_function' ) );  // Handle ajax call

        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles_and_scripts' ));

        add_action( 'init',  array( $this, 'content_upgrade_post'), 0 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles_and_scripts' ) );
        add_shortcode( 'content_upgrade', array( $this, 'content_upgrade_shortcode_function' ) ); //[caption]My Caption[/caption]


    }// Add the Events Meta Boxes




    public function wccup_submit_function() {

        $options = get_option( 'content_upgrade_settings' );

        if(!$options['content_upgrade_text_field_0']) {
        wp_die("API key is missing"); // this is required to terminate immediately and return a proper responsere
        }
        require dirname( __FILE__ ) . '/includes/mailchimp/MailChimp.php';

        if(!$_REQUEST['callback']) { wp_die("Unknown error...");  }
        if(!$_POST['listid']) { wp_die("List id is missing");  }
        if(count($_POST['merge_vars']) < '1') {  wp_die("Missing fields"); }

        foreach($_POST['merge_vars'] AS $key => $data  ) {
            foreach($data AS $keyName => $string) {

                if($keyName == 'email') {
                    $email = sanitize_email($string);
                } else {
                    $merge_fields[$keyName] .= sanitize_text_field($string);
                }

            }
        }

        $MailChimp = new MailChimp($options['content_upgrade_text_field_0']);
        $list_id = sanitize_text_field($_POST['listid']);
        $subscriberHash = $MailChimp->subscriberHash($email);

        if(count($merge_fields) == 0) {
        $result = $MailChimp->put("lists/$list_id/members/$subscriberHash", [
        				'email_address' => $email,
        				'status'        => 'pending',
                        'update_existing'   => true,
                        'send_welcome'      => true,
        			]);
        } else {

        $result = $MailChimp->put("lists/$list_id/members/$subscriberHash", [
        				'email_address' => $email,
        				'status'        => 'pending',
                        'merge_fields' => (object)$merge_fields,
                        'update_existing'   => true,
                        'send_welcome'      => true,
        			]);
        }

       if(count($result['error'])) {
            wp_die("Something went wrong..");

        } else {
            print_R($result);
            wp_die("OK");

        }

    	wp_die(); // this is required to terminate immediately and return a proper response
    }

/**/
public function admin_enqueue_styles_and_scripts( $hook ) {


    if(is_admin()) {
        wp_enqueue_style( 'wp-color-picker' );
        // Include our custom jQuery file with WordPress Color Picker dependency
        wp_enqueue_script( 'content_upgrade_color_picker',  plugin_dir_url( __FILE__ ) . 'js/color-picker.js', array( 'wp-color-picker' ), false, true );
    }

}

/**
 * Initializes various stuff used in WP Admin
 *
 * - Registers settings
 */
public function init() {

    // listen for custom actions
    $this->wpcuppro_listen_for_actions();
}

	/**
	 * Listen for `_mc4wp_action` requests
	 */
	public function wpcuppro_listen_for_actions() {

		// listen for any action (if user is authorised)
		if( !is_admin() && !isset( $_REQUEST['_wpcuppro_action'] ) ) {
			return false;
		}

		$action = (string) $_REQUEST['_wpcuppro_action'];

		/**
		 * Allows you to hook into requests containing `_mc4wp_action` => action name.
		 *
		 * The dynamic portion of the hook name, `$action`, refers to the action name.
		 *
		 * By the time this hook is fired, the user is already authorized. After processing all the registered hooks,
		 * the request is redirected back to the referring URL.
		 *
		 * @since 3.0
		 */
		do_action( 'wpcuppro_admin_' . $action );

		// redirect back to where we came from
    //    print_R($_POST);
	//	$redirect_url = ! empty( $_POST['_redirect_to'] ) ? $_POST['_redirect_to'] : remove_query_arg( '_wpcuppro_action' );
	//	wp_redirect( $redirect_url );
		//exit;
	}

/**/
/**
 * Renew MailChimp lists cache
 */
public function wpcuppro_renew_lists_cache() {

    $options = get_option( 'content_upgrade_settings' );

    // try getting new lists to fill cache again
    include dirname( __FILE__ ) . '/includes/mailchimp/MailChimp.php';

    //use \DrewM\MailChimp\MailChimp;
    if($options['content_upgrade_text_field_0']) {
    $MailChimp = new MailChimp($options['content_upgrade_text_field_0']);
    $result = $MailChimp->get('lists');
//echo "<pre>";

$MailChimpList = array();
foreach($result['lists'] AS $list) {

$merge_fields = $MailChimp->get('/lists/'.$list['id'].'/merge-fields');
$fields = array();
//email
$fields[] = array(
    "merge_id" => "0",
    "tag" =>  "EMAIL",
    "name" => "email",
    "field_type" =>  "email",
    "required" => "1",
);

foreach($merge_fields['merge_fields'] AS $field) {


    $fields[] = array(
        "merge_id" => $field['merge_id'],
        "tag" =>  $field['tag'],
        "name" =>  $field['name'],
        "field_type" =>  $field['type'],
        "required" => $field['required'],
        "default_value" => $field['default_value'],
        "options" => json_encode($field['options']),
    );

 }

$MailChimpList[] = array(
 "id" => $list['id'],
 "web_id" => $list['web_id'],
 "name" => $list['name'],
 "member_count" => $list['stats']['member_count'],
 "merge_fields" => $fields
);

}

    update_option( 'wpcuppro_mailchimp_lists', json_encode($MailChimpList), false ); // forever
}

}

/* Pro options */
public function content_upgrade_add_admin_menu(  ) {

add_submenu_page(
           'edit.php?post_type=content-upgrade',
           __('Content Upgrade Pro Options', 'wp-content-upgrade'),
           __('Settings', 'wp-content-upgrade'),
           'manage_options',
           'wp_content_upgrade',
           array($this, 'content_upgrade_options_page'));
}


public function content_upgrade_settings_init(  ) {

	register_setting( 'pluginPage', 'content_upgrade_settings' );

/**
* Section 1
*/
	add_settings_section(
		'content_upgrade_pluginPage_section',
		__( 'MailChimp API settings', 'wp-content-upgrade' ),
		array( $this, 'content_upgrade_settings_section_callback' ),
		'pluginPage'
	);

	add_settings_field(
		'content_upgrade_text_field_0',
		__( 'API key', 'wp-content-upgrade' ),
		array( $this, 'content_upgrade_text_field_0_render'),
		'pluginPage',
		'content_upgrade_pluginPage_section'
	);


    add_settings_section(
        'content_upgrade_pluginPageSpace_1_section',
        '',
        array( $this, 'content_upgrade_space_section_callback' ),
        'pluginPage'
    );
/**
*Section 1 end
*/

}

public function content_upgrade_text_field_0_render(  ) {

	$options = get_option( 'content_upgrade_settings' );
	?>
	<input type='text' placeholder='<?php echo __( 'Your MailChimp API key', 'wp-content-upgrade' ); ?>' name='content_upgrade_settings[content_upgrade_text_field_0]' value='<?php echo $options['content_upgrade_text_field_0']; ?>'>
    <p class="help">The API key for connecting with your MailChimp account. <a target="_blank" href="https://admin.mailchimp.com/account/api">Get your API key here..</a></p>
    <span>This plugin is not developed by or affiliated with MailChimp in any way.</span>
    <?php

    //
    include dirname( __FILE__ ) . '/includes/mailchimp/MailChimp.php';

    $MailChimp = new MailChimp(@$options['content_upgrade_text_field_0']);
    $result = @$MailChimp->get('?fields=account_name');

    if(!$options['content_upgrade_text_field_0'] || $result["account_name"] == '' || !$result["account_name"]) {

    echo '<br><br><span style="color:#da0000;"><b>No API Key detected or the key is invalid.</b></span><br>';
    echo '<input type="submit" value="Save API key" class="button">';

} else {

    $wpcuppro_mailchimp_lists = get_option( 'wpcuppro_mailchimp_lists' );


    echo '<hr />';

    $lists = json_decode($wpcuppro_mailchimp_lists);
    include dirname( __FILE__ ) . '/includes/mailchimp/lists-overview.php';
}

}

public function content_upgrade_space_section_callback(  ) {
	?>
	<hr>
    <?php
}


public function content_upgrade_settings_section_callback(  ) {

    echo __( '<img style="width:64px;height:64px;vertical-align: middle;margin-right: 15px;" src="' . plugin_dir_url( __FILE__ ) . '/images/mc_freddie_color.png">', 'wp-content-upgrade' );
	echo __( '<b>MailChimp settings for Content Upgrade .</b>', 'wp-content-upgrade' );

}


public function content_upgrade_options_page(  ) {

	?>
	<form action='options.php' method='post'>

		<h2>WP Content Upgrade Pro</h2>

		<?php
		settings_fields( 'pluginPage' );
		do_settings_sections( 'pluginPage' );
		submit_button();
		?>

	</form>
	<?php

}

/* End pro options */

    	public function add_metabox() {

    		add_meta_box(
    			'content_upgrade_settings',
    			__( 'Content Upgrade Pro Settings', 'text_domain' ),
    			array( $this, 'render_metabox' ),
    			'content-upgrade',
    			'advanced',
    			'default'
    		);

    	}

    	public function render_metabox( $post ) {

    		// Add nonce for security and authentication.
    		wp_nonce_field( 'content_upgrade_nonce_action', 'content_upgrade_nonce' );

    		// Retrieve an existing value from the database.
    		$content_upgrade_share_shortcode = esc_html(get_post_meta( $post->ID, 'content_upgrade_share_shortcode', true ) );
    		$content_upgrade_inlinecontent_text = esc_html(get_post_meta( $post->ID, 'content_upgrade_inlinecontent_text', true ) );
    		$content_upgrade_div_text = esc_html(get_post_meta( $post->ID, 'content_upgrade_div_text', true ) );
    		$content_upgrade_thankyou_text = esc_html(get_post_meta( $post->ID, 'content_upgrade_thankyou_text', true ) );
    		$content_upgrade_div_background_color = esc_html(get_post_meta( $post->ID, 'content_upgrade_div_background_color', true ) );
    		$content_upgrade_div_boder_size = esc_html(get_post_meta( $post->ID, 'content_upgrade_div_boder_size', true ) );
    		$content_upgrade_div_border_color = esc_html(get_post_meta( $post->ID, 'content_upgrade_div_border_color', true ) );
    		$content_upgrade_div_text_color = esc_html(get_post_meta( $post->ID, 'content_upgrade_div_text_color', true ) );
    		$content_upgrade_cta_button_text = esc_html(get_post_meta( $post->ID, 'content_upgrade_cta_button_text', true ) );
    		$content_upgrade_cta_button_color = esc_html(get_post_meta( $post->ID, 'content_upgrade_cta_button_color', true ) );
    		$content_upgrade_cta_button_text_color = esc_html(get_post_meta( $post->ID, 'content_upgrade_cta_button_text_color', true ) );
    		$content_upgrade_cta_button_border_color = esc_html(get_post_meta( $post->ID, 'content_upgrade_cta_button_border_color', true ) );
    		$content_upgrade_cta_button_border_size = (int)get_post_meta( $post->ID, 'content_upgrade_cta_button_border_size', true );

            $merge_fields_order = get_post_meta( $post->ID, 'merge_fields_order', true );
            $merge_fields_use = get_post_meta( $post->ID, 'merge_fields_use', true );



        	$content_upgrade_cta_button_width = (int)get_post_meta( $post->ID, 'content_upgrade_cta_button_width', true );
        	$content_upgrade_cta_button_height = (int)get_post_meta( $post->ID, 'content_upgrade_cta_button_height', true );
        	$content_upgrade_hide_watermark = (int)get_post_meta( $post->ID, 'content_upgrade_hide_watermark', true );

            $content_upgrade_mailchimp_list = get_post_meta( $post->ID, 'content_upgrade_mailchimp_list', true );
            $wpcuppro_mailchimp_lists = json_decode(get_option( 'wpcuppro_mailchimp_lists' ));


    		// Set default values.
    		if( empty( $content_upgrade_share_shortcode ) ) $content_upgrade_share_shortcode = '[content_upgrade id='.(int)$post->ID.']';
    		if( empty( $content_upgrade_inlinecontent_text ) ) $content_upgrade_inlinecontent_text = __('This is your content', 'text_domain');
    		if( empty( $content_upgrade_div_text ) ) $content_upgrade_div_text = __('Click here to view content', 'text_domain');
    		if( empty( $content_upgrade_thankyou_text ) ) $content_upgrade_thankyou_text = __('Thank you!', 'text_domain');
    		if( empty( $content_upgrade_div_background_color ) ) $content_upgrade_div_background_color = "#FEF5C4";
    		if( empty( $content_upgrade_div_boder_size ) ) $content_upgrade_div_boder_size = "1";
    		if( empty( $content_upgrade_div_border_color ) ) $content_upgrade_div_border_color = "#FAE09A";
    		if( empty( $content_upgrade_div_text_color ) ) $content_upgrade_div_text_color = "#000";
    		if( empty( $content_upgrade_cta_button_text ) ) $content_upgrade_cta_button_text = __('Signup', 'text_domain');
    		if( empty( $content_upgrade_cta_button_color ) ) $content_upgrade_cta_button_color = "#4CAF50";
    		if( empty( $content_upgrade_cta_button_text_color ) ) $content_upgrade_cta_button_text_color = "#FFF";
    		if( empty( $content_upgrade_cta_button_border_color ) ) $content_upgrade_cta_button_border_color = "#8BC34A";
    		if( empty( $content_upgrade_cta_button_border_size ) ) $content_upgrade_cta_button_border_size = "1";

    		if( empty( $merge_fields_order ) ) $merge_fields_order = "";
    		if( empty( $merge_fields_use ) ) $merge_fields_use = "";

        	if( empty( $content_upgrade_cta_button_width ) ) $content_upgrade_cta_button_width = "80";
    		if( empty( $content_upgrade_cta_button_height ) ) $content_upgrade_cta_button_height = "42";
    		if( empty( $content_upgrade_hide_watermark ) ) $content_upgrade_hide_watermark = "0";


            if( empty( $content_upgrade_mailchimp_list ) ) $content_upgrade_mailchimp_list = "";
    		if( empty( $wpcuppro_mailchimp_lists ) ) $wpcuppro_mailchimp_lists = "";


    		// Form fields.
    		echo '<table class="form-table">';


            //Shortcode
                		echo '	<tr>';
                		echo '';
                		echo '		<td colspan="2">';
                		echo '			<input style="width:100%;" onclick="select()"  type="text" id="content_upgrade_share_shortcode" name="content_upgrade_share_shortcode" class="content_upgrade_share_shortcode_field" placeholder="' . esc_attr__( '', 'text_domain' ) . '" value="' . esc_attr__( $content_upgrade_share_shortcode ) . '">';
                		echo '			<p class="description">' . __( 'This is your shortcode. Copy and insert into your page.', 'text_domain' ) . '</p>';
                		echo '		</td>';
                		echo '	</tr>';

                        //Spacer
                                    echo '	<tr>';
                                    echo '';
                                    echo '		<td colspan="2">';
                                    echo '          <hr>';
                                    echo '		</td>';
                                    echo '	</tr>';

                                    //Title
                                                echo '	<tr>';
                                                echo '<th colspan="2"><h2 style="padding-left:0;font-size:22px;">Main content setup</h2><p>-</p></th>';
                                                echo '	</tr>';



                        //content_upgrade_div_text
                            		echo '	<tr>';
                            		echo '<th>Content Upgrade Teaser</th>';
                            		echo '		<td colspan="2">';
                            		echo wp_editor( htmlspecialchars_decode($content_upgrade_div_text), 'content_upgrade_div_text', $settings = array('textarea_name'=>'content_upgrade_div_text', 'textarea_rows'=>'3') );
                            		echo '			<p class="description">' . __( 'Text for teaser', 'text_domain' ) . '</p>';
                            		echo '		</td>';
                            		echo '	</tr>';

                                    //content_upgrade_inlinecontent_text
                                        		echo '	<tr>';
                                        		echo '<th>Content Upgrade Text</th>';
                                        		echo '		<td colspan="2">';
                                        		echo wp_editor( htmlspecialchars_decode($content_upgrade_inlinecontent_text), 'content_upgrade_inlinecontent_text', $settings = array('textarea_name'=>'content_upgrade_inlinecontent_text', 'textarea_rows'=>'3') );
                                        		echo '			<p class="description">' . __( 'Text for content upgrade popup', 'text_domain' ) . '</p>';
                                        		echo '		</td>';
                                        		echo '	</tr>';

                        //content_upgrade_hide_watermark
                            		echo '	<tr>';
                            		echo '<th>Remove the Content Upgrade Link?</th>';
                            		echo '		<td colspan="2">';
                                    echo '			<select id="content_upgrade_hide_watermark" name="" class="content_upgrade_hide_watermark_field">';
                                    echo '			<option value="0" ' . selected( $content_upgrade_hide_watermark, '0', false ) . '> ' . __( 'No, keep it', 'text_domain' );
                                    echo '			<option value=""> ' . __( 'Yes, remove it - PRO FEATURE', 'text_domain' );
                                    	echo '		<input type="hidden" name="content_upgrade_hide_watermark" value="0"></td>';
                            		echo '	</tr>';


                                                //Spacer
                                                    		echo '	<tr>';
                                                    		echo '';
                                                    		echo '		<td colspan="2">';
                                                            echo '          <hr>';
                                                    		echo '		</td>';
                                                    		echo '	</tr>';


// Mailchmip settings
//Title
            echo '	<tr>';
            echo '<th colspan="2"><h2 style="padding-left:0;font-size:22px;">Mailchimp settings for this content upgrade</h2><p>Select the list and other options for MailChimp.</p></th>';
            echo '	</tr>';

                echo '	<tr>';
                		echo '		<th>MailChmip list name</th>';
                		echo '		<td>';
                		echo '			<select id="content_upgrade_mailchimp_list" name="content_upgrade_mailchimp_list" class="car_currency_field">';
                		echo '			<option value="" ' . selected( $content_upgrade_mailchimp_list, '', false ) . '> ' . __( 'Please select a list', 'text_domain' ).'</option>';

                        $merge_fields_data = "";
                        foreach ( $wpcuppro_mailchimp_lists as $list ) {
                            echo '<option value="'.$list->id.'" ' . selected( esc_html( $content_upgrade_mailchimp_list ), $list->id, false ) . '>'.esc_html( $list->name ).'</option>';

                            if(selected( esc_html( $content_upgrade_mailchimp_list ), $list->id, false ) ) {
                            $merge_fields_data .= '<div id="merge_fields_value_'.$list->id.'" class="merge_fields_value_box" style="display:block;">';
                            } else {
                            $merge_fields_data .= '<div id="merge_fields_value_'.$list->id.'" style="display:none;">';
                            }

                            $merge_fields_data .= '<table class="widefat striped">';
                            $merge_fields_data .= '<tr>';
            				$merge_fields_data .= '<thead>';
            				$merge_fields_data .= '<th>Name</th>';
            				$merge_fields_data .= '<th>Tag</th>';
            				$merge_fields_data .= '<th>Type</th>';
            			//	$merge_fields_data .= '<th>Order</th>';
            				//$merge_fields_data .= '<th>Use this?</th>';
            				$merge_fields_data .= '</tr>';
            				$merge_fields_data .= '</thead>';

                            foreach ( $list->merge_fields as $merge_field ) {

                                if ( $merge_field->required ) {


                            $merge_fields_data .= '<tr>';
            				$merge_fields_data .= '<td>'.esc_html( $merge_field->name ).'';

                            $merge_fields_data .= '<span style="color:red;">*</span>';

                            $merge_fields_data .= '</td>';
            				$merge_fields_data .= '<td><code>'.esc_html( $merge_field->tag ).'</code></td>';
            				$merge_fields_data .= '<td>';

            				$merge_fields_data .= esc_html( $merge_field->field_type );

                            $coices = json_decode($merge_field->options);

                            if( ! empty( $coices->choices ) ) {
                        	       $merge_fields_data .= '(' . join( ', ', $coices->choices ) . ')';
            				}
            				$merge_fields_data .= '</td>';
            				//$merge_fields_data .= '<td>';
            				//$merge_fields_data .= '<input type="text" name="merge_fields_order['.$list->id.']">';
            				//$merge_fields_data .= '</td>';
            		//		$merge_fields_data .= '<td>';

                        //    $is_merge_fields_use = json_decode($merge_fields_use);
                        //    $merge_fields_name_nospaces = preg_replace('/\s+/', '', $merge_field->name);
                        //    $checked = "";
                        //    if($is_merge_fields_use->$merge_fields_name_nospaces == 'on') {
                        //        $merge_fields_data .= '<input type="checkbox" name="merge_fields_use['.$merge_field->name.']" checked="checked">';
                        //    } else {
                        //        $merge_fields_data .= '<input type="checkbox" name="merge_fields_use['.$merge_field->name.']">';
                        //    }

                        //	$merge_fields_data .= '</td>';
            				$merge_fields_data .= '</tr>';
            			}
                    		 }
            				$merge_fields_data .= '</table>';
            				$merge_fields_data .= '</div>';

                    }
                    echo '			</select>';
                    echo '		</td>';
                    echo '	</tr>';
                        //Merge fields
                            		echo '	<tr>';
                            		echo '		<th>Select fields</th>';
                            		echo '		<td colspan="2">';
                                    echo $merge_fields_data;
                                    echo '			<p class="description">' . __( 'This is the fields in your list. You can select what you want to display by cliking the checkbox. Fields marked with a red * is required.<br>On list change, press save / update.', 'text_domain' ) . '</p>';
                                    echo '		</td>';
                            		echo '	</tr>';

                        //Spacer
                            		echo '	<tr>';
                            		echo '';
                            		echo '		<td colspan="2">';
                                    echo '          <hr>';
                            		echo '		</td>';
                            		echo '	</tr>';



//Thank you text
    		echo '	<tr>';
    		echo '<th>Thank you text</th>';
    		echo '		<td colspan="2">';
    		echo wp_editor( htmlspecialchars_decode($content_upgrade_thankyou_text), 'content_upgrade_thankyou_text', $settings = array('textarea_name'=>'content_upgrade_thankyou_text', 'textarea_rows'=>'3') );
    		echo '			<p class="description">' . __( 'A small message after your visitors have completed signup. HTML allowed', 'text_domain' ) . '</p>';
    		echo '		</td>';
    		echo '	</tr>';





            //Spacer
                		echo '	<tr>';
                		echo '';
                		echo '		<td colspan="2">';
                        echo '          <hr>';
                		echo '		</td>';
                		echo '	</tr>';


            echo '<input type="hidden" id="content_upgrade_div_background_color" name="content_upgrade_div_background_color" class="content_upgrade_div_background_color_field" placeholder="' . esc_attr__( '', 'text_domain' ) . '" value="' . esc_attr__( $content_upgrade_div_background_color ) . '">';
            echo '			<input type="hidden" id="content_upgrade_div_boder_size" name="content_upgrade_div_boder_size" class="content_upgrade_div_boder_size_field" placeholder="' . esc_attr__( '', 'text_domain' ) . '" value="' . esc_attr__( $content_upgrade_div_boder_size ) . '"> px';
    		echo '			<input type="hidden" id="content_upgrade_div_border_color" name="content_upgrade_div_border_color" class="content_upgrade_div_border_color_field" placeholder="' . esc_attr__( '', 'text_domain' ) . '" value="' . esc_attr__( $content_upgrade_div_border_color ) . '">';
    		echo '			<input type="hidden" id="content_upgrade_div_text_color" name="content_upgrade_div_text_color" class="content_upgrade_div_text_color_field" placeholder="' . esc_attr__( '', 'text_domain' ) . '" value="' . esc_attr__( $content_upgrade_div_text_color ) . '">';
    		echo '			<input type="hidden" id="content_upgrade_cta_button_text" name="content_upgrade_cta_button_text" class="content_upgrade_cta_button_text_field" placeholder="' . esc_attr__( '', 'text_domain' ) . '" value="' . esc_attr__( $content_upgrade_cta_button_text ) . '">';
    		echo '			<input type="hidden" id="content_upgrade_cta_button_color" name="content_upgrade_cta_button_color" class="content_upgrade_cta_button_color_field" placeholder="' . esc_attr__( '', 'text_domain' ) . '" value="' . esc_attr__( $content_upgrade_cta_button_color ) . '">';
    		echo '			<input type="hidden" id="content_upgrade_cta_button_text_color" name="content_upgrade_cta_button_text_color" class="content_upgrade_cta_button_text_color_field" placeholder="' . esc_attr__( '', 'text_domain' ) . '" value="' . esc_attr__( $content_upgrade_cta_button_text_color ) . '">';
    		echo '			<input type="hidden" id="content_upgrade_cta_button_border_color" name="content_upgrade_cta_button_border_color" class="content_upgrade_cta_button_border_color_field" placeholder="' . esc_attr__( '', 'text_domain' ) . '" value="' . esc_attr__( $content_upgrade_cta_button_border_color ) . '">';
    		echo '			<input type="hidden" id="content_upgrade_cta_button_border_size" name="content_upgrade_cta_button_border_size" class="content_upgrade_cta_button_border_size_field" placeholder="' . esc_attr__( '', 'text_domain' ) . '" value="' . esc_attr__( $content_upgrade_cta_button_border_size ) . '"> px';
    		echo '			<input type="hidden" id="content_upgrade_cta_button_width" name="content_upgrade_cta_button_width" class="content_upgrade_cta_button_width_field" placeholder="' . esc_attr__( '', 'text_domain' ) . '" value="' . esc_attr__( $content_upgrade_cta_button_width ) . '"> %';
    		echo '			<input type="hidden" id="content_upgrade_cta_button_height" name="content_upgrade_cta_button_height" class="content_upgrade_cta_button_height_field" placeholder="' . esc_attr__( '', 'text_domain' ) . '" value="' . esc_attr__( $content_upgrade_cta_button_height ) . '"> px';

            echo '<tr><td><img src="'.plugin_dir_url( __FILE__ ) . '/images/tease/tease1.png">';
            echo '<img src="'.plugin_dir_url( __FILE__ ) . '/images/tease/tease2.png"></td></tr>';

    		echo '</table>';

    	}

    	public function save_metabox( $post_id, $post ) {

    		// Add nonce for security and authentication.
    		$nonce_name   = $_POST['content_upgrade_nonce'];
    		$nonce_action = 'content_upgrade_nonce_action';

    		// Check if a nonce is set.
    		if ( ! isset( $nonce_name ) )
    			return;

    		// Check if a nonce is valid.
    		if ( ! wp_verify_nonce( $nonce_name, $nonce_action ) )
    			return;

    		// Check if the user has permissions to save data.
    		if ( ! current_user_can( 'edit_post', $post_id ) )
    			return;

    		// Check if it's not an autosave.
    		if ( wp_is_post_autosave( $post_id ) )
    			return;

    		// Check if it's not a revision.
    		if ( wp_is_post_revision( $post_id ) )
    			return;

    		// Sanitize user input.

    		$content_upgrade_new_share_shortcode = isset( $_POST[ 'content_upgrade_share_shortcode' ] ) ? sanitize_text_field( $_POST[ 'content_upgrade_share_shortcode' ] ) : '';
            $content_upgrade_new_inlinecontent_text = isset( $_POST[ 'content_upgrade_inlinecontent_text' ] ) ? wp_kses_post($_POST[ 'content_upgrade_inlinecontent_text' ] ) : '';
            $content_upgrade_new_div_text  = isset( $_POST[ 'content_upgrade_div_text' ] ) ? wp_kses_post($_POST[ 'content_upgrade_div_text' ] ) : '';
            $content_upgrade_new_thankyou_text = isset( $_POST[ 'content_upgrade_thankyou_text' ] ) ? wp_kses_post($_POST[ 'content_upgrade_thankyou_text' ] ) : '';
            $content_upgrade_new_div_background_color = isset( $_POST[ 'content_upgrade_div_background_color' ] ) ? sanitize_text_field( $_POST[ 'content_upgrade_div_background_color' ] ) : '';
            $content_upgrade_new_div_boder_size = isset( $_POST[ 'content_upgrade_div_boder_size' ] ) ? sanitize_text_field( $_POST[ 'content_upgrade_div_boder_size' ] ) : '';
            $content_upgrade_new_div_border_color = isset( $_POST[ 'content_upgrade_div_border_color' ] ) ? sanitize_text_field( $_POST[ 'content_upgrade_div_border_color' ] ) : '';
            $content_upgrade_new_div_text_color = isset( $_POST[ 'content_upgrade_div_text_color' ] ) ? sanitize_text_field( $_POST[ 'content_upgrade_div_text_color' ] ) : '';
            $content_upgrade_new_cta_button_text = isset( $_POST[ 'content_upgrade_cta_button_text' ] ) ? sanitize_text_field( $_POST[ 'content_upgrade_cta_button_text' ] ) : '';
            $content_upgrade_new_cta_button_color = isset( $_POST[ 'content_upgrade_cta_button_color' ] ) ? sanitize_text_field( $_POST[ 'content_upgrade_cta_button_color' ] ) : '';
            $content_upgrade_new_cta_button_text_color = isset( $_POST[ 'content_upgrade_cta_button_text_color' ] ) ? sanitize_text_field( $_POST[ 'content_upgrade_cta_button_text_color' ] ) : '';
            $content_upgrade_new_cta_button_border_color = isset( $_POST[ 'content_upgrade_cta_button_border_color' ] ) ? sanitize_text_field( $_POST[ 'content_upgrade_cta_button_border_color' ] ) : '';
            $content_upgrade_new_cta_button_border_size = isset( $_POST[ 'content_upgrade_cta_button_border_size' ] ) ? (int)sanitize_text_field( $_POST[ 'content_upgrade_cta_button_border_size' ] ) : '';


//$_POST['merge_fields_order']
if(isset( $_POST['merge_fields_use'] )) {
            foreach ($_POST['merge_fields_use'] as $key => $value) {
                $new_key = preg_replace('/\s+/', '', $key);
                $new_merge_fields_use[$new_key] = $value;
            }
}

            $content_upgrade_new_cta_button_width = isset( $_POST[ 'content_upgrade_cta_button_width' ] ) ? (int)sanitize_text_field( $_POST[ 'content_upgrade_cta_button_width' ] ) : '';
            $content_upgrade_new_cta_button_height = isset( $_POST[ 'content_upgrade_cta_button_height' ] ) ? (int)sanitize_text_field( $_POST[ 'content_upgrade_cta_button_height' ] ) : '';
            $content_upgrade_new_hide_watermark = isset( $_POST[ 'content_upgrade_hide_watermark' ] ) ? (int)sanitize_text_field( $_POST[ 'content_upgrade_hide_watermark' ] ) : '';

            $content_upgrade_new_mailchimp_list = isset( $_POST[ 'content_upgrade_mailchimp_list' ] ) ? sanitize_text_field( $_POST[ 'content_upgrade_mailchimp_list' ] ) : '';

// Update the meta field in the database.
    		update_post_meta( $post_id, 'content_upgrade_share_shortcode', $content_upgrade_new_share_shortcode );

    		update_post_meta( $post_id, 'content_upgrade_inlinecontent_text', $content_upgrade_new_inlinecontent_text );
    		update_post_meta( $post_id, 'content_upgrade_div_text', $content_upgrade_new_div_text );

    		update_post_meta( $post_id, 'content_upgrade_thankyou_text', $content_upgrade_new_thankyou_text );
    		update_post_meta( $post_id, 'content_upgrade_div_background_color', $content_upgrade_new_div_background_color );
    		update_post_meta( $post_id, 'content_upgrade_div_boder_size', $content_upgrade_new_div_boder_size );
    		update_post_meta( $post_id, 'content_upgrade_div_border_color', $content_upgrade_new_div_border_color );
    		update_post_meta( $post_id, 'content_upgrade_div_text_color', $content_upgrade_new_div_text_color );
    		update_post_meta( $post_id, 'content_upgrade_cta_button_text', $content_upgrade_new_cta_button_text );
    		update_post_meta( $post_id, 'content_upgrade_cta_button_color', $content_upgrade_new_cta_button_color );
    		update_post_meta( $post_id, 'content_upgrade_cta_button_text_color', $content_upgrade_new_cta_button_text_color );
    		update_post_meta( $post_id, 'content_upgrade_cta_button_border_color', $content_upgrade_new_cta_button_border_color );
    		update_post_meta( $post_id, 'content_upgrade_cta_button_border_size', $content_upgrade_new_cta_button_border_size );

            update_post_meta( $post_id, 'merge_fields_order', json_encode($new_merge_fields_order) );
            update_post_meta( $post_id, 'merge_fields_use', json_encode($new_merge_fields_use) );

            update_post_meta( $post_id, 'content_upgrade_cta_button_width', $content_upgrade_new_cta_button_width );
            update_post_meta( $post_id, 'content_upgrade_cta_button_height', $content_upgrade_new_cta_button_height );
            update_post_meta( $post_id, 'content_upgrade_hide_watermark', $content_upgrade_new_hide_watermark );

            update_post_meta( $post_id, 'content_upgrade_mailchimp_list', $content_upgrade_new_mailchimp_list );


    	}



    // Register Custom Post Type
    function content_upgrade_post() {

    	$labels = array(
    		'name'                  => _x( 'Content upgrade ', 'Post Type General Name', 'wp-content-upgrade' ),
    		'singular_name'         => _x( 'Content upgrade ', 'Post Type Singular Name', 'wp-content-upgrade' ),
    		'menu_name'             => __( 'Content upgrade ', 'wp-content-upgrade' ),
    		'name_admin_bar'        => __( 'Content upgrade ', 'wp-content-upgrade' ),
    		'archives'              => __( 'Content upgrade  Archives', 'wp-content-upgrade' ),
    		'attributes'            => __( 'Content upgrade  Attributes', 'wp-content-upgrade' ),
    		'parent_item_colon'     => __( 'Parent Content upgrade :', 'wp-content-upgrade' ),
    		'all_items'             => __( 'All Content upgrade ', 'wp-content-upgrade' ),
    		'add_new_item'          => __( 'Add New Content upgrade ', 'wp-content-upgrade' ),
    		'add_new'               => __( 'Add New', 'wp-content-upgrade' ),
    		'new_item'              => __( 'New Content upgrade ', 'wp-content-upgrade' ),
    		'edit_item'             => __( 'Edit Content upgrade ', 'wp-content-upgrade' ),
    		'update_item'           => __( 'Update Content upgrade ', 'wp-content-upgrade' ),
    		'view_item'             => __( 'View Content upgrade ', 'wp-content-upgrade' ),
    		'view_items'            => __( 'View Content upgrades ', 'wp-content-upgrade' ),
    		'search_items'          => __( 'Search Content upgrade ', 'wp-content-upgrade' ),
    		'not_found'             => __( 'Not found', 'wp-content-upgrade' ),
    		'not_found_in_trash'    => __( 'Not found in Trash', 'wp-content-upgrade' ),
    		'featured_image'        => __( 'Featured Image', 'wp-content-upgrade' ),
    		'set_featured_image'    => __( 'Set featured image', 'wp-content-upgrade' ),
    		'remove_featured_image' => __( 'Remove featured image', 'wp-content-upgrade' ),
    		'use_featured_image'    => __( 'Use as featured image', 'wp-content-upgrade' ),
    		'insert_into_item'      => __( 'Insert into item', 'wp-content-upgrade' ),
    		'uploaded_to_this_item' => __( 'Uploaded to this item', 'wp-content-upgrade' ),
    		'items_list'            => __( 'Items list', 'wp-content-upgrade' ),
    		'items_list_navigation' => __( 'Items list navigation', 'wp-content-upgrade' ),
    		'filter_items_list'     => __( 'Filter items list', 'wp-content-upgrade' ),
    	);
    	$args = array(
    		'label'                 => __( 'Content upgrade', 'wp-content-upgrade' ),
    		'description'           => __( 'Create your content upgrades content', 'wp-content-upgrade' ),
    		'labels'                => $labels,
    		'supports'              => array( 'title', 'revisions' ),
    		'taxonomies'            => array(),
    		'hierarchical'          => false,
    		'public'                => true,
    		'show_ui'               => true,
    		'show_in_menu'          => true,
    		'menu_position'         => 5,
    		'show_in_admin_bar'     => true,
    		'show_in_nav_menus'     => false,
    		'can_export'            => true,
    		'has_archive'           => false,
    		'exclude_from_search'   => true,
    		'publicly_queryable'    => false,
    		'capability_type'       => 'page',
    	);
    	register_post_type( 'content-upgrade', $args );

    }

    /**
    *
    * Call scripts on frontend
    *
    */
    public function enqueue_styles_and_scripts() {

        wp_enqueue_style( "wp-content-upgrade-styles", plugin_dir_url( __FILE__ ) . 'css/styles.css', array(), "1.0.6", 'all' );
        wp_enqueue_script( "wp-content-upgrade-colorbox", plugin_dir_url( __FILE__ ) . 'js/jquery.colorbox.js', array( 'jquery' ), "1.0.0", false );
        wp_enqueue_script( "wp-content-upgrade-scripts", plugin_dir_url( __FILE__ ) . 'js/scripts.js', array( 'jquery' ), "1.0.0", true );
        wp_enqueue_script('jquery'); // Requires this.

    }

    /**
     *
     * ADD shortcode
     * Usage: [typedjs]CONTENT[/typedjs]
    */

    function content_upgrade_shortcode_function( $atts, $content = null ) {

        $options = get_option( 'content_upgrade_settings' );

        $html = "";
        $atts_id = (int)$atts['id'];
        if(@$atts['content']) {

            $html .= "<div style=\"display:none;\"><div id=\"inline_content\">".$atts['content']."</div></div>";
            $html .= '<div id="cup" class="engageCup id-'.$atts_id.'" href="#inline_content">' . $content . '</div>';
        } else {

            $args = array(
                'p'         => $atts_id, // ID of a page, post, or custom type
                'post_type' => 'content-upgrade'
            );

            $this_cup = new WP_Query($args);

            if ( $this_cup->have_posts() ) {
                while ( $this_cup->have_posts() ) { $this_cup->the_post();
                    $inline_content = nl2br(do_shortcode(get_the_content()));

                    $wpcuppro_mailchimp_lists = json_decode(get_option( 'wpcuppro_mailchimp_lists' ));
                    $content_upgrade_mailchimp_list = esc_html( get_post_meta( $atts['id'], 'content_upgrade_mailchimp_list', true ) );

                    $content_upgrade_inlinecontent_text = wp_kses_post( nl2br(get_post_meta( $atts['id'], 'content_upgrade_inlinecontent_text', true )) );
                    $content_upgrade_div_text = wp_kses_post( nl2br(get_post_meta( $atts['id'], 'content_upgrade_div_text', true )) );

                    $content_upgrade_thankyou_text = wp_kses_post(  nl2br(get_post_meta( $atts['id'], 'content_upgrade_thankyou_text', true )) );

                    $content_upgrade_div_background_color = esc_html( get_post_meta( $atts['id'], 'content_upgrade_div_background_color', true ) );
            		$content_upgrade_div_boder_size = esc_html( get_post_meta( $atts['id'], 'content_upgrade_div_boder_size', true ) );
            		$content_upgrade_div_border_color = esc_html( get_post_meta( $atts['id'], 'content_upgrade_div_border_color', true ) );
            		$content_upgrade_div_text_color = esc_html( get_post_meta( $atts['id'], 'content_upgrade_div_text_color', true ) );

                    $content_upgrade_cta_button_text = esc_html( nl2br(do_shortcode(get_post_meta( $atts['id'], 'content_upgrade_cta_button_text', true ))) );

                    $content_upgrade_cta_button_color = esc_html( get_post_meta( $atts['id'], 'content_upgrade_cta_button_color', true ) );

                    $content_upgrade_cta_button_text_color = esc_html( get_post_meta( $atts['id'], 'content_upgrade_cta_button_text_color', true ) );
            		$content_upgrade_cta_button_border_color = esc_html( get_post_meta( $atts['id'], 'content_upgrade_cta_button_border_color', true ) );
            		$content_upgrade_cta_button_border_size = (int)get_post_meta( $atts['id'], 'content_upgrade_cta_button_border_size', true );

                	$content_upgrade_cta_button_width = (int)get_post_meta( $atts['id'], 'content_upgrade_cta_button_width', true );
                	$content_upgrade_cta_button_height = (int)get_post_meta( $atts['id'], 'content_upgrade_cta_button_height', true );
                	$content_upgrade_hide_watermark = (int)get_post_meta( $atts['id'], 'content_upgrade_hide_watermark', true );



                }
                wp_reset_postdata();
            }
            wp_reset_query();

            $html .= "<div style=\"display:none;\"><div id=\"inline_content_".@$atts['id']."\">".$content_upgrade_inlinecontent_text."<br><br>";

            $html .= '<div id="wccup_thankyoutext_'.$atts['id'].'" style="display:none;">'.$content_upgrade_thankyou_text.'</div>';
            $html .= '<form id="wccup_signup_'.$atts['id'].'">';
            $html .= '<input type="hidden" name="action" value="wccup_submit">';
            $html .= '<div class="cup-input-group mc-field-group">';

            $merge_fields_data = "";

            foreach ( $wpcuppro_mailchimp_lists as $list ) {

                if(selected( esc_html( $content_upgrade_mailchimp_list ), $list->id, false ) ) {
                    $html .= '<input type="hidden" name="listid" value="'.$list->id.'">';


                foreach ( $list->merge_fields as $merge_field ) {
                    $required = "";
                    if ( $merge_field->required ) {
                    $required = 'required';

                    //$merge_field->tag
                    if($merge_field->field_type == 'email') {
                        $html .= '<input style="width:50%;margin:0px auto;margin-bottom:15px;" class="cup_input cup_input_email" type="email" placeholder="'.ucfirst(esc_html( $merge_field->name )).'" name="merge_vars[]['.$merge_field->name.']" class="'.$merge_field->tag.'">';
                    }
                    if($merge_field->field_type == 'text') {
                        $html .= '<input style="width:50%;margin:0px auto;margin-bottom:15px;" class="cup_input cup_input_text" type="text" placeholder="'.ucfirst(esc_html( $merge_field->name )).'" name="merge_vars[]['.$merge_field->tag.']" '.$required.' style="">';
                    }

                    }



            }

        }
        }

            $html .= '</div>';

            $html .= "<input type=\"submit\" value=\"$content_upgrade_cta_button_text\" name=\"subscribe\" id=\"mc-embedded-subscribe\" class=\"button cup-button\">";
            $html .= '</form>';

            $html .= '<script type="text/javascript">';
            $html .= 'jQuery(function() {


            	jQuery("#wccup_signup_'.$atts['id'].'").submit(function($) {
                    event.preventDefault();
            	jQuery.ajax(   {
                        type: "post",
            			url: "' . admin_url( 'admin-ajax.php' ) . '",
            			data: jQuery("#wccup_signup_'.$atts['id'].'").serialize(),
            			dataType: "jsonp",
            			complete: function(msg) {
                            var thankyoutext = jQuery("#wccup_thankyoutext_'.$atts['id'].'").html();
                          jQuery("#wccup_signup_'.$atts['id'].'").html(thankyoutext);

            				}
            			});

            		return false;
            	});


            });';
            $html .= '</script>';


            // Hi Developer.Please do not remove this code. We cannot develope good tools without paying customers. Feel free to use our free version.
            // Hope you respect this. If you got any suggestions for us, please contact.
            if($options['content_upgrade_hide_watermark'] != '1') {
            $html .= '<p style="font-size: 14px;width: 100%;text-align: center;">Free Content Upgrade Plugin from <a href="http://LeadTools.io" target="_blank" style="text-decoration:underline;">LeadTools.io</a></p>';
             }

            $html .= "</div></div>";
            $html .= '<div id="cup" class="engageCup id-'.@$atts['id'].'" href="#inline_content_'.@$atts['id'].'">' . $content_upgrade_div_text . '</div>';

            $html .= "<style>";
                $html .= "#cup.id-".@$atts['id']." {";
                $html .= "background-color: $content_upgrade_div_background_color;";
                $html .= "border-width: ".$content_upgrade_div_boder_size."px;";
                $html .= "border-color: $content_upgrade_div_border_color;";
                $html .= "color: $content_upgrade_div_text_color;";
            $html .= " }";
            $html .= "#inline_content_".@$atts['id']." .cup-button {";
                $html .= "background-color: $content_upgrade_cta_button_color;";
                $html .= "color: $content_upgrade_cta_button_text_color;";
                $html .= "border: ".$content_upgrade_cta_button_border_size."px solid $content_upgrade_cta_button_border_color;";
                $html .= "border-radius: 3px;";
                $html .= "width: ".$content_upgrade_cta_button_width."%;";
                $html .= "height: ".$content_upgrade_cta_button_height."px;";
                $html .= "margin: 15px auto;";
                $html .= "display: block;";
                $html .= "padding: 16px 12px;";
            $html .= " }";
            $html .= "</style>";
        }


    	return $html;
    }



}


$WP_Content_Upgrade = new WP_Content_Upgrade();
