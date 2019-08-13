<?php
/*
Plugin Name: Gain Advisory Group Functions
Plugin URI: https://www.gainadvisorygroup.com/
Description: Gain Advisory Group functions
Version: 1.0
Author: Franklin Web Design
Author URI: http://www.webdesignfranklintn.com/
*/

/* Enqueue plugin scripts */

wp_enqueue_script( 'gag_custom_script', plugin_dir_url( __FILE__ ) . 'scripts.js', array( 'jquery' , 'jquery-ui', 'load-more' ), time(),true);

wp_enqueue_script( 'jquery-ui', plugin_dir_url( __FILE__ ) . 'jquery-ui/jquery-ui.js', array( 'jquery' ), time(),true);

wp_enqueue_script( 'load-more', plugin_dir_url( __FILE__ ) . 'loadmore/js/loadMoreResults.js', array( 'jquery' ), time(),true);

wp_enqueue_style( 'jquery-ui', plugin_dir_url( __FILE__ ) . 'jquery-ui/jquery-ui.css');

wp_enqueue_style( 'gag-functions-style', plugin_dir_url( __FILE__ ) . 'style.css');


/* Front end profile editor */

class basic_user_avatars {
    /**
     * User ID
     *
     * @since 1.0.0
     * @var int
     */
    private $user_id_being_edited;
    /**
     * Initialize all the things
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Text domain
        $this->load_textdomain();
        // Actions
        add_action( 'admin_init',                array( $this, 'admin_init'               )        );
        add_action( 'show_user_profile',         array( $this, 'edit_user_profile'        )        );
        add_action( 'edit_user_profile',         array( $this, 'edit_user_profile'        )        );
        add_action( 'personal_options_update',   array( $this, 'edit_user_profile_update' )        );
        add_action( 'edit_user_profile_update',  array( $this, 'edit_user_profile_update' )        );
        add_action( 'bbp_user_edit_after_about', array( $this, 'bbpress_user_profile'     )        );
        // Shortcode
        add_shortcode( 'gag-profile-form',     array( $this, 'shortcode'                )        );
        // Filters
        add_filter( 'get_avatar',                array( $this, 'get_avatar'               ), 10, 5 );
        add_filter( 'avatar_defaults',           array( $this, 'avatar_defaults'          )        );
    }
    /**
     * Loads the plugin language files.
     *
     * @since 1.0.1
     */
    public function load_textdomain() {
        $domain = 'basic-user-avatars';
        $locale = apply_filters( 'plugin_locale', get_locale(), $domain );
        load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
        load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }
    /**
     * Start the admin engine.
     *
     * @since 1.0.0
     */
    public function admin_init() {
        // Register/add the Discussion setting to restrict avatar upload capabilites
        register_setting( 'discussion', 'basic_user_avatars_caps', array( $this, 'sanitize_options' ) );
        add_settings_field( 'basic-user-avatars-caps', __( 'Local Avatar Permissions', 'basic-user-avatars' ), array( $this, 'avatar_settings_field' ), 'discussion', 'avatars' );
    }
    /**
     * Discussion settings option
     *
     * @since 1.0.0
     * @param array $args [description]
     */
    public function avatar_settings_field( $args ) {
        $options = get_option( 'basic_user_avatars_caps' );
        ?>
        <label for="basic_user_avatars_caps">
            <input type="checkbox" name="basic_user_avatars_caps" id="basic_user_avatars_caps" value="1" <?php checked( $options['basic_user_avatars_caps'], 1 ); ?>/>
            <?php _e( 'Only allow users with file upload capabilities to upload local avatars (Authors and above)', 'basic-user-avatars' ); ?>
        </label>
        <?php
    }
    /**
     * Sanitize the Discussion settings option
     *
     * @since 1.0.0
     * @param array $input
     * @return array
     */
    public function sanitize_options( $input ) {
        $new_input['basic_user_avatars_caps'] = empty( $input['basic_user_avatars_caps'] ) ? 0 : 1;
        return $new_input;
    }
    /**
     * Filter the avatar WordPress returns
     *
     * @since 1.0.0
     * @param string $avatar
     * @param int/string/object $id_or_email
     * @param int $size
     * @param string $default
     * @param boolean $alt
     * @return string
     */
    public function get_avatar( $avatar = '', $id_or_email, $size = 96, $default = '', $alt = false ) {
        // Determine if we recive an ID or string
        if ( is_numeric( $id_or_email ) )
            $user_id = (int) $id_or_email;
        elseif ( is_string( $id_or_email ) && ( $user = get_user_by( 'email', $id_or_email ) ) )
            $user_id = $user->ID;
        elseif ( is_object( $id_or_email ) && ! empty( $id_or_email->user_id ) )
            $user_id = (int) $id_or_email->user_id;
        if ( empty( $user_id ) )
            return $avatar;
        $local_avatars = get_user_meta( $user_id, 'basic_user_avatar', true );
        if ( empty( $local_avatars ) || empty( $local_avatars['full'] ) )
            return $avatar;
        $size = (int) $size;
        if ( empty( $alt ) ) {
            $first_name = get_the_author_meta( 'first_name',$user_id);
            $last_name  = get_the_author_meta( 'last_name',$user_id);
            $alt        = $first_name . " " . $last_name;
        }
        // Generate a new size
        if ( empty( $local_avatars[$size] ) ) {
            $upload_path      = wp_upload_dir();
            $avatar_full_path = str_replace( $upload_path['baseurl'], $upload_path['basedir'], $local_avatars['full'] );
            $image            = wp_get_image_editor( $avatar_full_path );
            $image_sized      = null;
            if ( ! is_wp_error( $image ) ) {
                $image->resize( $size, $size, true );
                $image_sized = $image->save();
            }
            // Deal with original being >= to original image (or lack of sizing ability).
            if ( empty( $image_sized ) || is_wp_error( $image_sized ) ) {
                $local_avatars[ $size ] = $local_avatars['full'];
            } else {
                $local_avatars[ $size ] = str_replace( $upload_path['basedir'], $upload_path['baseurl'], $image_sized['path'] );
            }
            // Save updated avatar sizes
            update_user_meta( $user_id, 'basic_user_avatar', $local_avatars );
        } elseif ( substr( $local_avatars[$size], 0, 4 ) != 'http' ) {
            $local_avatars[$size] = home_url( $local_avatars[$size] );
        }
        if ( is_ssl() ) {
            //$local_avatars[ $size ] = str_replace( 'http', 'https', $local_avatars[ $size ] );
        }
        $author_class = is_author( $user_id ) ? ' current-author' : '' ;
        $avatar       = "<img alt='" . esc_attr( $alt ) . "' src='" . $local_avatars[$size] . "' class='avatar avatar-{$size}{$author_class} photo' height='{$size}' width='{$size}' />";
        return apply_filters( 'basic_user_avatar', $avatar, $user_id );
    }
    /**
     * Form to display on the user profile edit screen
     *
     * @since 1.0.0
     * @param object $profileuser
     * @return
     */
    public function edit_user_profile( $profileuser ) {
        // bbPress will try to auto-add this to user profiles - don't let it.
        // Instead we hook our own proper function that displays cleaner.
        if ( function_exists( 'is_bbpress') && is_bbpress() )
            return;
        ?>

        <h3><?php _e( 'Avatar', 'basic-user-avatars' ); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="basic-user-avatar"><?php _e( 'Upload Avatar', 'basic-user-avatars' ); ?></label></th>
                <td style="width: 50px;" valign="top">
                    <?php echo get_avatar( $profileuser->ID ); ?>
                </td>
                <td>
                    <?php
                    $options = get_option( 'basic_user_avatars_caps' );
                    if ( empty( $options['basic_user_avatars_caps'] ) || current_user_can( 'upload_files' ) ) {
                        // Nonce security ftw
                        wp_nonce_field( 'basic_user_avatar_nonce', '_basic_user_avatar_nonce', false );

                        // File upload input
                        echo '<input type="file" name="basic-user-avatar" id="basic-local-avatar" /><br />';
                        if ( empty( $profileuser->basic_user_avatar ) ) {
                            echo '<span class="description">' . __( 'No local avatar is set. Use the upload field to add a local avatar.', 'basic-user-avatars' ) . '</span>';
                        } else {
                            echo '<input type="checkbox" name="basic-user-avatar-erase" value="1" /> ' . __( 'Delete local avatar', 'basic-user-avatars' ) . '<br />';
                            echo '<span class="description">' . __( 'Replace the local avatar by uploading a new avatar, or erase the local avatar (falling back to a gravatar) by checking the delete option.', 'basic-user-avatars' ) . '</span>';
                        }
                    } else {
                        if ( empty( $profileuser->basic_user_avatar ) ) {
                            echo '<span class="description">' . __( 'No local avatar is set. Set up your avatar at Gravatar.com.', 'basic-user-avatars' ) . '</span>';
                        } else {
                            echo '<span class="description">' . __( 'You do not have media management permissions. To change your local avatar, contact the site administrator.', 'basic-user-avatars' ) . '</span>';
                        }
                    }
                    ?>
                </td>
            </tr>
        </table>
        <script type="text/javascript">var form = document.getElementById('your-profile');form.encoding = 'multipart/form-data';form.setAttribute('enctype', 'multipart/form-data');</script>
        <?php
    }
    /**
     * Update the user's avatar setting
     *
     * @since 1.0.0
     * @param int $user_id
     */
    public function edit_user_profile_update( $user_id ) {
        // Check for nonce otherwise bail
        if ( ! isset( $_POST['_basic_user_avatar_nonce'] ) || ! wp_verify_nonce( $_POST['_basic_user_avatar_nonce'], 'basic_user_avatar_nonce' ) )
            return;
        if ( ! empty( $_FILES['basic-user-avatar']['name'] ) ) {
            // Allowed file extensions/types
            $mimes = array(
                'jpg|jpeg|jpe' => 'image/jpeg',
                'gif'          => 'image/gif',
                'png'          => 'image/png',
            );
            // Front end support - shortcode, bbPress, etc
            if ( ! function_exists( 'wp_handle_upload' ) )
                require_once ABSPATH . 'wp-admin/includes/file.php';
            // Delete old images if successful
            $this->avatar_delete( $user_id );
            // Need to be more secure since low privelege users can upload
            if ( strstr( $_FILES['basic-user-avatar']['name'], '.php' ) )
                wp_die( 'For security reasons, the extension ".php" cannot be in your file name.' );
            // Make user_id known to unique_filename_callback function
            $this->user_id_being_edited = $user_id;
            $avatar = wp_handle_upload( $_FILES['basic-user-avatar'], array( 'mimes' => $mimes, 'test_form' => false, 'unique_filename_callback' => array( $this, 'unique_filename_callback' ) ) );
            // Handle failures
            if ( empty( $avatar['file'] ) ) {
                switch ( $avatar['error'] ) {
                    case 'File type does not meet security guidelines. Try another.' :
                        add_action( 'user_profile_update_errors', create_function( '$a', '$a->add("avatar_error",__("Please upload a valid image file for the avatar.","basic-user-avatars"));' ) );
                        break;
                    default :
                        add_action( 'user_profile_update_errors', create_function( '$a', '$a->add("avatar_error","<strong>".__("There was an error uploading the avatar:","basic-user-avatars")."</strong> ' . esc_attr( $avatar['error'] ) . '");' ) );
                }
                return;
            }
            // Save user information (overwriting previous)
            update_user_meta( $user_id, 'basic_user_avatar', array( 'full' => $avatar['url'] ) );
        } elseif ( ! empty( $_POST['basic-user-avatar-erase'] ) ) {
            // Nuke the current avatar
            $this->avatar_delete( $user_id );
        }


        if (isset($_POST['first_name'])) {
            update_user_meta( $user_id, 'first_name', sanitize_text_field($_POST['first_name']) );
        }
        if (isset($_POST['last_name'])) {
            update_user_meta( $user_id, 'last_name', sanitize_text_field($_POST['last_name']) );
        }

        if ( isset($_POST['first_name']) && isset($_POST['last_name']) ) {
            wp_update_user(
                array (
                    'ID' => $user_id,
                    'display_name' => sanitize_text_field($_POST['first_name']) . ' ' . sanitize_text_field($_POST['last_name'])
                )
            );
        }

        if (isset($_POST['member_email'])) {
            update_user_meta( $user_id, 'email', sanitize_text_field($_POST['member_email']) );
        }

        if (isset($_POST['member_company'])) {
            update_user_meta( $user_id, 'member_company', sanitize_text_field($_POST['member_company']) );
        }

        if (isset($_POST['last4ssn'])) {
            update_user_meta( $user_id, 'last4ssn', sanitize_text_field($_POST['last4ssn']) );
        }
        if (isset($_POST['metrocity'])) {
            update_user_meta( $user_id, 'metrocity', sanitize_text_field($_POST['metrocity']) );
        }
        if (isset($_POST['billing_city'])) {
            update_user_meta( $user_id, 'billing_city', sanitize_text_field($_POST['billing_city']) );
        }
        if (isset($_POST['billing_postcode'])) {
            update_user_meta( $user_id, 'billing_postcode', sanitize_text_field($_POST['billing_postcode']) );
        }
        if (isset($_POST['billing_state'])) {
            update_user_meta( $user_id, 'billing_state', sanitize_text_field($_POST['billing_state']) );
        }
        if (isset($_POST['jobtitle'])) {
            update_user_meta( $user_id, 'jobtitle', sanitize_text_field($_POST['jobtitle']) );
        }
        if (isset($_POST['industry1'])) {
            update_user_meta( $user_id, 'industry1', sanitize_text_field($_POST['industry1']) );
        }
        if (isset($_POST['industry2'])) {
            update_user_meta( $user_id, 'industry2', sanitize_text_field($_POST['industry2']) );
        }
        if (isset($_POST['industry3'])) {
            update_user_meta( $user_id, 'industry3', sanitize_text_field($_POST['industry3']) );
        }
        if (isset($_POST['industry4'])) {
            update_user_meta( $user_id, 'industry4', sanitize_text_field($_POST['industry4']) );
        }
        if (isset($_POST['yearexp1'])) {
            update_user_meta( $user_id, 'yearexp1', sanitize_text_field($_POST['yearexp1']) );
        }
        if (isset($_POST['yearexp2'])) {
            update_user_meta( $user_id, 'yearexp2', sanitize_text_field($_POST['yearexp2']) );
        }
        if (isset($_POST['yearexp3'])) {
            update_user_meta( $user_id, 'yearexp3', sanitize_text_field($_POST['yearexp3']) );
        }
        if (isset($_POST['yearexp4'])) {
            update_user_meta( $user_id, 'yearexp4', sanitize_text_field($_POST['yearexp4']) );
        }
        if (isset($_POST['intro'])) {
            update_user_meta( $user_id, 'intro', sanitize_text_field($_POST['intro']) );
        }
        if (isset($_POST['description'])) {
            update_user_meta( $user_id, 'description', implode( "\n", array_map( 'sanitize_text_field', explode( "\n", $_POST['description']) ) ) );
        }
        if (isset($_POST['skills'])) {
            update_user_meta( $user_id, 'skills', implode( "\n", array_map( 'sanitize_text_field', explode( "\n", $_POST['skills']) ) ) );
        }
        if (isset($_POST['linkedin'])) {
            update_user_meta( $user_id, 'linkedin', sanitize_text_field($_POST['linkedin']) );
        }
        if (isset($_POST['phonenum'])) {
            update_user_meta( $user_id, 'phonenum', sanitize_text_field($_POST['phonenum']) );
        }
        if (isset($_POST['willingtravel'])) {
            update_user_meta( $user_id, 'willingtravel', sanitize_text_field($_POST['willingtravel']) );
        }
        if (isset($_POST['willingrelocate'])) {
            update_user_meta( $user_id, 'willingrelocate', sanitize_text_field($_POST['willingrelocate']) );
        }
        if (isset($_POST['hourlyrate'])) {
            update_user_meta( $user_id, 'hourlyrate', sanitize_text_field($_POST['hourlyrate']) );
        }
        if (isset($_POST['hearfrom'])) {
            update_user_meta( $user_id, 'hear_from', sanitize_text_field($_POST['hearfrom']) );
        }


        if (!empty($_POST['member_profile_url'])) {
            $view_profile_url = $_POST['member_profile_url'];
            echo '<script>document.location.href="'.$view_profile_url.'";</script>';
            exit;
        }


    }
    /**
     * Enable avatar management on the frontend via this shortcode.
     *
     * @since 1.0.0
     */
    function shortcode() {
        // Don't bother if the user isn't logged in
        if ( ! is_user_logged_in() )
            return;
        $user_id     = get_current_user_id();
        $profileuser = get_userdata( $user_id );

        if ( isset( $_POST['manage_avatar_submit'] ) ){
            $this->edit_user_profile_update( $user_id );
        }
        ob_start();

        $member_code = get_the_author_meta( 'member_code', $user_id );
        $user_login = get_the_author_meta( 'user_login', $user_id );
        $first_name = get_the_author_meta( 'first_name', $user_id );
        $last_name = get_the_author_meta( 'last_name', $user_id );
        $member_email = get_the_author_meta( 'email', $user_id );

        $company = get_the_author_meta( 'member_company', $user_id );

        $metrocity = get_the_author_meta( 'metrocity', $user_id );
        $city = get_the_author_meta( 'billing_city', $user_id );
        $state = get_the_author_meta( 'billing_state', $user_id );
        $postcode = get_the_author_meta( 'billing_postcode', $user_id );
        $jobtitle = get_the_author_meta( 'jobtitle', $user_id );
        $industry1 = get_the_author_meta( 'industry1', $user_id );
        $industry2 = get_the_author_meta( 'industry2', $user_id );
        $industry3 = get_the_author_meta( 'industry3', $user_id );
        $industry4 = get_the_author_meta( 'industry4', $user_id );
        $yearexp1 = get_the_author_meta( 'yearexp1', $user_id );
        $yearexp2 = get_the_author_meta( 'yearexp2', $user_id );
        $yearexp3 = get_the_author_meta( 'yearexp3', $user_id );
        $yearexp4 = get_the_author_meta( 'yearexp4', $user_id );
        $intro = get_the_author_meta( 'intro', $user_id );
        $bio = get_the_author_meta( 'description', $user_id );
        $skills = get_the_author_meta( 'skills', $user_id );
        $linkedin = get_the_author_meta( 'linkedin', $user_id );
        $phonenum = get_the_author_meta( 'phonenum', $user_id );
        $willingtravel = get_the_author_meta( 'willingtravel', $user_id );
        $willingrelocate = get_the_author_meta( 'willingrelocate', $user_id );
        $hourlyrate = get_the_author_meta( 'hourlyrate', $user_id );
        $member_page_id = get_the_author_meta( 'member_page', $user_id );
        $hear_from = get_the_author_meta( 'hear_from', $user_id );

        $pms_member = pms_get_member( $user_id );
        $pms_member_sub = pms_get_member_subscriptions( array('user_id' => $user_id) );

//        echo "<pre>";
//        print_r($pms_member_sub);
//        echo "</pre>";

        $subscription_id = $pms_member_sub[0]->id;

        if ($pms_member) {
            $start_date = strtotime($pms_member->subscriptions[0]["start_date"]);
            $start_date = date('m/d/Y',$start_date);
            if ($pms_member->subscriptions[0]["status"] == 'active') {
                $end_date = "Active";
            } else {
                $end_date = strtotime($pms_member->subscriptions[0]["expiration_date"]);
                $end_date = date('m/d/Y',$end_date);
            }
        }

        ?>

        <form id="basic-user-avatar-form" action="<?php the_permalink(); ?>" method="post" enctype="multipart/form-data">

            <h3>Personal Details</h3>
            <p>
                <?php _e( 'Member Number (Used for Verification Letter search)', 'corporate-pro' ); ?>: <div class="member-code"><?php echo $member_code; ?></div>
            </p>
            <p>
                <label>
                    <?php _e( 'Username', 'corporate-pro' ); ?>
                    <input type="text" disabled value="<?php echo $user_login; ?>">
                </label>

                <label>
                    <?php _e( 'First Name', 'corporate-pro' ); ?>
                    <input type="text" name="first_name" value="<?php echo $first_name; ?>">
                </label>

                <label>
                    <?php _e( 'Last Name', 'corporate-pro' ); ?>
                    <input type="text" name="last_name" value="<?php echo $last_name; ?>">
                </label>

                <label><?php _e( 'Job Title ( As it will appear on Verification Letter ) ', 'corporate-pro' ); ?>
                    <input type="text" name="jobtitle" value="<?php echo $jobtitle; ?>">
                </label>
            </p>

            <?php
            echo get_avatar( $profileuser->ID );
            $options = get_option( 'basic_user_avatars_caps' );
            if ( empty( $options['basic_user_avatars_caps'] ) || current_user_can( 'upload_files' ) ) {
                // Nonce security ftw
                wp_nonce_field( 'basic_user_avatar_nonce', '_basic_user_avatar_nonce', false );

                // File upload input
                echo '<p><input type="file" name="basic-user-avatar" id="basic-local-avatar" /></p>';
                /*if ( empty( $profileuser->basic_user_avatar ) ) {
                    echo '<p class="description">' . __( 'No local avatar is set. Use the upload field to add a local avatar.', 'basic-user-avatars' ) . '</p>';
                } else {
                    echo '<input type="checkbox" name="basic-user-avatar-erase" value="1" /> ' . __( 'Delete local avatar', 'basic-user-avatars' ) . '<br />';
                    echo '<p class="description">' . __( 'Replace the local avatar by uploading a new avatar, or erase the local avatar (falling back to a gravatar) by checking the delete option.', 'basic-user-avatars' ) . '</p>';
                }*/
            } else {
                /*if ( empty( $profileuser->basic_user_avatar ) ) {
                    echo '<p class="description">' . __( 'No local avatar is set. Set up your avatar at Gravatar.com.', 'basic-user-avatars' ) . '</p>';
                } else {
                    echo '<p class="description">' . __( 'You do not have media management permissions. To change your local avatar, contact the site administrator.', 'basic-user-avatars' ) . '</p>';
                }*/
            }

            ?>

            <!-- Change Password Form -->
            <p>
                <!--<a id="password_toggle" style="text-decoration: underline; cursor: pointer;"></a>-->
                <button id="password_toggle"><?php _e( 'Change password', 'corporate-pro' ); ?></button>
            </p>
            <div id="password_change" style="display: none;">
                <fieldset data-minlength="<?php echo get_option( 'gag_pw_length' ); ?>" data-url="<?php echo admin_url('admin-ajax.php'); ?>" id="gag_change_password" class="change-password-content" style="border: 0; padding: 0; margin: 0; position: relative;">
                    <div class="change-password-overlay" style="display: none; position: absolute; top: 0; left: 0; bottom: 0; width: 100%; background-color: rgba(255,255,255,0.75);"></div>
                    <div class = "change-password-messages"></div>
                    <div class = "change-password-form">
                        <p>
                            <label for="password"><?php _e('New Password'); ?></label><br />
                            <input type="password" class="form-control password1" id="pass_1"/>

                            <label for="password2"><?php _e('Re-enter New Password'); ?></label><br />
                            <input type="password" class="form-control password2" id="pass_2"/>

                            <button id="change_password_btn" name="change-password"><?php _e('Save password', 'corporate-pro'); ?></button>
                        </p>
                    </div>
                </fieldset>
            </div>


            <?php if (user_can($user_id,'employer')) { ?>

                <h3>Employer Profile Fields</h3>
                <p class='form-error'>
                <?php if (empty($company)) echo "Please fill the Company Name field to get access to the Consultant's directory."; ?>
                </p>
                <p>
                    <label>
                        <?php _e( 'Company Name', 'corporate-pro' ); ?>
                        <input type="text" name="member_company" value="<?php echo $company; ?>" required>
                    </label>
                </p>

            <?php } ?>

            <?php if (user_can($user_id,'consultant')) { ?>

                <h3>Location Information</h3>
                <p>
                    <label><?php _e( 'Metro Complex', 'corporate-pro' ); ?>
                        <select class="gag-profile-select" name="metrocity" data-prevoption="<?php echo $metrocity; ?>">
                            <option></option>
                            <?php echo gag_get_option_list('gag_metro_list');?>
                        </select>
                    </label>
                    <label><?php _e( 'Your City', 'corporate-pro' ); ?>
                        <input type="text" name="billing_city" value="<?php echo $city; ?>">
                    </label>
                    <label><?php _e( 'State', 'corporate-pro' ); ?>
                        <select class="gag-profile-select" data-prevoption="<?php echo $state; ?>" name="billing_state">
                            <option></option>
                            <?php echo gag_get_option_list('gag_states_list');?>
                        </select>
                    </label>
                    <label><?php _e( 'ZIP Code', 'corporate-pro' ); ?>
                        <input type="text" maxlength="5" name="billing_postcode" value="<?php echo $postcode; ?>">
                    </label>
                </p>


                <h3>Experience & Skills</h3>
                <p>
                    <label><?php _e( 'Introduction', 'corporate-pro' ); ?>
                        <input type="text" name="intro" value="<?php echo $intro; ?>">
                    </label>

                    <label><?php _e( 'Biography', 'corporate-pro' ); ?>
                        <textarea name="description"><?php echo $bio;?></textarea>
                        <?php //wp_editor($bio,'bio-editor',array('textarea_name' => 'description')); ?>
                    </label>

                    <label><?php _e( 'Skills (Use comma or semi-colon to separate items in list)', 'corporate-pro' ); ?>
                        <textarea name="skills"><?php echo $skills; ?></textarea>
                    </label>

                    <label><?php _e( 'Hourly rate', 'corporate-pro' ); ?>
                        <select class="gag-profile-select" data-prevoption="<?php echo $hourlyrate; ?>" name="hourlyrate">
                            <option></option>
                            <?php echo gag_get_option_list('gag_hrange_list');?>
                        </select>
                    </label>
                </p>
                <p>
                    <label><?php _e( 'Willing to travel', 'corporate-pro' ); ?></label><br/>
                    <label><input type="radio" name="willingtravel" value="yes" <?php if ($willingtravel == 'yes') echo 'checked'; ?>/> Yes&nbsp;&nbsp;&nbsp;</label>
                    <label><input type="radio" name="willingtravel" value="no" <?php if ($willingtravel == 'no') echo 'checked'; ?>/> No</label>
                </p>
                <p>
                    <label><?php _e( 'Willing to relocate', 'corporate-pro' ); ?></label><br/>
                    <label><input type="radio" name="willingrelocate" value="yes" <?php if ($willingrelocate == 'yes') echo 'checked'; ?>/> Yes&nbsp;&nbsp;&nbsp;</label>
                    <label><input type="radio" name="willingrelocate" value="no" <?php if ($willingrelocate == 'no') echo 'checked'; ?>/> No</label>
                </p>
                <p>
                    <label style="width: 45%; display: inline-block;"><?php _e( 'Industry', 'corporate-pro' ); ?>
                        <select class="gag-profile-select" data-prevoption="<?php echo $industry1; ?>" name="industry1">
                            <option></option>
                            <?php echo gag_get_option_list('gag_industries_list');?>
                        </select>
                    </label>
                    <label style="width: 45%; display: inline-block;"><?php _e( 'Years of experience', 'corporate-pro' ); ?>
                        <select class="gag-profile-select" data-prevoption="<?php echo $yearexp1; ?>" name="yearexp1">
                            <option></option>
                            <?php echo gag_get_option_list('gag_exprange_list');?>
                        </select>
                    </label>

                    <label style="width: 45%; display: inline-block;"><?php _e( 'Industry', 'corporate-pro' ); ?>
                        <select class="gag-profile-select" data-prevoption="<?php echo $industry2; ?>" name="industry2">
                            <option></option>
                            <?php echo gag_get_option_list('gag_industries_list');?>
                        </select>
                    </label>
                    <label style="width: 45%; display: inline-block;"><?php _e( 'Years of experience', 'corporate-pro' ); ?>
                        <select class="gag-profile-select" data-prevoption="<?php echo $yearexp2; ?>" name="yearexp2">
                            <option></option>
                            <?php echo gag_get_option_list('gag_exprange_list');?>
                        </select>
                    </label>

                    <label style="width: 45%; display: inline-block;"><?php _e( 'Industry', 'corporate-pro' ); ?>
                        <select class="gag-profile-select" data-prevoption="<?php echo $industry3; ?>" name="industry3">
                            <option></option>
                            <?php echo gag_get_option_list('gag_industries_list');?>
                        </select>
                    </label>
                    <label style="width: 45%; display: inline-block;"><?php _e( 'Years of experience', 'corporate-pro' ); ?>
                        <select class="gag-profile-select" data-prevoption="<?php echo $yearexp3; ?>" name="yearexp3">
                            <option></option>
                            <?php echo gag_get_option_list('gag_exprange_list');?>
                        </select>
                    </label>

                    <label style="width: 45%; display: inline-block;"><?php _e( 'Industry', 'corporate-pro' ); ?>
                        <select class="gag-profile-select" data-prevoption="<?php echo $industry4; ?>" name="industry4">
                            <option></option>
                            <?php echo gag_get_option_list('gag_industries_list');?>
                        </select>
                    </label>
                    <label style="width: 45%; display: inline-block;"><?php _e( 'Years of experience', 'corporate-pro' ); ?>
                        <select class="gag-profile-select" data-prevoption="<?php echo $yearexp4; ?>" name="yearexp4">
                            <option></option>
                            <?php echo gag_get_option_list('gag_exprange_list');?>
                        </select>
                    </label>
                </p>

                <h3>Contact Information</h3>
                <p>
                    <label><?php _e( 'E-Mail Address', 'corporate-pro' ); ?>
                        <input type="text" name="member_email" value="<?php echo $member_email; ?>">
                    </label>

                    <label><?php _e( 'Phone number', 'corporate-pro' ); ?>
                        <input type="text" name="phonenum" value="<?php echo $phonenum; ?>">
                    </label>

                    <label><?php _e( 'LinkedIn', 'corporate-pro' ); ?>
                        <input type="text" name="linkedin" value="<?php echo $linkedin; ?>">
                    </label>

                    <label style="width: 45%; display: inline-block;"><?php _e( 'How did you hear of us?', 'corporate-pro' ); ?>
                        <select class="gag-profile-select" data-prevoption="<?php echo $hear_from; ?>" name="hearfrom">
                            <option></option>
                            <?php echo gag_get_option_list('gag_howhear_list');?>
                        </select>
                    </label>

                </p>

                <?php if (isset($start_date)) { ?>
                    <p>
                        <?php _e( 'Start date', 'corporate-pro' ); ?>: <?php echo $start_date; ?><br/>
                        <small>To change start date, because you did not register on your first day, please contact <a href="mailto:Profile@GainAdvisoryGroup.com">Profile@GainAdvisoryGroup.com</a></small>
                    </p>
                <?php } ?>

                <?php if (isset($end_date)) { ?>
                    <p>
                        <?php _e( 'End date', 'corporate-pro' ); ?>: <?php echo $end_date; ?><br/>
                        <small>If your end date is incorrect, please contact <a href="mailto:Profile@GainAdvisoryGroup.com">Profile@GainAdvisoryGroup.com</a></small>

                    </p>
                <?php } ?>


            <?php } ?>

            <p>
                <input style="margin-bottom:0" type="submit" name="manage_avatar_submit" value="<?php _e( 'Save Profile', 'basic-user-avatars' ); ?>" />
            </p>

            <!--<input type="hidden" name="member_profile_url" value="--><?php //echo get_the_permalink($member_page_id); ?><!--"/>-->

        </form>

        <p>
            <a class="button" class="btn" href="<?php echo get_the_permalink($member_page_id); ?>">View Profile</a>
        </p>

        <p>
            <a class="button" target="_blank" class="btn" href="<?php site_url(); ?>/verification-letter?vl_num=<?php echo $user_id; ?>">Verification letter</a>
        </p>

        <p>
            <a class="button" href="<?php echo site_url(); ?>/manage-subscriptions/?subscription_id=<?php echo $subscription_id; ?>">Manage Membership</a>
        </p>

        <p>
            <a class="button" href="<?php echo wp_logout_url(site_url()); ?>">Logout</a>
        </p>


        <?php
        return ob_get_clean();
    }
    /**
     * Remove the custom get_avatar hook for the default avatar list output on
     * the Discussion Settings page.
     *
     * @since 1.0.0
     * @param array $avatar_defaults
     * @return array
     */
    public function avatar_defaults( $avatar_defaults ) {
        remove_action( 'get_avatar', array( $this, 'get_avatar' ) );
        return $avatar_defaults;
    }
    /**
     * Delete avatars based on user_id
     *
     * @since 1.0.0
     * @param int $user_id
     */
    public function avatar_delete( $user_id ) {
        $old_avatars = get_user_meta( $user_id, 'basic_user_avatar', true );
        $upload_path = wp_upload_dir();
        if ( is_array( $old_avatars ) ) {
            foreach ( $old_avatars as $old_avatar ) {
                $old_avatar_path = str_replace( $upload_path['baseurl'], $upload_path['basedir'], $old_avatar );
                @unlink( $old_avatar_path );
            }
        }
        delete_user_meta( $user_id, 'basic_user_avatar' );
    }
    /**
     * File names are magic
     *
     * @since 1.0.0
     * @param string $dir
     * @param string $name
     * @param string $ext
     * @return string
     */
    public function unique_filename_callback( $dir, $name, $ext ) {
        $user = get_user_by( 'id', (int) $this->user_id_being_edited );
        $name = $base_name = sanitize_file_name( $user->display_name . '_avatar' );
        $number = 1;
        while ( file_exists( $dir . "/$name$ext" ) ) {
            $name = $base_name . '_' . $number;
            $number++;
        }
        return $name . $ext;
    }
}
$basic_user_avatars = new basic_user_avatars;


