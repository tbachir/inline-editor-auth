<?php
/**
 * Plugin Name: Inline Editor Magic Auth
 * Description: Ajoute l’authentification Magic Link + JWT pour Inline Editor CMS.
 * Version: 1.0.0
 * Author: Tarek Bachir
 * Text Domain: inline-editor-auth
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/class-authentification.php';

class Inline_Editor_Auth_Plugin {

    public function __construct() {
        // Remplace l’icône “maison” (accès front) par le Magic Link
        add_action('admin_bar_menu', [$this, 'replace_frontend_link_with_magic_link'], 20);
        // Ajoute un bouton dédié “Magic Link (Front)” à droite
        add_action('admin_bar_menu', [$this, 'add_admin_bar_magic_link'], 100);
        // Ajoute la page de réglages
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        // Ajoute la page de debug
        add_action('admin_menu', [$this, 'add_debug_page']);
    }

    /**
     * Remplace le lien du front (icône maison) dans la barre d’admin par le magic link.
     */
    public function replace_frontend_link_with_magic_link($wp_admin_bar) {
        if (!is_user_logged_in() || !current_user_can('read')) return;

        $magic_link = Authentification::generate_magic_link();
        if (is_wp_error($magic_link)) return;

        $node = $wp_admin_bar->get_node('site-name');
        if ($node) {
            $wp_admin_bar->add_node([
                'id'     => 'site-name',
                'title'  => $node->title,
                'href'   => esc_url($magic_link),
                'meta'   => array_merge($node->meta ?? [], ['target' => '_blank']),
            ]);
        }
    }

    /**
     * Ajoute un bouton “Magic Link (Front)” dans la barre d’admin
     */
    public function add_admin_bar_magic_link($admin_bar) {
        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            return;
        }
        $link = Authentification::generate_magic_link();
        if (is_wp_error($link)) return;
        $admin_bar->add_node([
            'id'    => 'magic-link',
            'title' => __('🔑 Magic Link (Front)', 'inline-editor-auth'),
            'href'  => esc_url($link),
            'meta'  => ['target' => '_blank', 'title' => __('Ouvre le front avec magic link', 'inline-editor-auth')],
            'parent'=> false,
        ]);
    }

    /**
     * Ajoute la page de réglages au menu Réglages
     */
    public function add_settings_page() {
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
    public function register_settings() {
        register_setting('inline_editor_auth_group', 'frontend_url', [
            'type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => 'http://localhost:5173'
        ]);
        register_setting('inline_editor_auth_group', 'jwt_duration', [
            'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 300 // 5h en minutes
        ]);
    }

    /**
     * Affiche la page de configuration
     */
    public function render_settings_page() {
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
    public function add_debug_page() {
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
     * Affiche la page de debug
     */
    public function render_debug_page() {
        $user = wp_get_current_user();
        $frontend_url = get_option('frontend_url', 'http://localhost:5173');
        $jwt_duration = get_option('jwt_duration', 300);
        $jwt = Authentification::generate_jwt();
        $magic_link = Authentification::generate_magic_link();
        $jwt_decoded = null;
        $jwt_error = null;

        if (is_wp_error($jwt)) {
            $jwt_error = $jwt->get_error_message();
        } elseif (class_exists('\Tmeister\Firebase\JWT\JWT')) {
            try {
                // Décodage du token pour affichage (attention, pour debug uniquement !)
                $key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : '';
                $algorithm = (new Authentification())->get_algorithm();
                $jwt_decoded = \Tmeister\Firebase\JWT\JWT::decode($jwt, new \Tmeister\Firebase\JWT\Key($key, $algorithm));
            } catch (\Exception $e) {
                $jwt_error = $e->getMessage();
            }
        } else {
            $jwt_error = __('Librairie JWT manquante', 'inline-editor-auth');
        }

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
                        <th><?php _e('Magic Link', 'inline-editor-auth'); ?></th>
                        <td>
                            <?php if (!is_wp_error($magic_link)) : ?>
                                <input type="text" value="<?php echo esc_attr($magic_link); ?>" readonly style="width:100%;">
                                <a href="<?php echo esc_url($magic_link); ?>" target="_blank" class="button"><?php _e('Ouvrir', 'inline-editor-auth'); ?></a>
                            <?php else: ?>
                                <span style="color:red;"><?php echo esc_html($magic_link->get_error_message()); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('JWT', 'inline-editor-auth'); ?></th>
                        <td>
                            <?php if ($jwt_error) : ?>
                                <span style="color:red;"><?php echo esc_html($jwt_error); ?></span>
                            <?php else: ?>
                                <textarea readonly style="width:100%;" rows="2"><?php echo esc_html($jwt); ?></textarea>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Payload JWT décodé', 'inline-editor-auth'); ?></th>
                        <td>
                            <?php if ($jwt_decoded) : ?>
                                <pre style="max-width:100%;overflow:auto;"><?php echo esc_html(print_r($jwt_decoded, true)); ?></pre>
                            <?php else: ?>
                                <em><?php _e('Aucun payload disponible ou JWT invalide.', 'inline-editor-auth'); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
}

new Inline_Editor_Auth_Plugin();
