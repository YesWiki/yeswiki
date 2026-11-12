<?php

namespace YesWiki\Kernel\Service;

/**
 * Which settings the operator decides, from the environment or `private/.env`, and which the wiki keeps in its database (replicas 04).
 * An operator setting is read before the container exists or compiled into it; every other setting is the wiki's.
 */
final class SettingKinds
{
    public const OPERATOR = [
        'db_driver', 'db_host', 'db_database', 'db_user', 'db_password', 'db_port', 'db_charset', 'table_prefix',
        'base_url', 'rewrite_mode', 'cookies_samesite', 'extra_headers', 'timezone', 'debug', 'mail_domain', 'max_file_size',
        'active_extensions', 'forbidden_extensions',
        'queue_consumer', 'queue_consume_secret',
        'contact_mail_func', 'contact_smtp_host', 'contact_smtp_port', 'contact_smtp_user', 'contact_smtp_pass', 'contact_smtp_secure',
        'contact_smtp_verify_peer', 'contact_dkim_domain', 'contact_dkim_selector', 'contact_dkim_private_key', 'contact_debug',
        'trusted_proxies', 'trusted_headers', 'http_cache_ttl', 'herse_id', 'herse_password',
        'yeswiki_version', 'yeswiki_release',
    ];

    public static function isOperator(string $key): bool
    {
        return in_array($key, self::OPERATOR, true);
    }
}