/**
 * During uninstallation, remove the custom field from the users and delete the local avatars
 *
 * @since 1.0.0
 */
function basic_user_avatars_uninstall() {
    $basic_user_avatars = new basic_user_avatars;
    $users = get_users_of_blog();
    foreach ( $users as $user )
        $basic_user_avatars->avatar_delete( $user->user_id );
    delete_option( 'basic_user_avatars_caps' );
}
register_uninstall_hook( __FILE__, 'basic_user_avatars_uninstall' );


/* Member directory users query*/

add_action('wp_ajax_nopriv_gag_users_ajax', 'gag_get_users_ajax');
add_action('wp_ajax_gag_users_ajax', 'gag_get_users_ajax');

function gag_get_users_ajax(){

    if (!is_user_logged_in()) {
        wp_die('You must sign in to use Directory.');
    }

    $industry = (isset($_POST['industry'])) ? $_POST['industry'] : '';
    $yearexp = (isset($_POST['yearexp'])) ? $_POST['yearexp'] : '';
    $location = (isset($_POST['location'])) ? $_POST['location'] : '';
    $keyword = (isset($_POST['keyword'])) ? $_POST['keyword'] : '';
    $payrange = (isset($_POST['payrange'])) ? $_POST['payrange'] : '';
    $ppp     = 10;
    $offset  = $_POST['offset'];


    //Industry field is mandatory
    if ($industry == '') wp_die('Industry field must be filled');


    //Getting ids of users with matching industry and yearexp

    $industry_ids = array();

    $industry1_args = array(
        'role'         => 'consultant',
        'meta_query'   =>
            array(
                array(
                    'relation' => 'AND'
                )
            )
    );

    $industry2_args = $industry1_args;
    $industry3_args = $industry1_args;
    $industry4_args = $industry1_args;

    //Industry1
    $industry1_args['meta_query'][] = array(
        'key' => 'industry1',
        'value' => $industry
    );

    if ($yearexp != '') {
        $industry1_args['meta_query'][] = array(
            'key' => 'yearexp1',
            'value' => $yearexp
        );
    }

    $industry1_users = get_users( $industry1_args );

    foreach ( $industry1_users as $user ) :
        $industry_ids[] = $user->ID;
    endforeach;


    //Industry2
    $industry2_args['meta_query'][] = array(
        'key' => 'industry2',
        'value' => $industry
    );

    if ($yearexp != '') {
        $industry2_args['meta_query'][] = array(
            'key' => 'yearexp2',
            'value' => $yearexp
        );
    }

    $industry2_users = get_users( $industry2_args );

    foreach ( $industry2_users as $user ) :
        $industry_ids[] = $user->ID;
    endforeach;


    //Industry3
    $industry3_args['meta_query'][] = array(
        'key' => 'industry3',
        'value' => $industry
    );

    if ($yearexp != '') {
        $industry3_args['meta_query'][] = array(
            'key' => 'yearexp3',
            'value' => $yearexp
        );
    }

    $industry3_users = get_users( $industry3_args );

    foreach ( $industry3_users as $user ) :
        $industry_ids[] = $user->ID;
    endforeach;


    //Industry4
    $industry4_args['meta_query'][] = array(
        'key' => 'industry4',
        'value' => $industry
    );

    if ($yearexp != '') {
        $industry4_args['meta_query'][] = array(
        'key' => 'yearexp4',
        'value' => $yearexp
    );}

    $industry4_users = get_users( $industry4_args );

    foreach ( $industry4_users as $user ) :
        $industry_ids[] = $user->ID;
    endforeach;

    if (empty($industry_ids)) return "Nothing found";

    if ($keyword != '') {
        $keyword_ids[] = array();

        $keyword_args = array(
            'include'       => $industry_ids,
            'role'          => 'consultant',
            'meta_query'    => array(
                'relation' => 'OR',
                array(
                    'key' => 'skills',
                    'value' => $keyword,
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => 'description',
                    'value' => $keyword,
                    'compare' => 'LIKE'
                )
            )
        );
        $keyword_users = get_users($keyword_args);

        foreach ($keyword_users as $user) :
            $keyword_ids[] = $user->ID;
        endforeach;

        //return (print_r($keyword_ids));
    }


    /* ZIP code search

    $user_billing_postcode = (isset($_POST['billing_postcode'])) ? $_POST['billing_postcode'] : '';
    $searchdistance = (isset($_POST['searchdistance'])) ? $_POST['searchdistance'] : 0;


    if (!empty($user_billing_postcode) && !empty($searchdistance)) {

        $args_zip = array(
            'include'      => $industry_ids,
            'role'         => 'consultant',
            'meta_query'   =>
                array()
        );

        $args_zip['meta_query'][] = array(
            'key' => 'billing_postcode',
            'value' => '',
            'compare' => '!='
        );

        $zip_consultants = get_users( $args_zip );

        foreach ( $zip_consultants as $zip_consultant ) :

            $con_zip = get_user_meta( $zip_consultant->ID, 'billing_postcode');
            $unit = 'Miles'; //Specify Units
            $distance = gag_getdistance($con_zip[0], $user_billing_postcode, $unit);

            die($distance);

            if ($distance < $searchdistance) {
                $zip_ids[] = $zip_consultant->ID;
            }

        endforeach;
        $industry_ids = $zip_ids;
    }*/

    if (!empty($keyword_ids)) $industry_ids = $keyword_ids;

    if (empty($industry_ids)) return "Nothing found";

    $args = array(
        'include'        => $industry_ids,
        'role'           => 'consultant',
        'meta_key'       => 'hourlyrate',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     =>
            array(
                array(
                    'relation' => 'AND'
                )
            )
    );

    if ($location != '') {
        $args['meta_query'][] = array(
            'key' => 'metrocity',
            'value' => $location
        );
    }

    if ($payrange != '') {
        $args['meta_query'][] = array(
            'key' => 'hourlyrate',
            'value' => $payrange
        );
    }

    $consultants = get_users( $args );

    wp_die(gag_search_results_output($consultants,$offset,$ppp));
}

