<?php
declare(strict_types=1);

const APP_NAME = 'GPSPlus License';
const APP_VERSION = '1.1.0';
const SESSION_NAME = 'gpsplus_admin';
const DB_PATH = __DIR__ . '/storage/gpsplus.sqlite';

function env_value(string $key, string $default = ''): string {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

function api_key(): string { return env_value('GPS_API_KEY', 'CHANGE_ME_TO_A_LONG_RANDOM_API_KEY'); }
function admin_username(): string { return env_value('GPS_ADMIN_USERNAME', 'admin'); }
function admin_password(): string { return env_value('GPS_ADMIN_PASSWORD', 'CHANGE_ME_TO_A_STRONG_PASSWORD'); }
function app_base_url(): string { return rtrim(env_value('GPS_BASE_URL', 'https://ipa.p3nd.fun/gps'), '/'); }

date_default_timezone_set('Asia/Riyadh');
