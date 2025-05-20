<?php
require_once('../../config.php');

$PAGE->set_url(new moodle_url('/mod/programmingassign/dmoj_login.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Login to DMOJ');
$PAGE->set_heading('Login to DMOJ');

echo $OUTPUT->header();

// Affichage du formulaire
echo '
    <form method="post">
        <label>Username:</label><br>
        <input type="text" name="username" required><br><br>
        <label>Password:</label><br>
        <input type="password" name="password" required><br><br>
        <button type="submit">Get Token</button>
    </form>
';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiurl = 'http://139.59.105.152'; // Remplace ici par l'URL réelle de ton DMOJ
    $username = required_param('username', PARAM_RAW);
    $password = required_param('password', PARAM_RAW);

    $data = json_encode([
        'username' => $username,
        'password' => $password
    ]);

    $curl = curl_init("$apiurl/api/token/");
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $data
    ]);

    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpcode === 200) {
        $tokens = json_decode($response, true);
        echo html_writer::div('<strong>Access Token:</strong> ' . s($tokens['access']), 'alert alert-success');
    } else {
        echo html_writer::div('Authentication failed. Check credentials.', 'alert alert-danger');
    }
}

echo $OUTPUT->footer();


//adress : http://139.59.105.152
//login : basicuser1
//Password : RoadRageWarrior1!