add_action('wp_ajax_nopriv_gag_usersbyname_ajax', 'gag_get_usersbyname_ajax');
add_action('wp_ajax_gag_usersbyname_ajax', 'gag_get_usersbyname_ajax');
function gag_get_usersbyname_ajax(){

    if (!is_user_logged_in()) {
        wp_die('You must sign in to use Directory.');
    }

    $ppp     = 10;
    $offset  = $_POST['offset'];

    $first_name = (isset($_POST['first_name'])) ? $_POST['first_name'] : '';
    $last_name = (isset($_POST['last_name'])) ? $_POST['last_name'] : '';
    $member_number = (isset($_POST['member_number'])) ? $_POST['member_number'] : '';

    //Last Name field is mandatory
    if ($last_name == '') wp_die('Last Name field must be filled');

    $args = array(
        'role'         => 'consultant',
        'meta_query'   =>
            array(
                array(
                    'relation' => 'AND'
                )
            )
    );

    if ($first_name != '') {
        $args['meta_query'][] = array(
            'key' => 'first_name',
            'value' => $first_name
        );
    }

    if ($last_name != '') {
        $args['meta_query'][] = array(
            'key' => 'last_name',
            'value' => $last_name
        );
    }

    if ($member_number != '') {
        $args['meta_query'][] = array(
            'key' => 'member_code',
            'value' => $member_number
        );
    }

    $consultants = get_users( $args );

    wp_die(gag_search_results_output($consultants,$offset,$ppp));
}


