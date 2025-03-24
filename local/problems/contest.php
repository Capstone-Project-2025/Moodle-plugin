<?php

require_once('../../config.php');

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], 'fullname');

if (!$course) {
    print_error('Invalid course ID');
}

$PAGE->set_url(new moodle_url('/local/problems/contest.php', ['courseid' => $courseid]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title("Course - " . format_string($course->fullname));
$PAGE->set_heading("Moodle Interface for contest of course :  " . format_string($course->fullname));

echo $OUTPUT->header();

echo "<h2>Course Name: " . format_string($course->fullname) . "</h2>";


$contests = [
    ['id' => 1, 'name' => 'Contest A', 'date' => '2025-03-25', 'status' => 'Active'],
    ['id' => 2, 'name' => 'Contest B', 'date' => '2025-03-28', 'status' => 'Completed'],
    ['id' => 3, 'name' => 'Contest C', 'date' => '2025-04-02', 'status' => 'Active'],
    ['id' => 4, 'name' => 'Contest D', 'date' => '2025-04-10', 'status' => 'Upcoming'],
];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contest List</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center">Contest List</h1>
        <table class="table table-striped mt-4">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contests as $contest) : ?>
                    <tr>
                        <td><?= $contest['id'] ?></td>
                        <td><?= htmlspecialchars($contest['name']) ?></td>
                        <td><?= $contest['date'] ?></td>
                        <td><?= $contest['status'] ?></td>
                        <td><a href="index.php" class="btn btn-primary btn-sm">View</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

<?php

echo $OUTPUT->footer();
?>
