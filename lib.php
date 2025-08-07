<?php
defined('MOODLE_INTERNAL') || die();

function get_latest_organization_ID($apiurl){
    // latest_organization_ID required for create_organization
    $url = "$apiurl/api/v2/organizations";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);

    if (isset($data)){

        $objects = $data['data']['objects'];
        $maxId = 0;
        foreach ($objects as $obj) {
            if ($obj['id'] > $maxId) {
                $maxId = $obj['id'];
            }
        }
        return $maxId;
    } else {
        return -1;
    }
}
function find_dmoj_organization_id(int $moodle_course_id){
    global $CFG;

    $servername = SERVERNAME;
    $username = USERNAME;
    $password = PASSWORD;
    $dbname = DBNAME;

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $sql = "SELECT * FROM mdl_dmojorganize WHERE course_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $moodle_course_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $dmoj_organization_id_found = 0;
    if ($row = $result->fetch_assoc()) {
        $dmoj_organization_id_found = $row['organization_id'];
    }
    $stmt->close();
    $conn->close();

    return $dmoj_organization_id_found;
}
function dmojorganize_extend_navigation_course($navigation, $course, $context) {
    if (is_siteadmin() && has_capability('mod/dmojorganize:view', $context)) {
        $url = new moodle_url('/mod/dmojorganize/view.php', ['id' => $course->id]);
        $navigation->add(
            get_string('dmojsettings', 'mod_dmojorganize'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            null
        );
    }
}