function gag_search_results_output($consultants,$offset,$ppp) {
    $num_results = count($consultants);

    $out = '';

    //$out .= '<span>'.$num_results.' found.</span>';

    foreach ( $consultants as $consultant ) :
        $member_page_id = get_the_author_meta( 'member_page', $consultant->ID );
        $member_code = get_the_author_meta( 'member_code', $consultant->ID );
        $jobtitle    = get_the_author_meta( 'jobtitle',$consultant->ID);
        $member_industry = array();
        if (!empty(get_the_author_meta( 'industry1',$consultant->ID))) $member_industry[]  = get_the_author_meta( 'industry1',$consultant->ID);
        if (!empty(get_the_author_meta( 'industry2',$consultant->ID))) $member_industry[]  = get_the_author_meta( 'industry2',$consultant->ID);
        if (!empty(get_the_author_meta( 'industry3',$consultant->ID))) $member_industry[]  = get_the_author_meta( 'industry3',$consultant->ID);
        if (!empty(get_the_author_meta( 'industry4',$consultant->ID))) $member_industry[]  = get_the_author_meta( 'industry4',$consultant->ID);

        $member_industry    = implode(', ',$member_industry);
        $member_rate        = get_the_author_meta( 'hourlyrate' ,$consultant->ID);
        $out .= '<section class="search-results-item">';
        $out .= '<div class="grid-row">';
        $out .= '<div class="grid-col-3">';
        $out .= get_avatar( $consultant->ID, 200 );
        $out .= '<p class="member_code">#'.$member_code.'</p>';
        $out .= '</div>';
        $out .= '<div class="grid-col-9">';
        $out .= '<h4>' . $consultant->first_name . ' ' . $consultant->last_name . '</h4>';
        $out .= '<div class="member-data-container">';
        $out .= '<div class="data-section"><div class="label">Title:</div> <div class="member-data">' . $jobtitle . '</div></div>';
        $out .= '<div class="data-section"><div class="label">Industries:</div> <div class="member-data">' . $member_industry . '</div></div>';
        $out .= '</div>';
        $out .= '<div class="grid-row">';
        $out .= '<div class="grid-col-6">';
        $out .= '<div class="data-section"><div class="label">Hourly Rate:</div> <div class="member-data">' . $member_rate . '</div></div>';
        $out .= '</div>';
        $out .= '<div class="grid-col-6">';
        $out .= '<div class="view-profile"><a class="button" href="'.get_the_permalink($member_page_id).'" target="_blank">View Profile</a></div>';
        $out .= '</div></div>';
        $out .= '</div></div>';
        $out .= '</section>';

    endforeach;

    return $out;
}


