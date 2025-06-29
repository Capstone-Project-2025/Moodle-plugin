<?php

function oauth_add_wordpress_client($client_id, $url) {
    global $DB;

    $url = trim($url);
    if (substr($url, -1) !== '/') {
        $url .= '/';
    }
    $record = new stdClass();
    $record->redirect_uri = $url . 'wp-content/plugins/wordpress-social-login/hybridauth/callbacks/moodle.php';
    $record->grant_types = 'authorization_code';
    $record->scope = 'user_info ';
    $record->user_id = '';

    // do save
    $record->client_id = $client_id;
    $record->client_secret = generate_secret();

    return $DB->insert_record('oauth_clients', $record);
}

// An equivalent of fopen('/dev/urandom', 'rb'): generate 32 random ASCII characters
// https://stackoverflow.com/a/4356295
function generateRandomString($length = 32) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

function generate_secret() {
    // Get a bunch of random characters from the OS
    /*
    $fp = fopen('/dev/urandom', 'rb');
    $entropy = fread($fp, 32);
    fclose($fp);
    */
    $entropy = generateRandomString();

    // Takes our binary entropy, and concatenates a string which represents the current time to the microsecond
    $entropy .= uniqid(mt_rand(), true);

    // Hash the binary entropy
    $hash = hash('sha512', $entropy);

    // Chop and send the first 80 characters back to the client
    return substr($hash, 0, 48);
}