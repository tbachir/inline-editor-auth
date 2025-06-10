<?php

/**
 * Plugin Name: Inline Editor Magic Auth
 * Description: Ajoute l’authentification Magic Link + JWT pour Inline Editor CMS.
 * Version: 1.0.1
 * Author: Tarek Bachir
 * Text Domain: inline-editor-auth
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/class-authentification.php';

class Inline_Editor_Auth_Plugin
{

    public function __construct()
    {
        // Barre d’admin : maison (site-name) et bouton Magic Link → endpoint dynamique
        add_action('admin_bar_menu', [$this, 'replace_frontend_link_with_magic_link'], 2000);
        // Réglages
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        // Page de debug
        add_action('admin_menu', [$this, 'add_debug_page']);
        // Endpoint sécurisé pour générer/rediriger Magic Link
        add_action('admin_post_generate_magic_link', [$this, 'handle_generate_magic_link']);
        // CSS custom admin (optionnel, personnalise ici)
        add_action('admin_head', [$this, 'custom_admin_css']);
    }

    /**
     * Maison (site-name) redirige vers le endpoint dynamique.
     */
    public function replace_frontend_link_with_magic_link($wp_admin_bar)
    {
        if (!is_user_logged_in() || !current_user_can('read')) return;

        $node = $wp_admin_bar->get_node('site-name');
        if ($node) {
            $wp_admin_bar->add_node([
                'id'     => 'site-name',
                'title'  => $node->title,
                'href'   => admin_url('admin-post.php?action=generate_magic_link'),
                'meta'   => array_merge($node->meta ?? [], ['target' => '_blank']),
            ]);
        }
    }

    /**
     * Endpoint : génère le magic link et redirige vers le front.
     */
    public function handle_generate_magic_link()
    {
        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_die(__('Non autorisé', 'inline-editor-auth'), 403);
        }
        $magic_link = Authentification::generate_magic_link();
        if (is_wp_error($magic_link)) {
            wp_die(esc_html($magic_link->get_error_message()), 500);
        }
        wp_redirect($magic_link);
        exit;
    }

    /**
     * Ajoute la page de réglages au menu Réglages
     */
    public function add_settings_page()
    {
        add_options_page(
            __('Réglages Magic Link', 'inline-editor-auth'),
            __('Magic Link Auth', 'inline-editor-auth'),
            'manage_options',
            'inline_editor_auth',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Déclare les settings pour frontend_url et jwt_duration
     */
    public function register_settings()
    {
        register_setting('inline_editor_auth_group', 'frontend_url', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => 'http://localhost:5173'
        ]);
        register_setting('inline_editor_auth_group', 'jwt_duration', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 300 // 5h en minutes
        ]);
    }

    /**
     * Affiche la page de configuration
     */
    public function render_settings_page()
    {
?>
        <div class="wrap">
            <h1><?php _e('Réglages Magic Link', 'inline-editor-auth'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('inline_editor_auth_group'); ?>
                <?php do_settings_sections('inline_editor_auth_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="frontend_url"><?php _e('URL du Front', 'inline-editor-auth'); ?></label></th>
                        <td><input type="url" id="frontend_url" name="frontend_url" value="<?php echo esc_attr(get_option('frontend_url', 'http://localhost:5173')); ?>" class="regular-text ltr" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="jwt_duration"><?php _e('Durée du token (minutes)', 'inline-editor-auth'); ?></label></th>
                        <td><input type="number" id="jwt_duration" name="jwt_duration" value="<?php echo esc_attr(get_option('jwt_duration', 300)); ?>" min="5" max="1440" step="5"></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
    <?php
    }

    /**
     * Ajoute la page de debug au menu Réglages
     */
    public function add_debug_page()
    {
        add_submenu_page(
            'options-general.php',
            __('Magic Link Debug', 'inline-editor-auth'),
            __('Magic Link Debug', 'inline-editor-auth'),
            'manage_options',
            'inline-editor-auth-debug',
            [$this, 'render_debug_page']
        );
    }

    /**
     * Affiche la page de debug (affiche le lien vers l'endpoint dynamique : un seul token émis si clic)
     */
    public function render_debug_page()
    {
        $user = wp_get_current_user();
        $frontend_url = get_option('frontend_url', 'http://localhost:5173');
        $jwt_duration = get_option('jwt_duration', 300);
        $admin_url = admin_url('admin-post.php?action=generate_magic_link');
    ?>
        <div class="wrap">
            <h1><?php _e('Magic Link Debug', 'inline-editor-auth'); ?></h1>
            <table class="widefat" style="max-width:700px;">
                <tbody>
                    <tr>
                        <th><?php _e('URL du Front', 'inline-editor-auth'); ?></th>
                        <td><?php echo esc_html($frontend_url); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Durée du Token (minutes)', 'inline-editor-auth'); ?></th>
                        <td><?php echo esc_html($jwt_duration); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Utilisateur courant', 'inline-editor-auth'); ?></th>
                        <td>
                            ID: <code><?php echo esc_html($user->ID); ?></code><br>
                            Login: <code><?php echo esc_html($user->user_login); ?></code><br>
                            Rôles: <code><?php echo esc_html(implode(', ', $user->roles)); ?></code>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Lien dynamique Magic Link', 'inline-editor-auth'); ?></th>
                        <td>
                            <input type="text" value="<?php echo esc_attr($admin_url); ?>" readonly style="width:100%;">
                            <a href="<?php echo esc_url($admin_url); ?>" target="_blank" class="button"><?php _e('Générer & ouvrir', 'inline-editor-auth'); ?></a>
                            <em style="display:block;color:#888;font-size:0.95em;"><?php _e('Le JWT/Magic Link ne sera généré qu’au clic.', 'inline-editor-auth'); ?></em>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php
    }

    /**
     * CSS custom pour admin (adapte à ton goût)
     */
    public function custom_admin_css()
    {
    ?>
        <style>
            #wpadminbar li.hover>.ab-sub-wrapper,
            #wpadminbar.nojs li:hover>.ab-sub-wrapper {
                display: none !important;
            }
        </style>
<?php
    }
}

new Inline_Editor_Auth_Plugin();