/* Members directory output */

add_shortcode('gag-directory', 'gag_directory_shortcode');
function gag_directory_shortcode() {

    if ( ! is_user_logged_in() )
        return 'You must sign in to use a Directory.';

    ob_start();

    ?>

    <div class="section-headers-container">
        <div class="section-header criteria-tab active" data-tab="criteria"><h3>Search by Criteria</h3></div>
        <div class="section-header member-tab" data-tab="member"><h3>Find Member</h3></div>
    </div>

    <div class="search-by-criteria">
    <form id="gag_get_consultants" action="" data-admin-url="<?php echo admin_url( 'admin-ajax.php'); ?>">
        <input type="hidden" name="action" value="gag_users_ajax" />
        <label>Industry *
            <select name="industry">
                <option></option>
                <?php echo gag_get_option_list('gag_industries_list');?>
            </select>
        </label>
        <input type="text" name="keyword" value="" placeholder="Skills" />
        <div class="one-half first">
            <label>Years of industry experience
                <select name="yearexp">
                    <option></option>
                    <?php echo gag_get_option_list('gag_exprange_list'); ?>
                </select>
            </label>
        </div>
        <div class="one-half">
            <label>Pay Range
                <select name="payrange">
                    <option></option>
                    <?php echo gag_get_option_list('gag_hrange_list'); ?>
                </select>
            </label>
        </div>
        <label>Location
            <select name="location">
                <option></option>
                <?php echo gag_get_option_list('gag_metro_list');?>
            </select>
        </label>

        <!--
        <input type="number" name="billing_postcode" placeholder="ZIP code of your location" style="max-width: 300px;"/>
        <input type="number" name="searchdistance" placeholder="Search radius" style="max-width: 300px;"/>
        -->
        <p>
            <input type="submit" value="Search">
        </p>
    </form>
    </div>
    <div class="find-member" style="display:none">
    <form id="gag_get_consultants_byname" action="" data-admin-url="<?php echo admin_url( 'admin-ajax.php'); ?>">
        <input type="hidden" name="action" value="gag_usersbyname_ajax" />
        <label><input type="text" name="member_number" value="" placeholder="Member Number" /></label>
        <label><input type="text" name="first_name" value="" placeholder="First Name" /></label>
        <label><input type="text" name="last_name" placeholder="Last Name *" value="" /></label>
        <p>
            <input type="submit" value="Find">
        </p>
    </form>
    </div>

    <figure class="gag-search-results" style="opacity: 0">
        <h3>Search Results</h3>
        <p class="search-sort-order">Sorted by Hourly Rate</p>
        <div id="gag_members_directory">
            <div class="gag-content"></div>
        </div>
    </figure>

    <?php
    return ob_get_clean();

}


// Hook for adding admin menus
add_action('admin_menu', 'gag_add_pages');

// action function for above hook
function gag_add_pages() {
    // Add a new submenu under Options:
    add_options_page('GAG Options', 'GAG Options', 8, 'gagoptions', 'gag_options_page');
}

// mt_options_page() displays the page content for the Test Options submenu
function gag_options_page() {

    // variables for the field and option names
    $hidden_field_name = 'gag_submit_hidden';

    $opt1_name = 'gag_industries_list';
    $data_field1_name = 'gag_industries_list';

    $opt2_name = 'gag_metro_list';
    $data_field2_name = 'gag_metro_list';

    $opt3_name = 'gag_hrange_list';
    $data_field3_name = 'gag_hrange_list';

    $opt4_name = 'gag_exprange_list';
    $data_field4_name = 'gag_exprange_list';

    $opt5_name = 'gag_pw_length';
    $data_field5_name = 'gag_pw_length';

    $opt6_name = 'gag_states_list';
    $data_field6_name = 'gag_states_list';

    $opt7_name = 'gag_employer_redirect';
    $data_field7_name = 'gag_employer_redirect';

    $opt8_name = 'gag_consultant_redirect';
    $data_field8_name = 'gag_consultant_redirect';

    $opt9_name = 'gag_howhear_list';
    $data_field9_name = 'gag_howhear_list';


    // Read in existing option value from database
    $opt1_val = get_option( $opt1_name );
    $opt2_val = get_option( $opt2_name );
    $opt3_val = get_option( $opt3_name );
    $opt4_val = get_option( $opt4_name );
    $opt5_val = get_option( $opt5_name );
    $opt6_val = get_option( $opt6_name );
    $opt7_val = get_option( $opt7_name );
    $opt8_val = get_option( $opt8_name );
    $opt9_val = get_option( $opt9_name );



    // See if the user has posted us some information
    // If they did, this hidden field will be set to 'Y'
    if( $_POST[ $hidden_field_name ] == 'Y' ) {
        // Read their posted value
        $opt1_val = $_POST[ $data_field1_name ];
        $opt2_val = $_POST[ $data_field2_name ];
        $opt3_val = $_POST[ $data_field3_name ];
        $opt4_val = $_POST[ $data_field4_name ];
        $opt5_val = $_POST[ $data_field5_name ];
        $opt6_val = $_POST[ $data_field6_name ];
        $opt7_val = $_POST[ $data_field7_name ];
        $opt8_val = $_POST[ $data_field8_name ];
        $opt9_val = $_POST[ $data_field9_name ];

        // Save the posted value in the database
        update_option( $opt1_name, $opt1_val );
        update_option( $opt2_name, $opt2_val );
        update_option( $opt3_name, $opt3_val );
        update_option( $opt4_name, $opt4_val );
        update_option( $opt5_name, $opt5_val );
        update_option( $opt6_name, $opt6_val );
        update_option( $opt7_name, $opt7_val );
        update_option( $opt8_name, $opt8_val );
        update_option( $opt9_name, $opt9_val );

        // Put an options updated message on the screen

        ?>
        <div class="updated"><p><strong><?php _e('Options saved.', 'mt_trans_domain' ); ?></strong></p></div>
        <?php

    }

    // Now display the options editing screen

    echo '<div class="wrap">';

    // header

    echo "<h2>GAG Theme Options</h2>";

    // options form

    ?>

    <form name="form1" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
        <input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">

        <p><strong>Fields for sign up form:</strong></p>
        <p>Minimum password length
            <input type="number" name="<?php echo $data_field5_name; ?>" value="<?php echo $opt5_val; ?>" width="10">
        </p>
        <p>Industries list (comma separated)
            <input type="text" name="<?php echo $data_field1_name; ?>" value="<?php echo $opt1_val; ?>" size="60">
        </p>
        <p>US States list (comma separated)
            <input type="text" name="<?php echo $data_field6_name; ?>" value="<?php echo $opt6_val; ?>" size="60">
        </p>
        <p>Metro City list (comma separated)
            <input type="text" name="<?php echo $data_field2_name; ?>" value="<?php echo $opt2_val; ?>" size="60">
        </p>
        <p>Hourly rate ranges (comma separated)
            <input type="text" name="<?php echo $data_field3_name; ?>" value="<?php echo $opt3_val; ?>" size="60">
        </p>
        <p>Experience ranges (comma separated)
            <input type="text" name="<?php echo $data_field4_name; ?>" value="<?php echo $opt4_val; ?>" size="60">
        </p>
        <p>How did you hear of us (comma separated)
            <input type="text" name="<?php echo $data_field9_name; ?>" value="<?php echo $opt9_val; ?>" size="60">
        </p>
        <p>Employer success page slug
            <input type="text" name="<?php echo $data_field7_name; ?>" value="<?php echo $opt7_val; ?>" size="60">
        </p>
        <p>Consultant success page slug
            <input type="text" name="<?php echo $data_field8_name; ?>" value="<?php echo $opt8_val; ?>" size="60">
        </p><hr />

        <p class="submit">
            <input type="submit" name="Submit" value="Update Options" />
        </p>

    </form>
    </div>

    <?php
}


