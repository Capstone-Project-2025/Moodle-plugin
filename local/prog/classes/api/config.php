<?php
namespace local_prog\api;

defined('MOODLE_INTERNAL') || die();

class config {
    public const DOMAIN = 'http://172.29.64.156:4000';
    public const TOKEN_SECRET = 'secret';
    public const ACCESS_TOKEN_URL = self::DOMAIN . '/api/token/';
    public const REFRESH_TOKEN_URL = self::DOMAIN . '/api/token/refresh/';
}

