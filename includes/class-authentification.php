<?php

if (!defined('ABSPATH')) exit;

class Authentification
{
    private array $supported_algorithms = [
        'HS256','HS384','HS512',
        'RS256','RS384','RS512',
        'ES256','ES384','ES512',
        'PS256','PS384','PS512'
    ];

    public function __construct() {}

    public function get_algorithm()
    {
        $algorithm = apply_filters('jwt_auth_algorithm', 'HS256');
        if (!in_array($algorithm, $this->supported_algorithms)) {
            return false;
        }
        return $algorithm;
    }

    public static function generate_jwt($duration_minutes = null)
    {
        if (!class_exists('\Tmeister\Firebase\JWT\JWT')) {
            return new WP_Error('jwt_library_missing', 'Librairie JWT manquante.');
        }
        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return new WP_Error('jwt_no_user', 'Aucun utilisateur connecté.');
        }
        $secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
        if (!$secret_key) {
            return new WP_Error('jwt_bad_config', 'JWT mal configuré.');
        }
        if (empty($duration_minutes)) {
            $duration_minutes = absint(get_option('jwt_duration', 300));
        }

        $issuedAt  = time();
        $notBefore = apply_filters('jwt_auth_not_before', $issuedAt, $issuedAt);
        $expire    = apply_filters('jwt_auth_expire', $issuedAt + (MINUTE_IN_SECONDS * $duration_minutes), $issuedAt);

        $token = [
            'iss'  => get_bloginfo('url'),
            'iat'  => $issuedAt,
            'nbf'  => $notBefore,
            'exp'  => $expire,
            'data' => [
                'user' => [
                    'id' => $user->ID,
                ],
            ],
        ];

        $algorithm = (new self())->get_algorithm();
        if ($algorithm === false) {
            return new WP_Error(
                'jwt_auth_unsupported_algorithm',
                __('Algorithm not supported', 'wp-api-jwt-auth'),
                ['status' => 403]
            );
        }

        return \Tmeister\Firebase\JWT\JWT::encode(
            apply_filters('jwt_auth_token_before_sign', $token, $user),
            $secret_key,
            $algorithm
        );
    }

    public static function generate_magic_link()
    {
        $jwt = self::generate_jwt();
        if (is_wp_error($jwt)) {
            return $jwt;
        }
        $frontend_url = esc_url(get_option('frontend_url', 'http://localhost:5173'));
        $link = $frontend_url . '?magic_token=' . $jwt;
        error_log(sprintf('[Inline-Editor-CMS] Magic link generated for user %d, JWT duration = %d min', get_current_user_id(), get_option('jwt_duration', 300)));
        return $link;
    }
}

new Authentification();