function gag_get_option_list($opt_name = '') {
    if ($opt_name != '') {
        $out = '';
        $opt_val = get_option( $opt_name );
        if ($opt_val != '') {
            $options = explode(',',$opt_val);
            if (is_array($options)) {
                foreach ($options as $option) {
                    $out .= '<option>'.trim($option).'</option>';
                }
            }
        }
        return $out;
    } else {
        return;
    }
}


function gag_get_industries() {
    $args = array(
        'role'         => 'consultant'
    );

    $consultants = get_users( $args );

    foreach ( $consultants as $consultant ) :
        $user_id = $consultant->ID;
        $industries[] = get_the_author_meta( 'industry', $user_id );

    endforeach;

    $industries = array_filter($industries);
    $industries = array_unique($industries);

    $out = '';

    foreach ($industries as $industry) {
        $out .= '<option>'.$industry.'</option>';
    }

    return $out;
}


function gag_get_locations() {
    $args = array(
        'role'         => 'consultant'
    );

    $consultants = get_users( $args );

    foreach ( $consultants as $consultant ) :
        $user_id = $consultant->ID;
        $locations[] = get_the_author_meta( 'metrocity', $user_id );

    endforeach;

    $locations = array_filter($locations);
    $locations = array_unique($locations);

    $out = '';

    foreach ($locations as $location) {
        $out .= '<option>'.$location.'</option>';
    }

    return $out;
}


//* Removes default Genesis Author Box, Adds a custom Author Box
remove_action( 'genesis_before_loop', 'genesis_do_author_box_archive', 1 );

add_action( 'genesis_before_loop', 'gag_author_box', 8 );
function gag_author_box() {
    if ( is_author() ) {
        $author = get_user_by( 'slug', get_query_var( 'author_name' ) );

        $author_avatar 	    = get_avatar( get_the_author_meta( 'ID',$author->ID ), 70 );
        $first_name         = get_the_author_meta( 'first_name',$author->ID);
        $last_name          = get_the_author_meta( 'last_name',$author->ID);
        $author_name        = $first_name . " " . $last_name;
        $author_desc 	    = get_the_author_meta( 'description' ,$author->ID);
        $author_email 	    = get_the_author_meta( 'email' ,$author->ID);
        $author_phone       = get_the_author_meta( 'billing_phone' ,$author->ID);
        $author_linkedin    = get_the_author_meta( 'linkedin' ,$author->ID);
        $author_travel      = get_the_author_meta( 'willingtravel' ,$author->ID);
        $author_relocate    = get_the_author_meta( 'willingrelocate' ,$author->ID);

        echo '<section class="author-box">';
        echo $author_avatar;
        echo '<h4 class="author-box-title">Consultant Profile</h4>';
        echo '<div class="author-box-content" itemprop="description">';
        echo '<p>Name:    ' . $author_name . '</p>';
        echo '<p>Contact information:<br/>';
        if ( $author_email ) echo $author_email .'<br/>';
        if ( $author_phone ) echo $author_phone .'<br/>';
        if ( $author_linkedin ) echo $author_linkedin .'<br/>';
        echo '</p>';
        echo '<p>Bio / Resume: <br/>' . $author_desc . '</p>';
        echo '<p>Willing to travel: ' . $author_travel . '</p>';
        echo '<p>Willing to relocate: ' . $author_relocate . '</p>';
        echo '</div>';
        echo '</section>';

        remove_action( 'genesis_loop', 'genesis_do_loop' );
    }
}


/* Change password from frontend profile */
add_action('wp_ajax_gag_change_password', 'gag_change_password' );
add_action('wp_ajax_nopriv_gag_change_password', 'gag_change_password' );

function gag_change_password() {

    if ( ! is_user_logged_in() )
        die('You must sign in to change the password.');

    global $current_user;

    if(isset($_POST['gag_pw_action']) && $_POST['gag_pw_action'] == 'change_password') {

        //Sanitize received password
        $password = sanitize_text_field($_POST['new_password']);

        // Define arguments that will be passed to the wp_update_user()
        $userdata = array(
            'ID'        =>  $current_user->ID,
            'user_pass' =>  $password // Wordpress automatically applies the wp_hash_password() function to the user_pass field.
        );
        $user_id = wp_update_user($userdata);

        // wp_update_user() will return the user_id on success and an array of error messages on failure.
        // so bellow we are going to check if the returned string is equal to the current user ID, if yes then we proceed updating the user meta field
        if($user_id == $current_user->ID) {
            echo 'success';

        } else {
            echo 'error';
        }
    }
    // Always exit to avoid further execution
    exit();
}


/* ZIP radius search */

// This function returns Longitude & Latitude from zip code.
function gag_getlnt($zip){
    $url = "http://maps.googleapis.com/maps/api/geocode/json?address=".urlencode($zip)."&sensor=false";
    $result_string = file_get_contents($url);
    $result = json_decode($result_string, true);
    $result1[]=$result['results'][0];
    $result2[]=$result1[0]['geometry'];
    $result3[]=$result2[0]['location'];
    return $result3[0];
}

//Gets the distance between two zip codes
function gag_getdistance($zip1, $zip2, $unit){
    $first_lat = gag_getlnt($zip1);
    $next_lat = gag_getlnt($zip2);
    $lat1 = $first_lat['lat'];
    $lon1 = $first_lat['lng'];
    $lat2 = $next_lat['lat'];
    $lon2 = $next_lat['lng'];
    $theta=$lon1-$lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    $unit = strtoupper($unit); //Personally I disable this line. I'm not sure why you would want to display "MILES" in all caps.

    if ($unit == "K"){
        return ($miles * 1.609344)." ".$unit;
    }
    else if ($unit =="N"){
        return ($miles * 0.8684)." ".$unit;
    }
    else{
        return $miles;
    }
}


add_action( 'init', 'gag_add_member_posts' );
function gag_add_member_posts() {
    $labels = array(
        'name'               => _x( 'Member page', 'post type general name', 'wordpress' ),
        'singular_name'      => _x( 'Member page', 'post type singular name', 'wordpress' ),
        'menu_name'          => _x( 'All Member pages', 'admin menu', 'wordpress' ),
        'name_admin_bar'     => _x( 'Member page', 'add new on admin bar', 'wordpress' ),
        'add_new'            => _x( 'Add Member page', 'bio', 'wordpress' ),
        'add_new_item'       => __( 'Add Member page', 'wordpress' ),
        'new_item'           => __( 'New Member page', 'wordpress' ),
        'edit_item'          => __( 'Edit', 'wordpress' ),
        'view_item'          => __( 'View', 'wordpress' ),
        'all_items'          => __( 'All Member pages', 'wordpress' )
    );

    $args = array(
        'labels'             => $labels,
        'description'        => __( 'Description.', 'wordpress' ),
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'member' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array( 'title', 'editor', 'custom-fields', 'comments' )
    );

    register_post_type( 'members', $args );
}


add_action( 'user_register', 'gag_user_registration', 10, 1 );
function gag_user_registration( $user_id ) {

    //Save extra fields
    if ( ! empty( $_POST['member_company'] ) ) {
        update_user_meta( $user_id, 'member_company', sanitize_text_field( $_POST['member_company'] ) );
    }
    if ( ! empty( $_POST['jobtitle'] ) ) {
        update_user_meta( $user_id, 'jobtitle', sanitize_text_field( $_POST['jobtitle'] ) );
    }
}


add_filter( 'genesis_post_title_output', 'gag_member_title_filter' );
function gag_member_title_filter($title) {
    global $post;

    $user = wp_get_current_user();
    $member_id = get_post_meta($post->ID,'member_user_id',true);
    $current_user_id = get_current_user_id();

    $has_permission_to_view = false;
    if ( user_can($user,'consultant') && $member_id == $current_user_id ) {
        $has_permission_to_view = true;
    } elseif ( user_can($user,'employer') ) {
        $has_permission_to_view = true;
    }

    if ( get_post_type() == 'members' && $has_permission_to_view ) {

        $title = '<h1 class="entry-title" itemprop="headline">' . get_the_author_meta( 'first_name',$member_id) . ' ' . get_the_author_meta( 'last_name',$member_id) . '</h1>';

    }
    return $title;
}


