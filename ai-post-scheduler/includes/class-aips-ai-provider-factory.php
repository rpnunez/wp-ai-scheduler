<?php
/**
 * AI Provider Factory
 *
 * Resolves which AIPS_AI_Provider_Interface implementation should serve AI
 * requests. Selection order:
 *   1. An explicit provider id passed to create().
 *   2. The aips_ai_provider option (set via the settings dropdown).
 *   3. Auto-detection: the first *available* provider in REGISTRY order
 *      (Meow is listed first, preserving it as the default).
 *   4. AIPS_Null_AI_Provider when nothing is available.
 *
 * Adding a new AI backend is a two-step change: write a class implementing
 * AIPS_AI_Provider_Interface and add one entry to REGISTRY.
 *
 * @package AI_Post_Scheduler
 * @since 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIPS_AI_Provider_Factory {

    /**
     * Ordered map of provider id => implementing class name.
     *
     * Order matters: auto-detection picks the first available entry, so Meow
     * stays the default for existing installs.
     *
     * @var array<string,string>
     */
    private const REGISTRY = array(
        'meow'         => 'AIPS_Meow_AI_Provider',
        'wp_ai_client' => 'AIPS_WP_AI_Client_Provider',
    );

    /**
     * Create the active provider.
     *
     * @param string|null $id Optional explicit provider id (overrides settings).
     * @return AIPS_AI_Provider_Interface
     */
    public static function create(?string $id = null): AIPS_AI_Provider_Interface {
        // 1. Explicit id.
        if ($id !== null && $id !== '') {
            $provider = self::instantiate($id);

            if ($provider !== null) {
                return $provider;
            }
        }

        // 2. Configured option.
        $configured = (string) AIPS_Config::get_instance()->get_option('aips_ai_provider');

        if ($configured !== '') {
            $provider = self::instantiate($configured);

            // Honour the explicit choice even if currently unavailable; the
            // service's is_available() guard yields the usual 'ai_unavailable'
            // error and the admin notice tells the user to install/enable it.
            if ($provider !== null) {
                return $provider;
            }
        }

        // 3. Auto-detect the first available provider.
        foreach (array_keys(self::REGISTRY) as $registered_id) {
            $provider = self::instantiate($registered_id);

            if ($provider !== null && $provider->is_available()) {
                return $provider;
            }
        }

        // 4. Nothing available.
        return new AIPS_Null_AI_Provider();
    }

    /**
     * List providers that are currently available, for the settings dropdown.
     *
     * @return array<string,string> Map of id => label.
     */
    public static function available_providers(): array {
        $available = array();

        foreach (array_keys(self::REGISTRY) as $id) {
            $provider = self::instantiate($id);

            if ($provider !== null && $provider->is_available()) {
                $available[$id] = $provider->get_label();
            }
        }

        return $available;
    }

    /**
     * List every known provider regardless of availability.
     *
     * @return array<string,string> Map of id => label.
     */
    public static function all_providers(): array {
        $all = array();

        foreach (array_keys(self::REGISTRY) as $id) {
            $provider = self::instantiate($id);

            if ($provider !== null) {
                $all[$id] = $provider->get_label();
            }
        }

        return $all;
    }


    /**
     * List unavailable providers with diagnostic reasons for the settings UI.
     *
     * @return array<string,string> Map of id => unavailable reason.
     */
    public static function unavailable_reasons(): array {
        $reasons = array();

        foreach (array_keys(self::REGISTRY) as $id) {
            $provider = self::instantiate($id);

            if ($provider === null || $provider->is_available()) {
                continue;
            }

            $reasons[$id] = $provider->get_unavailable_reason();
        }

        return $reasons;
    }

    /**
     * Instantiate a provider by id, or null if the id is unknown / class missing.
     *
     * @param string $id Provider id.
     * @return AIPS_AI_Provider_Interface|null
     */
    private static function instantiate(string $id): ?AIPS_AI_Provider_Interface {
        if (!isset(self::REGISTRY[$id])) {
            return null;
        }

        $class = self::REGISTRY[$id];

        if (!class_exists($class)) {
            return null;
        }

        return new $class();
    }
}
