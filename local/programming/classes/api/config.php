<?php
namespace local_programming\api;

defined('MOODLE_INTERNAL') || die();

class config {
    public const DOMAIN = 'http://139.59.105.152';
    public const TOKEN_SECRET = 'secret';
    public const ACCESS_TOKEN_URL = self::DOMAIN . '/api/token/';
    public const REFRESH_TOKEN_URL = self::DOMAIN . '/api/token/refresh/';
}