add_filter( 'the_content', 'gag_member_content_filter', 20 );
function gag_member_content_filter( $content ) {

    global $post;

    $user = wp_get_current_user();
    $member_id = get_post_meta($post->ID,'member_user_id',true);
    $current_user_id = get_current_user_id();

    $has_permission_to_view = false;
    if ( user_can($user,'consultant') && $member_id == $current_user_id ) {
        $has_permission_to_view = true;
    } elseif ( user_can($user,'employer') ) {
        $has_permission_to_view = true;
    }

    if (get_post_type() == 'members' && $has_permission_to_view) {

        $author_avatar 	    = get_avatar( get_the_author_meta( 'ID',$member_id ), 150 );
        $first_name         = get_the_author_meta( 'first_name',$member_id);
        $last_name          = get_the_author_meta( 'last_name',$member_id);
        $author_name        = $first_name . " " . $last_name;
        $author_email 	    = get_the_author_meta( 'email' ,$member_id);
        $author_phone       = get_the_author_meta( 'phonenum' ,$member_id);
        $member_code        = get_the_author_meta( 'member_code',$member_id);
        $jobtitle           = get_the_author_meta( 'jobtitle',$member_id);
        $member_metrocity   = get_the_author_meta( 'metrocity',$member_id);
        $member_intro       = get_the_author_meta( 'intro',$member_id);
        $author_desc 	    = get_the_author_meta( 'description' ,$member_id);
        $member_skills      = get_the_author_meta( 'skills',$member_id);
        $member_industry1   = get_the_author_meta( 'industry1',$member_id);
        $member_industry2   = get_the_author_meta( 'industry2',$member_id);
        $member_industry3   = get_the_author_meta( 'industry3',$member_id);
        $member_industry4   = get_the_author_meta( 'industry4',$member_id);
        $member_yearexp1    = get_the_author_meta( 'yearexp1', $member_id );
        $member_yearexp2    = get_the_author_meta( 'yearexp2', $member_id );
        $member_yearexp3    = get_the_author_meta( 'yearexp3', $member_id );
        $member_yearexp4    = get_the_author_meta( 'yearexp4', $member_id );
        $member_rate        = get_the_author_meta( 'hourlyrate' ,$member_id);
        $member_linkedin    = get_the_author_meta( 'linkedin' ,$member_id);
        $author_travel      = get_the_author_meta( 'willingtravel' ,$member_id);
        $author_relocate    = get_the_author_meta( 'willingrelocate' ,$member_id);

        $content .= '<section class="author-box">';
        $content .= '<div class="one-fourth first">';
        $content .= $author_avatar;
        $content .= '<div class="data-section"><div class="label"># </div> <div class="member-data">' . $member_code . '</div></div>';
        $content .= '</div>';

        $content .= '<div class="three-fourths">';
        $content .= '<div class="author-box-content" itemprop="description">';
        if ( $jobtitle ) {
            $content .= '<div class="data-section job-title"><h2 class="member-data job-title">' . $jobtitle . '</h2></div>';
        }
        $content .= '<div class="data-section name"><div class="member-data name">' . $author_name . '</div></div>';
        if ( $member_metrocity ) {
           $content .= '<div class="data-section metro-city"><div class="label metro-city">Metro City:</div> <div class="member-data">' . $member_metrocity . '</div></div>';
        }
        $content .= '<div class="data-section contact-info">';   //Contact information
        if ( $author_email ) {
            $content .= '<div class="member-data email"><i class="fas fa-envelope"></i>  <a href="mailto:' . $author_email . '">' . $author_email . '</a></div>';
        }
        if ( $author_phone ) {
            $content .= '<div class="member-data phone"><i class="fas fa-phone"></i> <a href="tel:' . $author_phone . '">' . $author_phone . '</a></div>';
        }
        if ( $member_linkedin ) {
            $content .= '<div class="member-data linkedin"><i class="fab fa-linkedin"></i>  <a href="' . $member_linkedin . '" target="_blank">LinkedIn</a></div>';
        }
        $content .= '</div>';
        if ( $member_intro ) {
            $content .= '<div class="data-section introduction"><div class="member-data introduction">' . $member_intro . '</div></div>';
        }
        if ( $author_desc) {
            $content .= '<div class="data-section biography"><div class="label biography">Biography:</div> <div class="member-data biography">' . nl2br($author_desc) . '</div></div>';
        }
        if ( $member_skills ) {
            $content .= '<div class="data-section skills"><div class="label skills">Skills:</div> <div class="member-data skills">' . nl2br($member_skills) . '</div></div>';
        }
        if ($member_industry1 || $member_industry2 || $member_industry3 || $member_industry4) {
            $content .= '<div class="data-section experience"><div class="label experience">Industry Experience:</div>';
            if ( $member_industry1 )    $content .= '<div class="member-data experience">' . $member_industry1 . ': ' . $member_yearexp1 . '</div>';
            if ( $member_industry2 )    $content .= '<div class="member-data experience">' . $member_industry2 . ': ' . $member_yearexp2 . '</div>';
            if ( $member_industry3 )    $content .= '<div class="member-data experience">' . $member_industry3 . ': ' . $member_yearexp3 . '</div>';
            if ( $member_industry4 )    $content .= '<div class="member-data experience">' . $member_industry4 . ': ' . $member_yearexp4 . '</div>';
            $content .= '</div>';
        }
        if ( $author_travel ) {
            $content .= '<div class="data-section travel"><div class="label travel">Willing to Travel:</div> <div class="member-data travel">' . ucfirst($author_travel) . '</div></div>';
        }
        if ( $author_relocate ) {
            $content .= '<div class="data-section relocate"><div class="label relocate">Willing to Relocate:</div> <div class="member-data relocate">' . ucfirst($author_relocate) . '</div></div>';
        }
        if ( $member_rate ) {
            $content .= '<div class="data-section rate"><div class="label rate">Hourly Rate:</div> <div class="member-data rate">' . $member_rate . '</div></div>';
        }
        $content .= '</div>';
        $content .= '</div>';
        $content .= '</section>';

        if ( get_current_user_id() == $member_id ) {
            $content .= '<p><a class="button" href="'.site_url().'/my-account">Edit My Profile</a></p>';
            $content .= '<p><a class="button" target="_blank" href="'.site_url().'/verification-letter?vl_num='.$member_id.'">Verification letter</a></p>';
            $content .= '<p><a class="button" href="'.wp_logout_url(site_url()).'">Logout</a></p>';
        } elseif ( user_can($user,'employer') ) {
            $content .= '<p><a class="button" target="_blank" href="'.site_url().'/verification-letter?vl_num='.$member_id.'">Verification letter</a></p>';
        }

    }

    // Returns the content.
    return $content;
}

/* Verification letter */

//Add a custom template from plugin
class PageTemplater {

    /**
     * A reference to an instance of this class.
     */
    private static $instance;

    /**
     * The array of templates that this plugin tracks.
     */
    protected $templates;

    /**
     * Returns an instance of this class.
     */
    public static function get_instance() {

        if ( null == self::$instance ) {
            self::$instance = new PageTemplater();
        }

        return self::$instance;

    }

    /**
     * Initializes the plugin by setting filters and administration functions.
     */
    private function __construct() {

        $this->templates = array();


        // Add a filter to the attributes metabox to inject template into the cache.
        if ( version_compare( floatval( get_bloginfo( 'version' ) ), '4.7', '<' ) ) {

            // 4.6 and older
            add_filter(
                'page_attributes_dropdown_pages_args',
                array( $this, 'register_project_templates' )
            );

        } else {

            // Add a filter to the wp 4.7 version attributes metabox
            add_filter(
                'theme_page_templates', array( $this, 'add_new_template' )
            );

        }

        // Add a filter to the save post to inject out template into the page cache
        add_filter(
            'wp_insert_post_data',
            array( $this, 'register_project_templates' )
        );


        // Add a filter to the template include to determine if the page has our
        // template assigned and return it's path
        add_filter(
            'template_include',
            array( $this, 'view_project_template')
        );


        // Add your templates to this array.
        $this->templates = array(
            'verification-letter-template/v_letter.php' => 'Verification Letter',
        );

    }

    /**
     * Adds our template to the page dropdown for v4.7+
     *
     */
    public function add_new_template( $posts_templates ) {
        $posts_templates = array_merge( $posts_templates, $this->templates );
        return $posts_templates;
    }

    /**
     * Adds our template to the pages cache in order to trick WordPress
     * into thinking the template file exists where it doesn't really exist.
     */
    public function register_project_templates( $atts ) {

        // Create the key used for the themes cache
        $cache_key = 'page_templates-' . md5( get_theme_root() . '/' . get_stylesheet() );

        // Retrieve the cache list.
        // If it doesn't exist, or it's empty prepare an array
        $templates = wp_get_theme()->get_page_templates();
        if ( empty( $templates ) ) {
            $templates = array();
        }

        // New cache, therefore remove the old one
        wp_cache_delete( $cache_key , 'themes');

        // Now add our template to the list of templates by merging our templates
        // with the existing templates array from the cache.
        $templates = array_merge( $templates, $this->templates );

        // Add the modified cache to allow WordPress to pick it up for listing
        // available templates
        wp_cache_add( $cache_key, $templates, 'themes', 1800 );

        return $atts;

    }

    /**
     * Checks if the template is assigned to the page
     */
    public function view_project_template( $template ) {

        // Get global post
        global $post;

        // Return template if post is empty
        if ( ! $post ) {
            return $template;
        }

        // Return default template if we don't have a custom one defined
        if ( ! isset( $this->templates[get_post_meta(
                $post->ID, '_wp_page_template', true
            )] ) ) {
            return $template;
        }

        $file = plugin_dir_path( __FILE__ ). get_post_meta(
                $post->ID, '_wp_page_template', true
            );

        // Just to be safe, we check if the file exist first
        if ( file_exists( $file ) ) {
            return $file;
        } else {
            echo $file;
        }

        // Return template
        return $template;

    }

}
add_action( 'plugins_loaded', array( 'PageTemplater', 'get_instance' ) );


