<?php
/*
Plugin Name: Gravity Forms Captcha
Plugin URI: http://www.oxosolutions.com
Description: Adds captcha functionality to Gravity Forms.
Version: 1.0
Author: SGS Sandhu
Author URI: http://www.sgssandhu.com

------------------------------------------------------------------------
Copyright 2014 SGS Sandhu
last updated: January 14, 2014

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

add_filter( 'gform_field_content', array( 'GFCaptcha', 'captcha_field_content' ), 10, 5 );
add_action( 'init',  array( 'GFCaptcha', 'init' ) );

class GFCaptcha {
    private static $path                        = 'gfcaptcha/gfcaptcha.php';
    private static $url                         = 'http://www.oxosolutions.com';
    private static $slug                        = 'gfcaptcha';
    private static $version                     = '1.0';
    private static $min_gravityforms_version    = '1.6.0';
	/*
     * Plugin starting point. Will load appropriate files
     */
    public static function init() {

        if ( RG_CURRENT_PAGE == 'plugins.php' ) {
            add_action( 'after_plugin_row_' . self::$path, array( 'GFCaptcha', 'plugin_row' ) );

            //force new remote request for version info on the plugin page
            self::flush_version_info();
        }

        if (!self::is_gravityforms_supported())
           return;

        if ( is_admin() ) {
            //automatic upgrade hooks
            add_action( 'install_plugins_pre_plugin-information', array( 'GFCaptcha', 'display_changelog' ) );

            // Build custom captcha field
            add_filter( 'gform_add_field_buttons', array( 'GFCaptcha', 'add_captcha_field' ) );
            add_filter( 'gform_pre_render', array( 'GFCaptcha', 'captcha_pre_render' ) );
            add_filter( 'gform_admin_pre_render', array( 'GFCaptcha', 'captcha_admin_pre_render' ) );

            //loading Gravity Forms tooltips
            require_once(GFCommon::get_base_path() . '/tooltips.php');
            add_filter( 'gform_tooltips', array( 'GFCaptcha', 'tooltips' ) );

            // Settings Page
            if ( self::is_gfcaptcha_page() ) {
                //enqueueing sack for AJAX requests
                wp_enqueue_script( array( 'sack' ) );
            }
            elseif ( in_array( RG_CURRENT_PAGE, array( 'admin-ajax.php' ) ) ) {
                add_action( 'wp_ajax_gf_gfcaptcha_confirm_settings', array( 'GFCaptcha', 'confirm_settings' ) );
            }
            elseif ( RGForms::get( 'page' ) == 'gf_settings' ) {
                RGForms::add_settings_page( 'GFCaptcha', array( 'GFCaptcha', 'settings_page' ), self::get_base_url() . '/images/gfcaptcha_wordpress_icon_32.png' );
            }
        }
        else {
            //handling post submission.
            add_filter( 'gform_confirmation', array( 'GFCaptcha', 'validate_captcha' ), 1000, 4 );
            add_filter( 'gform_entry_field_value', array( 'GFCaptcha', 'remove_captcha' ), 10, 4 );
        }
    }

    //-------------- Automatic upgrade ---------------------------------------
    public static function flush_version_info() {
        if( !class_exists( 'GFCaptchaUpgrade' ) )
            require_once('plugin-upgrade.php');

        GFCaptchaUpgrade::set_version_info( false );
    }

    public static function plugin_row() {
        if ( !self::is_gravityforms_supported() ) {
            $message = sprintf( __( 'Gravity Forms ' . self::$min_gravityforms_version . ' is required. Activate it now or %spurchase it today!%s', 'gfcaptcha' ), '<a href="http://www.gravityforms.com">', '</a>' );
            GFCaptchaUpgrade::display_plugin_message( $message, true );
        }/* In Case we ever implement upgrade path
        else {
            $version_info = GFCaptchaUpgrade::get_version_info( self::$slug, self::get_key(), self::$version );

            if ( !$version_info['is_valid_key'] ) {
                $new_version = version_compare( self::$version, $version_info['version'], '<' ) ? __( 'There is a new version of Gravity Forms GFCaptcha Add-On available.', 'gfcaptcha' ) .' <a class="thickbox" title="Gravity Forms GFCaptcha Add-On" href="plugin-install.php?tab=plugin-information&plugin=' . self::$slug . '&TB_iframe=true&width=640&height=808">'. sprintf( __( 'View version %s Details', 'gfcaptcha' ), $version_info['version'] ) . '</a>. ' : '';
                $message     = $new_version . sprintf( __( '%sRegister%s your copy of Gravity Forms to receive access to automatic upgrades and support. Need a license key? %sPurchase one now%s.', 'gfcaptcha' ), '<a href="admin.php?page=gf_settings">', '</a>', '<a href="http://www.gravityforms.com">', '</a>' ) . '</div></td>';
                GFCaptchaUpgrade::display_plugin_message( $message );
            }
        }*/
    }

    //Displays current version details on Plugin's page
    public static function display_changelog() {
        if ( $_REQUEST['plugin'] != self::$slug )
            return;

        //loading upgrade lib
        if ( !class_exists( 'GFCaptchaUpgrade' ) )
            require_once('plugin-upgrade.php');

        GFCaptchaUpgrade::display_changelog( self::$slug, self::get_key(), self::$version );
    }

    private static function get_key() {
        if(self::is_gravityforms_supported())
            return GFCommon::get_key();
        else
            return '';
    }
    //------------------------------------------------------------------------

    // Adds custom captcha button to fields menus on edit page
    public static function add_captcha_field( $field_groups ) {
        // Add the field to Advanced Fields array
        $field_groups[1]['fields'][] = array( 'class' => 'button', 'value' => 'GFCaptcha', 'onclick' => "StartAddField('gfcaptcha');" );

        return $field_groups;
    }

    /*
     * Function to keep captcha from showing on recept page
     */
    public static function remove_captcha( $value, $field, $lead, $form ) {
        // Pull necessary information

        if ( $field['type'] == 'gfcaptcha' ) {
            return '';
        }


        return $value;
    }

    public static function captcha_field_content( $content, $field, $value, $lead_id, $form_id ) {
        if ( $field['type'] == 'gfcaptcha' ) {
            if ( RG_CURRENT_VIEW == 'entry' ) {
                $mode = empty( $_POST['screen_mode'] ) ? 'view' : $_POST['screen_mode'];
                if ( $mode == 'view' ) {
                    $content = '<tr>
                        <td colspan="2" class="entry-view-section-break">Testing</td>
                    </tr>';
                }
                else {
                    $content = '<tr valign="top">
                        <td class="detail-view">
                            <div style="margin-bottom:10px; border-bottom:1px dotted #ccc;"><h2 class="detail_gsection_title">Testing</h2></div>
                        </td>
                    </tr>';
                }
            } //-- current view == entry
            else {
                $delete_field_link = "<a class='field_delete_icon' id='gfield_delete_".$field['id']."' title='" . __( 'click to delete this field', 'gravityforms' ) . "' href='javascript:void(0);' onclick='StartDeleteField(this);'>" . __( 'Delete', 'gravityforms' ) . '</a>';
                $delete_field_link = apply_filters( 'gform_delete_field_link', $delete_field_link );

                $edit_field_link = " <a class='field_edit_icon edit_icon_collapsed' title='" . __( 'click to edit this field', 'gravityforms' ) . "'>" . __( 'Edit', 'gravityforms' ) . '</a>';

                $title = "<div class='gfield_admin_header_title'>" . $field['label'] . ' : ' . $field['id'] . '</div>';

                //The edit and delete links need to be added to the content (if desired), when using this hook
                $admin_buttons = IS_ADMIN ? $title . $delete_field_link . $edit_field_link  : '';
                $admin_buttons = IS_ADMIN ? "<div class='gfield_admin_icons'>" . $admin_buttons . '</div>' : '';
                $content = $admin_buttons;

                // Build captcha setting string
                $captcha_settings = array(
                        'captcha_type'                    => get_option( 'gf_gfcaptcha_captcha_type' ),
                        'case_sensitive'                  => get_option( 'gf_gfcaptcha_case_sensitive' ),
                        'image_height'                    => get_option( 'gf_gfcaptcha_image_height' ),
                        'perturbation'                    => get_option( 'gf_gfcaptcha_perturbation' ),
                        'image_bg_color'                  => get_option( 'gf_gfcaptcha_image_bg_color' ),
                        'text_color'                      => get_option( 'gf_gfcaptcha_text_color' ),
                        'num_lines'                       => get_option( 'gf_gfcaptcha_num_lines' ),
                        'line_color'                      => get_option( 'gf_gfcaptcha_line_color' ),
                        'image_type'                      => get_option( 'gf_gfcaptcha_image_type' ),
                        'code_length'                     => get_option( 'gf_gfcaptcha_code_length' ),
                        'image_width'                     => get_option( 'gf_gfcaptcha_image_width' ),
                        'noise_level'                     => get_option( 'gf_gfcaptcha_noise_level' ),
                        'signature_color'                 => get_option( 'gf_gfcaptcha_signature_color' ),
                        'noise_color'                     => get_option( 'gf_gfcaptcha_noise_color' ),
                        'use_wordlist'                    => get_option( 'gf_gfcaptcha_use_wordlist' ),
                        'use_transparent_text'            => get_option( 'gf_gfcaptcha_use_transparent_text' ),
                        'text_transparency_percentage'    => get_option( 'gf_gfcaptcha_text_transparency_percentage' ),
                        'image_signature'                 => get_option( 'gf_gfcaptcha_image_signature' ),
                );

                $captcha_string = '';
                foreach ( $captcha_settings as $key => $value ) {
                    $value = str_replace( '#', '', $value );
                    $captcha_string .= '&'.$key.'='.$value;
                }

                // Need to have 2 different renderings due to differences in admin/front-end.
                if ( is_admin() ) {
                    $content .= "<label class='gfield_label' for='input_".$field['id']."'>".$field['label'].'</label>';
                    $content .= "<div class='ginput_container'>";
                    // Create Image
                    $content .= "<img id='siimage' style='border: 1px solid #000; margin-right: 15px' src='".plugins_url( 'gfcaptcha' ).'/includes/securimage_show.php?sid=' . md5( uniqid() ) . $captcha_string . "' alt='CAPTCHA Image' align='left'>";
                    // Create 'Play Code' Link
                    $content .= "<object type='application/x-shockwave-flash' data='" . plugins_url( 'gfcaptcha' ) . '/includes/securimage_play.swf?audio_file=' . plugins_url( 'gfcaptcha' ) . "/includes/securimage_play.php&amp;bgColor1=#fff&amp;bgColor2=#fff&amp;iconColor=#777&amp;borderWidth=1&amp;borderColor=#000' height='32' width='32'>";
                    $content .= "<param name='movie' value='" . plugins_url( 'gfcaptcha' ) . '/includes/securimage_play.swf?audio_file=' . plugins_url( 'gfcaptcha' ) . "/includes/securimage_play.php&amp;bgColor1=#fff&amp;bgColor2=#fff&amp;iconColor=#777&amp;borderWidth=1&amp;borderColor=#000'>";
                    $content .= '</object>';
                    // Create 'New Image ' button
                    $content .= "<a tabindex='-1' style='border-style: none;' href='#' title='Refresh Image' onclick='document.getElementById('siimage').src = \'this.blur(); return false'>";
                    $content .= "<img src='" . plugins_url( 'gfcaptcha' ) . "/includes/images/refresh.png' alt='Reload Image' onclick='this.blur()' align='bottom' border='0'>";
                    $content .= '</a>';
                    $content .= '<br />';
                    // Create Field
                    $content .= '<strong>Enter Code*:</strong><br />';
                    $content .= "<input type='text' name='input_".$field['id']."' id='input_".$field['id']."' size='12' maxlength='8' disabled='disabled' />";
                    $content .= '</div>';
                }
                else {
                    ob_start();
                    ?>
<label class="gfield_label" for="input_<?php echo $field['id'] ?>"><?php echo $field['label'] ?></label>
<div>
<img id="siimage" style="border: 1px solid #000; margin-right: 15px" src="<?php echo plugins_url( 'gfcaptcha' ); ?>/includes/securimage_show.php?sid=<?php echo md5( uniqid() ) . $captcha_string; ?>" alt="CAPTCHA Image" align="left">
<object type="application/x-shockwave-flash" data="<?php echo plugins_url( 'gfcaptcha' ); ?>/includes/securimage_play.swf?audio_file=<?php echo plugins_url( 'gfcaptcha' ); ?>/includes/securimage_play.php&amp;bgColor1=#fff&amp;bgColor2=#fff&amp;iconColor=#777&amp;borderWidth=1&amp;borderColor=#000" height="32" width="32">
<param name="movie" value="<?php echo plugins_url( 'gfcaptcha' ); ?>/includes/securimage_play.swf?audio_file=<?php echo plugins_url( 'gfcaptcha' ); ?>/includes/securimage_play.php&amp;bgColor1=#fff&amp;bgColor2=#fff&amp;iconColor=#777&amp;borderWidth=1&amp;borderColor=#000">
</object>
<a tabindex="-1" style="border-style:none;" href="#" title="Refresh Image" onclick="document.getElementById('siimage').src = '<?php echo plugins_url( 'gfcaptcha' ); ?>/includes/securimage_show.php?sid=' + Math.random() + '<?php echo $captcha_string; ?>'; this.blur(); return false">
<img src="<?php echo plugins_url( 'gfcaptcha' ); ?>/includes/images/refresh.png" alt="Reload Image" onclick="this.blur()" align="bottom" border="0">
</a>
<br />
<strong>Enter Code*:</strong><br />
<input type="text" name="input_<?php echo $field['id'] ?>" id="input_<?php echo $field['id'] ?>" size="12" maxlength="8" <?php echo is_admin() ? 'disabled="disabled"' : '' ?> />
</div>
                    <?php
                    $content .= ob_get_clean();
                }
            } //-- else
        } //-- field['type'] == 'gfcaptcha'
        return $content;
    }

    // Build the field on button press
    public static function captcha_admin_pre_render( $form ) {
        foreach ( $form['fields'] as $i => $field ) {
            if ( $field['type'] == 'gfcaptcha' ) {
                $form['fields'][$i]['displayAllCategories']   = true;
                $form['fields'][$i]['adminLabel']             = 'GFCaptcha';
                $form['fields'][$i]['adminOnly']              = false;
                $form['fields'][$i]['inputType']              = 'text';
                $form['fields'][$i]['inputName']              = 'gfcaptcha';
                $form['fields'][$i]['label']                  = 'GFCaptcha';
            }
        }

        return $form;
    }



    //------------------------------------------------------------------------

    //Creates or updates database tables. Will only run when version changes
    private static function setup() {
        if ( get_option( 'gf_gfcaptcha_version' ) != self::$version )
            GFCaptchaData::update_table();

        update_option( 'gf_gfcaptcha_version', self::$version );
    }

    //Adds feed tooltips to the list of tooltips
    public static function tooltips( $tooltips ) {
        $gfcaptcha_tooltips = array(
            'gfcaptcha_gravity_form'             => '<h6>' . __( 'Gravity Form', 'gfcaptcha' ) . '</h6>' . __( 'Select which Gravity Forms you would like to integrate with GFCaptcha.', 'gfcaptcha' ),
            'gfcaptcha_page_style'               => '<h6>' . __( 'Page Style', 'gfcaptcha' ) . '</h6>' . __( 'This option allows you to select which GFCaptcha page style should be used if you have setup a custom payment page style with GFCaptcha.', 'gfcaptcha' ),
            'gfcaptcha_continue_button_label'    => '<h6>' . __( 'Continue Button Label', 'gfcaptcha' ) . '</h6>' . __( 'Enter the text that should appear on the continue button once payment has been completed via GFCaptcha.', 'gfcaptcha' ),
            'gfcaptcha_options'                  => '<h6>' . __( 'Options', 'gfcaptcha' ) . '</h6>' . __( 'Turn on or off the available GFCaptcha checkout options.', 'gfcaptcha' ),
            'gfcaptcha_conditional'              => '<h6>' . __( 'GFCaptcha Condition', 'gfcaptcha' ) . '</h6>' . __( 'When the GFCaptcha condition is enabled, form submissions will only be sent to GFCaptcha when the condition is met. When disabled all form submissions will be sent to GFCaptcha.', 'gfcaptcha' ),
        );
        $gfcaptcha_tooltips['gfcaptcha_captcha_type']                   = '<h6>' . __( 'Letters', 'gfcaptcha' ) . '</h6>' . __( 'Will display random letters', 'gfcaptcha' ) . '<h6>' . __( 'Math</h6> will require the user to solve a simple math problem.', 'gfcaptcha' ) . ' <br /><em>' . __( 'Default: Letters', 'gfcaptcha' ) . '</em>';
        $gfcaptcha_tooltips['gfcaptcha_case_sensitive']                 = '<h6>' . __( 'Case Sensitive', 'gfcaptcha' ) . '</h6>' . __( 'Selecting Yes will require the user to not only match the letters, but also the capitalization.', 'gfcaptcha' ) . ' <br /><em>' . __( 'Default: No', 'gfcaptcha' ) . '</em>';
        $gfcaptcha_tooltips['gfcaptcha_image_height']                   = '<h6>' . __( 'Image Height', 'gfcaptcha' ) . '</h6>' . __( 'The height of the code image in pixels(px). The width will be calculated to maintain ratio.', 'gfcaptcha' ) . ' <br /><em>' . __( 'Default: 90', 'gfcaptcha' ) . '</em>';
        $gfcaptcha_tooltips['gfcaptcha_perturbation']                   = '<h6>' . __( 'Level of Distortion', 'gfcaptcha' ) . '</h6>' . __( '100% = High level of distortion. 0% = No distortion.', 'gfcaptcha' ) . ' <br /><em>' . __( 'Default: 75%', 'gfcaptcha' ) . '</em>';
        $gfcaptcha_tooltips['gfcaptcha_image_bg_color']                 = '<h6>' . __( 'Background Color', 'gfcaptcha' ) . '</h6>' . __( 'The color to use for the background. Value is a Hex color code: <a href="http://www.webmonkey.com/2010/02/color_charts/" title="Color Chart">Color Chart</a>.', 'gfcaptcha' ) . ' <br /><em>' . __( 'Default: #0099CC', 'gfcaptcha' ) . '</em>';
        $gfcaptcha_tooltips['gfcaptcha_text_color']                     = '<h6>' . __( 'Text Color', 'gfcaptcha' ) . '</h6>' . __( 'The color to use for the text. Value is a Hex color code: <a href="http://www.webmonkey.com/2010/02/color_charts/" title="Color Chart">Color Chart</a>.', 'gfcaptcha' ) . ' <br /><em>' . __( 'Default: #EAEAEA', 'gfcaptcha' ) . '</em>';
        $gfcaptcha_tooltips['gfcaptcha_num_lines']                      = '<h6>' . __( 'Number of Lines', 'gfcaptcha' ) . '</h6>' . __( 'The number of lines used to obfuscate the text.', 'gfcaptcha' ) . ' <br /><em>' . __( 'Default: 8', 'gfcaptcha' ) . '</em>';
        $gfcaptcha_tooltips['gfcaptcha_line_color']                     = '<h6>' . __( 'Line Color', 'gfcaptcha' ) . '</h6>' . __( 'The color to use for the background. Value is a Hex color code: <a href="http://www.webmonkey.com/2010/02/color_charts/" title="Color Chart">Color Chart</a>.', 'gfcaptcha' ) . ' <br /><em>' . __( 'Default: #0000CC', 'gfcaptcha' ) . '</em>';
        $gfcaptcha_tooltips['gfcaptcha_image_type']                     = '<h6>' . __( 'Image Type', 'gfcaptcha' ) . '</h6>' . __( 'The type of image to use to display the captcha code.  This should be PNG unless you encounter issues using this.', 'gfcaptcha' ) . ' <br /><em>' . __( 'Default: PNG', 'gfcaptcha' ) . '</em>';
        $gfcaptcha_tooltips['gfcaptcha_code_length']                    = '<h6>' . __( 'Code Length', 'gfcaptcha' ) . '</h6>' . __( 'The number of characters to display.', 'gfcaptcha' ) . ' <br /><em>' . __( 'Default: Set to 0 or leave empty for a random 5-6 character length', 'gfcaptcha' ) . '</em>';
        $gfcaptcha_tooltips['gfcaptcha_image_width']                    = '<h6>' . __( 'Image Width', 'gfcaptcha' ) . '</h6>' . __( 'The width of the captcha code image.', 'gfcaptcha' ) . ' <br /><em>' . __( 'Default: 215', 'gfcaptcha' ) . '</em>';
        $gfcaptcha_tooltips['gfcaptcha_noise_level']                    = '<h6>' . __( 'Noise Level', 'gfcaptcha' ) . '</h6>' . __( 'The level of noise (random dots) to place on the image. 0 = none, 10 = high', 'gfcaptcha' ) . ' <br /><em>' . __( 'Default: 0', 'gfcaptcha' ) . '</em>';
        $gfcaptcha_tooltips['gfcaptcha_signature_color']                = '<h6>' . __( 'Signature Color', 'gfcaptcha' ) . '</h6>' . __( 'The color to use for the signature text.', 'gfcaptcha' ) . ' <br /><em>' . __( 'Default: #707070', 'gfcaptcha' ) . '</em>';
        $gfcaptcha_tooltips['gfcaptcha_noise_color' ]                   = '<h6>' . __( 'Noise Color', 'gfcaptcha' ) . '</h6>' . __( 'The color to use for the Noise dots.', 'gfcaptcha' ) . ' <br /><em>' . __( 'Default: #707070', 'gfcaptcha' ) . '</em>';
        $gfcaptcha_tooltips['gfcaptcha_use_wordlist']                   = '<h6>' . __( 'Use Word List?', 'gfcaptcha' ) . '</h6>' . __( 'Whether or not to use the pre-set list of words', 'gfcaptcha' ) . ' <br /><em>' . __( 'Default: No', 'gfcaptcha' ) . '</em>';
        $gfcaptcha_tooltips['gfcaptcha_use_transparent_text' ]          = '<h6>' . __( 'Use Transparent Text?', 'gfcaptcha' ) . '</h6>' . __( 'Whether or not to make the text transparent.', 'gfcaptcha' ) . ' <br /><em>' . __( 'Default: No', 'gfcaptcha' ) . '</em>';
        $gfcaptcha_tooltips['gfcaptcha_text_transparency_percentage']   = '<h6>' . __( 'Text Transparency Level', 'gfcaptcha' ) . '</h6>' . __( 'How transparent to make the text. 100% = invisible, 0% = completely opaque.', 'gfcaptcha' ) . ' <br /><em>' . __( 'Default: 30%', 'gfcaptcha' ) . '</em>';
        $gfcaptcha_tooltips['gfcaptcha_image_signature']                = '<h6>' . __( 'Image Signature', 'gfcaptcha' ) . '</h6>' . __( 'The signature text to draw on the bottom corner of the image.', 'gfcaptcha' ) . ' <br /><em>' . __( 'Default: EMPTY', 'gfcaptcha' ) . '</em>';


        return array_merge( $tooltips, $gfcaptcha_tooltips );
    }

    public static function gfcaptcha_page() {
        $view = rgget( 'view' );
        if( $view == 'edit' )
            self::edit_page( rgget( 'id' ) );
        else if( $view == 'stats' )
            self::stats_page( rgget( 'id' ) );
        else
            echo self::list_page();

    }

    public static function confirm_settings() {
        update_option( 'gf_gfcaptcha_captcha_type', $_POST['gfcaptcha_captcha_type'] );
        update_option( 'gf_gfcaptcha_case_sensitive', $_POST['gfcaptcha_case_sensitive'] );
        update_option( 'gf_gfcaptcha_image_height', $_POST['gfcaptcha_image_height'] );
        update_option( 'gf_gfcaptcha_perturbation', $_POST['gfcaptcha_perturbation'] );
        update_option( 'gf_gfcaptcha_image_bg_color', $_POST['gfcaptcha_image_bg_color'] );
        update_option( 'gf_gfcaptcha_text_color', $_POST['gfcaptcha_text_color'] );
        update_option( 'gf_gfcaptcha_num_lines', $_POST['gfcaptcha_num_lines'] );
        update_option( 'gf_gfcaptcha_line_color', $_POST['gfcaptcha_line_color'] );
        update_option( 'gf_gfcaptcha_image_type', $_POST['gfcaptcha_image_type'] );

        update_option( 'gf_gfcaptcha_code_length', $_POST['gfcaptcha_code_length'] );
        update_option( 'gf_gfcaptcha_image_width', $_POST['gfcaptcha_image_width'] );
        update_option( 'gf_gfcaptcha_noise_level', $_POST['gfcaptcha_noise_level'] );
        update_option( 'gf_gfcaptcha_signature_color', $_POST['gfcaptcha_signature_color'] );
        update_option( 'gf_gfcaptcha_noise_color', $_POST['gfcaptcha_noise_color' ] );
        update_option( 'gf_gfcaptcha_use_wordlist', $_POST['gfcaptcha_use_wordlist'] );
        update_option( 'gf_gfcaptcha_use_transparent_text', $_POST['gfcaptcha_use_transparent_text' ] );
        update_option( 'gf_gfcaptcha_text_transparency_percentage', $_POST['gfcaptcha_text_transparency_percentage'] );
        update_option( 'gf_gfcaptcha_image_signature', $_POST['gfcaptcha_image_signature'] );
    }

    public static function settings_page() {

        if ( rgpost( 'uninstall' ) ) {
            check_admin_referer( 'uninstall', 'gf_gfcaptcha_uninstall' );
            self::uninstall();

            ?>
            <div class="updated fade" style="padding:20px;"><?php _e( sprintf( 'Gravity Forms GFCaptcha Add-On have been successfully uninstalled. It can be re-activated from the %splugins page%s.', '<a href="plugins.php">','</a>' ), 'gfcaptcha' ); ?></div>
            <?php
            return;
        }

        $captcha_type                   = get_option( 'gf_gfcaptcha_captcha_type' );
        $case_sensitive                 = get_option( 'gf_gfcaptcha_case_sensitive' );
        $image_height                   = get_option( 'gf_gfcaptcha_image_height' );
        $perturbation                   = get_option( 'gf_gfcaptcha_perturbation' );
        $image_bg_color                 = get_option( 'gf_gfcaptcha_image_bg_color' );
        $text_color                     = get_option( 'gf_gfcaptcha_text_color' );
        $num_lines                      = get_option( 'gf_gfcaptcha_num_lines' );
        $line_color                     = get_option( 'gf_gfcaptcha_line_color' );
        $image_type                     = get_option( 'gf_gfcaptcha_image_type' );
        $code_length                    = get_option( 'gf_gfcaptcha_code_length' );
        $image_width                    = get_option( 'gf_gfcaptcha_image_width' );
        $noise_level                    = get_option( 'gf_gfcaptcha_noise_level' );
        $signature_color                = get_option( 'gf_gfcaptcha_signature_color' );
        $noise_color                    = get_option( 'gf_gfcaptcha_noise_color' );
        $use_wordlist                   = get_option( 'gf_gfcaptcha_use_wordlist' );
        $use_transparent_text           = get_option( 'gf_gfcaptcha_use_transparent_text' );
        $text_transparency_percentage   = get_option( 'gf_gfcaptcha_text_transparency_percentage' );
        $image_signature                = get_option( 'gf_gfcaptcha_image_signature' );
        ?>
        <form>
            <?php wp_nonce_field( 'update', 'gf_gfcaptcha_update' ) ?>
            <h3><?php _e( 'GFCaptcha Settings', 'gfcaptcha' ) ?></h3>
            <h4><?php _e( 'Captcha Creation Settings', 'gfcaptcha' ) ?></h4>
            <table class="form-table">
                <tbody>
                    <!--
                        Code Specific settings
                        - Captcha type
                        - Case Sensitive
                        - Code Length
                        - Use Word List?
                    -->
                    <tr valign="top">
                        <th scope="row">
                            <label for ="gf_gfcaptcha_captcha_type" class="inline"><?php _e( 'Captcha Type' )?> <?php gform_tooltip( 'gfcaptcha_captcha_type' ) ?></label>
                        </th>
                        <td>
                            <select name="gf_gfcaptcha_captcha_type" id="gf_gfcaptcha_captcha_type">
                                <option value="letters" <?php echo ($captcha_type !== 'math') ? 'selected="selected"' : '';?>>Letters</option>
                                <option value="math" <?php echo ($captcha_type == 'math') ? 'selected="selected"' : '';?>>Math Problem</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for ="gf_gfcaptcha_case_sensitive" class="inline"><?php _e( 'Case Sensitive?' )?> <?php gform_tooltip( 'gfcaptcha_case_sensitive' ) ?></label>
                        </th>
                        <td>
                            <select name="gf_gfcaptcha_case_sensitive" id="gf_gfcaptcha_case_sensitive">
                                <option value="1" <?php echo ($case_sensitive == '1') ? 'selected="selected"' : '';?>>Yes</option>
                                <option value="0" <?php echo ($case_sensitive !== '1') ? 'selected="selected"' : '';?>>No</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for ="gf_gfcaptcha_code_length" class="inline"><?php _e( 'Code Length' )?> <?php gform_tooltip( 'gfcaptcha_code_length' ) ?></label>
                        </th>
                        <td>
                            <input type="text" name="gf_gfcaptcha_code_length" id="gf_gfcaptcha_code_length" value="<?php echo ($code_length) ? $code_length : ''; ?>" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for ="gf_gfcaptcha_use_wordlist" class="inline"><?php _e( 'Use Word List?' )?> <?php gform_tooltip( 'gfcaptcha_use_wordlist' ) ?></label>
                        </th>
                        <td>
                            <select name="gf_gfcaptcha_use_wordlist" id="gf_gfcaptcha_use_wordlist">
                                <option value="1" <?php echo ($use_wordlist == '1') ? 'selected="selected"' : '';?>>Yes</option>
                                <option value="0" <?php echo ($use_wordlist !== '1') ? 'selected="selected"' : '';?>>No</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <h4><?php _e( 'Image Settings', 'gfcaptcha' ) ?></h4>
            <table class="form-table">
                <tbody>
                    <!--
                        Image settings
                        - Image Height
                        - Image Width
                        - Image BG Color
                        - Image Type
                    -->
                    <tr valign="top">
                        <th scope="row">
                            <label for ="gf_gfcaptcha_image_height" class="inline"><?php _e( 'Image Height' )?> <?php gform_tooltip( 'gfcaptcha_image_height' ) ?></label>
                        </th>
                        <td>
                            <input type="text" name="gf_gfcaptcha_image_height" id="gf_gfcaptcha_image_height" value="<?php echo ($image_height) ? $image_height : '80'; ?>" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for ="gf_gfcaptcha_image_width" class="inline"><?php _e( 'Image Width' )?> <?php gform_tooltip( 'gfcaptcha_image_width' ) ?></label>
                        </th>
                        <td>
                            <input type="text" name="gf_gfcaptcha_image_width" id="gf_gfcaptcha_image_width" value="<?php echo ($image_width) ? $image_width : '215'; ?>" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for ="gf_gfcaptcha_image_bg_color" class="inline"><?php _e( 'Background Color' )?> <?php gform_tooltip( 'gfcaptcha_image_bg_color' ) ?></label>
                        </th>
                        <td>
                            <input type="text" name="gf_gfcaptcha_image_bg_color" id="gf_gfcaptcha_image_bg_color" value="<?php echo ( $image_bg_color ) ? $image_bg_color : '#0099CC'; ?>" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for ="gf_gfcaptcha_image_type" class="inline"><?php _e( 'Image Type' )?> <?php gform_tooltip( 'gfcaptcha_image_type' ) ?></label>
                        </th>
                        <td>
                            <select name="gf_gfcaptcha_image_type" id="gf_gfcaptcha_image_type">
                                <option value="png" <?php echo ($image_type !== 'jpg' && $image_type !== 'gif') ? 'selected="selected"' : '';?>>PNG</option>
                                <option value="jpg" <?php echo ($image_type == 'jpg') ? 'selected="selected"' : '';?>>JPEG</option>
                                <option value="gif" <?php echo ($image_type == 'gif') ? 'selected="selected"' : '';?>>GIF</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <h4><?php _e( 'Text Settings', 'gfcaptcha' ) ?></h4>
            <table class="form-table">
                <tbody>
                    <!--
                        Text settings
                        - Text Color
                        - Use Transparent Text
                        - Text Transparency Percentage
                    -->
                    <tr valign="top">
                        <th scope="row">
                            <label for ="gf_gfcaptcha_text_color" class="inline"><?php _e( 'Text Color' )?> <?php gform_tooltip( 'gfcaptcha_text_color' ) ?></label>
                        </th>
                        <td>
                            <input type="text" name="gf_gfcaptcha_text_color" id="gf_gfcaptcha_text_color" value="<?php echo ( $text_color ) ? $text_color : '#EAEAEA'; ?>" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for ="gf_gfcaptcha_use_transparent_text" class="inline"><?php _e( 'Use Transparent Text?' )?> <?php gform_tooltip( 'gfcaptcha_use_transparent_text' ) ?></label>
                        </th>
                        <td>
                            <select name="gf_gfcaptcha_use_transparent_text" id="gf_gfcaptcha_use_transparent_text">
                                <option value="1" <?php echo ($use_transparent_text == '1') ? 'selected="selected"' : '';?>>Yes</option>
                                <option value="0" <?php echo ($use_transparent_text !== '1') ? 'selected="selected"' : '';?>>No</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for ="gf_gfcaptcha_text_transparency_percentage" class="inline"><?php _e( 'Text Transparency Percentage' )?> <?php gform_tooltip( 'gfcaptcha_text_transparency_percentage' ) ?></label>
                        </th>
                        <td>
                            <select name="gf_gfcaptcha_text_transparency_percentage" id="gf_gfcaptcha_text_transparency_percentage">
                            <?php
                                for ( $i = 100; $i >= 0; $i = $i - 5 ) {
                                    if ( $perturbation !== '' ) {
                                        $selected = ( $perturbation == $i ) ? ' selected="selected"' : '';
                                    }
                                    else {
                                        $selected = ( $i == 30 ) ? ' selected="selected"' : '';
                                    }
                            ?>
                                <option value="<?php echo $i; ?>"<?php echo $selected; ?>><?php echo $i.'%'; ?></option>
                            <?php
                                }
                            ?>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <h4><?php _e( 'Distortion Settings', 'gfcaptcha' ) ?></h4>
            <table class="form-table">
                <tbody>
                    <!--
                        Distortion settings
                        - Perturbation
                        - Num Lines
                        - Line Color
                        - Noise
                        - Noise Color
                    -->
                    <tr valign="top">
                        <th scope="row">
                            <label for ="gf_gfcaptcha_perturbation" class="inline"><?php _e( 'Level of Perturbation' )?> <?php gform_tooltip( 'gfcaptcha_perturbation' ) ?></label>
                        </th>
                        <td>
                            <select name="gf_gfcaptcha_perturbation" id="gf_gfcaptcha_perturbation">
                            <?php
                                for ( $i = 100; $i >= 0; $i = $i - 5 ) {
                                    if ( $perturbation !== '' ) {
                                        $selected = ( $perturbation == $i ) ? ' selected="selected"' : '';
                                    }
                                    else {
                                        $selected = ( $i == 75 ) ? ' selected="selected"' : '';
                                    }
                            ?>
                                <option value="<?php echo $i; ?>"<?php echo $selected; ?>><?php echo $i.'%'; ?></option>
                            <?php
                                }
                            ?>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for ="gf_gfcaptcha_num_lines" class="inline"><?php _e( 'Number of Lines' )?> <?php gform_tooltip( 'gfcaptcha_num_lines' ) ?></label>
                        </th>
                        <td>
                            <select name="gf_gfcaptcha_num_lines" id="gf_gfcaptcha_num_lines">
                            <?php
                                for ( $i = 0; $i <= 20; $i++ ) {
                                    if ( $num_lines ) {
                                        $selected = ($num_lines == $i) ? ' selected="selected"' : '';
                                    }
                                    else {
                                        $selected = ($i == 8) ? ' selected="selected"' : '';
                                    }
                            ?>
                                <option value="<?php echo $i; ?>"<?php echo $selected; ?>><?php echo $i; ?></option>
                            <?php
                                }
                            ?>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for ="gf_gfcaptcha_line_color" class="inline"><?php _e( 'Line Color' )?> <?php gform_tooltip( 'gfcaptcha_line_color' ) ?></label>
                        </th>
                        <td>
                            <input type="text" name="gf_gfcaptcha_line_color" id="gf_gfcaptcha_line_color" value="<?php echo ($line_color) ? $line_color : '#0000CC'; ?>" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for ="gf_gfcaptcha_noise_level" class="inline"><?php _e( 'Noise Level' )?> <?php gform_tooltip( 'gfcaptcha_noise_level' ) ?></label>
                        </th>
                        <td>
                            <select name="gf_gfcaptcha_noise_level" id="gf_gfcaptcha_noise_level">
                            <?php
                                for ( $i = 0; $i <= 10; $i++ ) {
                                    if ( $num_lines ) {
                                        $selected = ($noise_level == $i) ? ' selected="selected"' : '';
                                    }
                                    else {
                                        $selected = ($i == 0) ? ' selected="selected"' : '';
                                    }
                            ?>
                                <option value="<?php echo $i; ?>"<?php echo $selected; ?>><?php echo $i; ?></option>
                            <?php
                                }
                            ?>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for ="gf_gfcaptcha_noise_color" class="inline"><?php _e( 'Noise Color' )?> <?php gform_tooltip( 'gfcaptcha_noise_color' ) ?></label>
                        </th>
                        <td>
                            <input type="text" name="gf_gfcaptcha_noise_color" id="gf_gfcaptcha_noise_color" value="<?php echo ($noise_color) ? $noise_color : '#0000CC'; ?>" />
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php /* REMOVED DUE TO IT BREAKING THE CAPTCHA
            <h4><?php _e( 'Signature Settings', 'gfcaptcha' ) ?></h4>
            <table class="form-table">
                <tbody>
                    <!--
                        Signature settings
                        - Image Signature
                        - Signature Color
                    -->
                    <tr valign="top">
                        <th scope="row">
                            <label for ="gf_gfcaptcha_image_signature" class="inline"><?php _e( 'Image Signature' )?> <?php gform_tooltip( 'gfcaptcha_image_signature' ) ?></label>
                        </th>
                        <td>
                            <input type="text" name="gf_gfcaptcha_image_signature" id="gf_gfcaptcha_image_signature" value="<?php echo ( $image_signature ) ? $image_signature : ''; ?>" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for ="gf_gfcaptcha_signature_color" class="inline"><?php _e( 'Signature Color' )?> <?php gform_tooltip( 'gfcaptcha_signature_color' ) ?></label>
                        </th>
                        <td>
                            <input type="text" name="gf_gfcaptcha_signature_color" id="gf_gfcaptcha_signature_color" value="<?php echo ( $signature_color ) ? $signature_color : '#EAEAEA'; ?>" />
                        </td>
                    </tr>
                </tbody>
            </table>
            */ ?>
            <p class="submit" style="text-align: left;">
                <input type="button" value="Save" name="submit" id="submit" class="button-primary gf_settings_savebutton" onclick="confirm_settings()"/>
            </p>

            <script type="text/javascript">
                function confirm_settings(){
                	var gf_gfcaptcha_captcha_type = jQuery('#gf_gfcaptcha_captcha_type').val();
                	var gf_gfcaptcha_case_sensitive = jQuery('#gf_gfcaptcha_case_sensitive').val();
                	var gf_gfcaptcha_image_height = jQuery('#gf_gfcaptcha_image_height').val();
                	var gf_gfcaptcha_perturbation = jQuery('#gf_gfcaptcha_perturbation').val();
                	var gf_gfcaptcha_image_bg_color = jQuery('#gf_gfcaptcha_image_bg_color').val();
                	var gf_gfcaptcha_text_color = jQuery('#gf_gfcaptcha_text_color').val();
                	var gf_gfcaptcha_num_lines = jQuery('#gf_gfcaptcha_num_lines').val();
                	var gf_gfcaptcha_line_color = jQuery('#gf_gfcaptcha_line_color').val();
                	var gf_gfcaptcha_image_type = jQuery('#gf_gfcaptcha_image_type').val();

                	var gf_gfcaptcha_code_length = jQuery('#gf_gfcaptcha_code_length').val();
                	var gf_gfcaptcha_image_width = jQuery('#gf_gfcaptcha_image_width').val();
                	var gf_gfcaptcha_noise_level = jQuery('#gf_gfcaptcha_noise_level').val();
                	var gf_gfcaptcha_signature_color = jQuery('#gf_gfcaptcha_signature_color').val();
                	var gf_gfcaptcha_noise_color = jQuery('#gf_gfcaptcha_noise_color').val();
                	var gf_gfcaptcha_use_wordlist = jQuery('#gf_gfcaptcha_use_wordlist').val();
                	var gf_gfcaptcha_use_transparent_text = jQuery('#gf_gfcaptcha_use_transparent_text').val();
                	var gf_gfcaptcha_text_transparency_percentage = jQuery('#gf_gfcaptcha_text_transparency_percentage').val();
                	var gf_gfcaptcha_image_signature = jQuery('#gf_gfcaptcha_image_signature').val();

                    jQuery.post(ajaxurl, {
                        action:'gf_gfcaptcha_confirm_settings',
                        gfcaptcha_captcha_type: gf_gfcaptcha_captcha_type,
                        gfcaptcha_case_sensitive: gf_gfcaptcha_case_sensitive,
                        gfcaptcha_image_height: gf_gfcaptcha_image_height,
                        gfcaptcha_perturbation: gf_gfcaptcha_perturbation,
                        gfcaptcha_image_bg_color: gf_gfcaptcha_image_bg_color,
                        gfcaptcha_text_color: gf_gfcaptcha_text_color,
                        gfcaptcha_num_lines: gf_gfcaptcha_num_lines,
                        gfcaptcha_line_color: gf_gfcaptcha_line_color,
                        gfcaptcha_image_type: gf_gfcaptcha_image_type,
                        gfcaptcha_code_length: gf_gfcaptcha_code_length,
                    	gfcaptcha_image_width: gf_gfcaptcha_image_width,
                    	gfcaptcha_noise_level: gf_gfcaptcha_noise_level,
                    	gfcaptcha_signature_color: gf_gfcaptcha_signature_color,
                    	gfcaptcha_noise_color: gf_gfcaptcha_noise_color,
                    	gfcaptcha_use_wordlist: gf_gfcaptcha_use_wordlist,
                    	gfcaptcha_use_transparent_text: gf_gfcaptcha_use_transparent_text,
                    	gfcaptcha_text_transparency_percentage: gf_gfcaptcha_text_transparency_percentage,
                    	gfcaptcha_image_signature: gf_gfcaptcha_image_signature,
                        cookie: encodeURIComponent(document.cookie)});
                }
            </script>


        </form>

        <form action="" method="post">
            <?php wp_nonce_field( 'uninstall', 'gf_gfcaptcha_uninstall' ) ?>
            <?php
                if ( GFCommon::current_user_can_any( 'gravityforms_gfcaptcha_uninstall' ) ) {
            ?>
                <div class="hr-divider"></div>

                <h3><?php _e( 'Uninstall GFCaptcha Add-On', 'gfcaptcha' ) ?></h3>
                <div class="delete-alert"><?php _e( 'Warning! This operation deletes ALL GFCaptcha Feeds.', 'gfcaptcha' ) ?>
                    <?php
                    $uninstall_button = '<br /><br /><input type="submit" name="uninstall" value="' . __( 'Uninstall GFCaptcha Add-On', 'gfcaptcha' ) . '" class="button" onclick="return confirm(\'' . __( 'Warning! ALL GFCaptcha Feeds will be deleted. This cannot be undone. "OK" to delete, "Cancel" to stop', 'gfcaptcha' ) . '\' );" />';
                    echo apply_filters( 'gform_gfcaptcha_uninstall_button', $uninstall_button );
                    ?>
                </div>
            <?php } ?>
        </form>
        <?php
    }


    public static function uninstall() {
        if ( !current_user_can( 'administrator' ) )
            die( __( "You don't have adequate permission to uninstall the GFCaptcha Add-On.", 'gfcaptcha' ) );

        //Deactivating plugin
        $plugin = 'gfcaptcha/gfcaptcha.php';
        deactivate_plugins( $plugin );
        update_option( 'recently_activated', array( $plugin => time() ) + (array) get_option( 'recently_activated' ) );
    }

    private static function is_gravityforms_installed() {
        return class_exists( 'RGForms' );
    }

    private static function is_gravityforms_supported() {
        if ( class_exists( 'GFCommon' ) ) {
            $is_correct_version = version_compare( GFCommon::$version, self::$min_gravityforms_version, '>=' );
            return $is_correct_version;
        }
        else {
            return false;
        }
    }

    private static function is_gfcaptcha_page() {
        $current_page = trim( strtolower( RGForms::get( 'page' ) ) );
        return in_array( $current_page, array( 'gf_gfcaptcha' ) );
    }

    //Returns the url of the plugin's root folder
    protected function get_base_url(){
        return plugins_url( null, __FILE__ );
    }

    //Returns the physical path of the plugin's root folder
    protected function get_base_path(){
        $folder = basename( dirname( __FILE__ ) );
        return WP_PLUGIN_DIR . '/' . $folder;
    }

    public static function validate_captcha( $confirmation, $form, $entry, $ajax )
    {
        // ignore requests that are not the current form's submissions
        if(RGForms::post( 'gform_submit' ) != $form['id'])
            return $confirmation;

        // Validate Captchas
        foreach ( $form['fields'] as $id => $field ) {
            if ( $field['type'] == 'gfcaptcha' ) {
                $id = $field['id'];

                require_once(self::get_base_path() . '/includes/securimage.php');
                $securimage = new Securimage();

                // Get field containing catcha code
                $code = $entry[$id];

                if ( $securimage->check( $code ) == false ) {
                    $confirmation = 'The Code Entered was Incorrect, please try again';
                    return self::build_conf( $confirmation );
                }
            }
        }

        return $confirmation;
    }

    public static function build_conf( $text, $type = 'error' )
    {
        switch ( $type ) {
            case 'error':
                $message = '<div class="validation_error">'.$text.'</div>';
                break;
            case 'warning':
                $message = '<div class="warning">'.$text.'</div>';
                break;
            default:
            case 'ok':
                $message = '<div>'.$text.'</div>';
                break;
        }

        return $message;
    }
}

if ( !function_exists( 'rgget' ) ) {
    function rgget( $name, $array = null ) {
        if( !isset( $array ) )
            $array = $_GET;

        if(isset($array[$name]))
            return $array[$name];

        return '';
    }
}

if ( !function_exists( 'rgpost' ) ) {
    function rgpost( $name, $do_stripslashes = true ) {
        if( isset($_POST[$name]) )
            return $do_stripslashes ? stripslashes_deep( $_POST[$name] ) : $_POST[$name];

        return '';
    }
}