function gag_v_letter() {
    global $wp_query;

    if (empty($wp_query->query_vars['vl_num']) ) {
        return '';
    }

    if ( is_user_logged_in() ) {
        $user = wp_get_current_user();
        $member_id = $wp_query->query_vars['vl_num'];
        $current_user_id = get_current_user_id();

        $has_permission_to_view = false;
        if ( user_can($user,'consultant') && $member_id == $current_user_id ) {
            $has_permission_to_view = true;
        } elseif ( user_can($user,'employer') ) {
            $has_permission_to_view = true;
        }
        if ( !$has_permission_to_view ) {
            return '';
        }
    } else {
        return '';
    }

    $member_id = $wp_query->query_vars['vl_num'];

    if(user_can($member_id, 'consultant')) {
        $first_name = get_the_author_meta( 'first_name',$member_id);
        $last_name = get_the_author_meta( 'last_name',$member_id);
        $user_code = get_the_author_meta( 'member_code',$member_id);
        $city = get_the_author_meta( 'billing_city',$member_id);
        $state = get_the_author_meta( 'billing_state',$member_id);
        $jobtitle = get_the_author_meta( 'jobtitle',$member_id);
        $pms_member = pms_get_member( $member_id );

        if ($pms_member) {
            $start_date = strtotime($pms_member->subscriptions[0]["start_date"]);
            $start_date = date('m/d/Y',$start_date);
            if ($pms_member->subscriptions[0]["status"] == 'active') {
                $end_date = "present";
            } else {
                $end_date = strtotime($pms_member->subscriptions[0]["expiration_date"]);
                $end_date = date('m/d/Y',$end_date);
            }
        }

        $avatar = get_avatar( $member_id, 100 );

        ?>
        <style>
        @page
        {
            size:  auto;
            margin: 0mm;  /* this affects the margin in the printer settings */
        }
        </style>
        <section class="verification-letter-wrap">
            <figure class="letter-header">
                <div class="letter-logo">
                    <div class="grid-row">
                        <div class="grid-col-3">
                            <img src="<?php echo plugin_dir_url( __FILE__ ).'verification-letter-template/images/logo_head.png'; ?>"/>
                        </div>
                        <div class="grid-col-6"></div>
                        <div class="grid-col-3">
                            <p>&nbsp;</p>
                            <a class="print-hide button" href="javascript:window.print();">Print</a>
                        </div>
                    </div>
                </div>
                <div class="letter-title">
                    <h1>Consultant Verification for</h1>
                    <h2><?php echo $last_name;?>, <?php echo $first_name;?></h2>
                </div>
                <div class="letter-date">
                    <p><?php echo date('m/d/Y');?></p>
                </div>
            </figure>
            <figure class="letter-content">
                <p>To Whom It May Concern:</p>
                <p>Thank you for inquiring about a consultant’s history with Gain Advisory Group. This letter is to serve as written verification for: </p>
                <div class="letter-columns">
                    <div class="grid-row">
                        <div class="grid-col-1"></div>
                        <div class="grid-col-8">
                            <div class="grid-row">
                                <div class="grid-col-4">Number:</div>
                                <div class="grid-col-8">23-<?php echo $user_code;?></div>
                            </div>
                            <div class="grid-row">
                                <div class="grid-col-4">Name: </div>
                                <div class="grid-col-8"><?php echo $first_name;?> <?php echo $last_name;?></div>
                            </div>
                            <div class="grid-row">
                                <div class="grid-col-4">Location</div>
                                <div class="grid-col-8"><?php echo $city;?>, <?php echo $state;?></div>
                            </div>
                            <div class="grid-row">
                                <div class="grid-col-4">Job Title: </div>
                                <div class="grid-col-8"><span class="jobtitle"><?php echo $jobtitle;?></span> (Consultant)</div>
                            </div>
                            <div class="grid-row">
                                <div class="grid-col-4">Dates Range:</div>
                                <div class="grid-col-8"><?php if (isset($start_date)) echo $start_date;?> until <?php if (isset($end_date)) echo $end_date; ?></div>
                            </div>

                        </div>
                        <div class="grid-col-3">
                            <?php if (strpos($avatar,'gravatar')) { ?>
                                <div class="letter-avatar-placeholder">No photo</div>
                            <?php } else { ?>
                                <?php echo $avatar; ?>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <p>Due to confidentiality, individual responsibilities and financial compensation will not be disclosed. </p>
                <p><span class="red">For legal purposes, this letter may not be used as proof of United States of America citizenship or for any H1B1 Visa documentation.</span></p>
                <p>Should you have any other questions, please provide written correspondence to <a href="mailto:Verify@GainAdvisoryGroup.com">Verify@GainAdvisoryGroup.com</a></p>
                <p>Sincerely,<br/><span class="red">Carrie Reed<br/>Human Resource Dept</span></p>
            </figure>
            <figure class="letter-footer">
                <p>Gain Advisory Group&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;707 Main Street, Suite #201&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Nashville, TN&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;37206</p>
            </figure>
        </section>

        <?php
    }

    return '';
}


//Add query vars for verification letter
function gag_add_query_vars($aVars) {
    $aVars[] = "vl_num";
    return $aVars;
}
// hook add_query_vars function into query_vars
add_filter('query_vars', 'gag_add_query_vars');


//Set consultant code for the first time
add_action( 'init', 'gag_set_member_code' );
function gag_set_member_code() {
    if (empty(get_option('gag_member_code'))) update_option('gag_member_code',532);
}


// Shortcode for saving roles ids in the DOM
add_shortcode('gag-role', 'gag_role_ids');
function gag_role_ids($atts) {
    $consultant_id = $atts['role'];
    $out = '<div id="gag_registration_role" data-role="'.$consultant_id.'"></div>';
    return $out;
}

// Shortcode for "Edit Profile" link (displayed only when a Jobseeker is logged in
add_shortcode('gag-edit-profile', 'gag_edit_profile');
function gag_edit_profile ($atts) {
    $user = wp_get_current_user();
    if (!empty($atts['title'])) {
        $link_title = $atts['title'];
    } else {
        $link_title = "Edit Profile";
    }
    $out = '<p><a class="' . $atts['class'] . '" href="'.site_url().'/my-account">' . $link_title . '</a></p>';
    if (empty($atts['role'])) {
        return $out;
    } elseif (user_can($user,$atts['role'])) {
        return $out;
    }
    return;
}

//Add extra fields to reg form
add_action('pms_register_form_top','gag_form_top_fields');
function gag_form_top_fields(){
    $out = '<ul class="pms-form-fields-wrapper">
                <li class="pms-field pms-user-company-field ">
                    <label for="member_company">Company *</label>
                    <input id="member_company" name="member_company" type="text" value="">
                </li>
                <li class="pms-field pms-user-jobtitle-field ">
                    <label for="jobtitle">Job Title *</label>
                    <input id="jobtitle" name="jobtitle" type="text" value="">
                </li>
             </ul>';

    echo $out;
}


/* Redirect member to custom success page */

add_shortcode('gag-user-redirect', 'gag_redirect_shortcode');
function gag_redirect_shortcode() {
    $user = wp_get_current_user();
    $employer_redirect = get_option('gag_employer_redirect');
    $consultant_redirect = get_option('gag_consultant_redirect');

    if (user_can($user,'employer') && !empty($employer_redirect)) {
        return('<script>document.location.href="'.site_url().'/'.$employer_redirect.'";</script>');

    } elseif (user_can($user,'consultant') && !empty($consultant_redirect)) {
        return('<script>document.location.href="'.site_url().'/'.$consultant_redirect.'";</script>');
    }

    return('<h1>Thank you for registering!</h1><p>View <a href="/my-account/">my profile</a></p>');

}


function gag_hide_func( $atts = "", $content = "" ) {
    if (is_user_logged_in()) {
        return "";
    } else {
        return $content;
    }
}
add_shortcode( 'hide-from-members', 'gag_hide_func' );


// Add Agree To Terms checkbox to payment page
// Includes next three functions
add_action('pms_register_form_after_fields', 'gain_checkbox_field');
function gain_checkbox_field($atts) {
    if ($atts['subscription_plans'][0] == 281) {
        $field_errors = pms_errors()->get_error_messages('user_terms_agree');

        $html = '<li class="pms-field ' . ( !empty($field_errors) ? 'pms-field-error' : '') . '">';
        $html .= '<label for="pms_user_terms_agree"><input id="pms_user_terms_agree" name="user_terms_agree" type="checkbox" value="1">';
        $html .= __('By checking this box, I agree to the <a href="/automatic-recurring-billing-agreement/" target="_blank">Automatic Recurring Billing Agreement terms and conditions</a>. *', 'paid-member-subscriptions') . '</label>';
        $html .= pms_display_field_errors( $field_errors, true );
        $html .= '</li>';

        echo $html;
    }
}

add_action('pms_register_form_validation', 'gain_checkbox_validation');
function gain_checkbox_validation() {
    if ($atts['subscription_plans'][0] == 281) {
        if (!isset($_POST['user_terms_agree']))
            {pms_errors()->add('user_terms_agree', __('This field is required.', 'paid-member-subscriptions'));
        }
    }
}

add_action( 'pms_register_form_after_create_user', 'gain_save_custom_field' );
function gain_save_custom_field( $user_data ) {
    if ($atts['subscription_plans'][0] == 281) {
        if ( !empty($user_data['user_id']) && isset( $_POST['user_terms_agree'] ) && $_POST['user_terms_agree'] == 1 )
            {update_user_meta( $user_data['user_id'], 'user_terms_agree', 'yes' );
        }
    }
}


add_action( 'pms_after_checkout_is_processed', 'gag_assign_id' );
function gag_assign_id($subscription) {
    $user_id = $subscription->user_id;

    if (user_can($user_id,'consultant')) {
        $user_code = get_option('gag_member_code');
        $user_code += 1;
        update_option('gag_member_code',$user_code);
        update_user_meta($user_id, 'member_code', $user_code);

        // Create post object
        $my_post = array(
            'post_title'    => get_the_author_meta( 'user_login', $user_id ),
            'post_status'   => 'publish',
            'post_type'     => 'members',
            'post_content'  => '',
            'comment_status'=> 'open',
            'meta_input'    => array(
                'member_user_id' => $user_id
            )
        );

        // Insert the post into the database
        $member_page_id = wp_insert_post( $my_post );

        if ($member_page_id != 0) update_user_meta($user_id, 'member_page', $member_page_id);


    }
}


//* Removes the comments altogether
add_action( 'genesis_after_entry', 'gag_remove_comments_genesis', 0 );
function gag_remove_comments_genesis() {
    if (get_post_type() == 'members')
        remove_action( 'genesis_after_entry', 'genesis_get_comments_template' );
}

add_filter( 'genesis_entry_footer', 'gag_remove_entry_footer', 2 );
function gag_remove_entry_footer() {
    if (get_post_type() == 'members') {
        remove_action( 'genesis_entry_footer', 'genesis_entry_footer_markup_open', 5 );
        remove_action( 'genesis_entry_footer', 'genesis_post_meta' );
        remove_action( 'genesis_entry_footer', 'genesis_entry_footer_markup_close', 15 );
    }
}
