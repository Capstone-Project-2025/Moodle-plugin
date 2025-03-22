<?php
require_once('../../config.php');

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], 'fullname');

if (!$course) {
    print_error('Invalid course ID');
}

$PAGE->set_url(new moodle_url('/local/problems/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Problem Management');
$PAGE->set_heading("Moodle Interface for Problem Management - " . format_string($course->fullname));


echo $OUTPUT->header();
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="style.css">

<div class="container mt-5 text-center">
    <h1 class="fw-bold">Manage, edit, and test problems</h1>

    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-success" onclick="alert('Add feature coming soon!')">➕ Add Problem</button>
    </div>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Problem Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $problems = [
                ['id' => 1, 'name' => 'Array Rotation'],
                ['id' => 2, 'name' => 'Graph Traversal'],
                ['id' => 3, 'name' => 'Dynamic Programming'],
                ['id' => 4, 'name' => 'Sorting Algorithms'],
                ['id' => 5, 'name' => 'String Manipulation']
            ];

            foreach ($problems as $problem) {
                echo "<tr>";
                echo "<td>" . $problem['id'] . "</td>";
                echo "<td>" . $problem['name'] . "</td>";
                echo "<td>";
                echo "<button class='btn btn-warning btn-sm me-2' onclick='alert(\"Edit feature coming soon!\")'>✏️ Edit</button>";
                echo "<button class='btn btn-danger btn-sm me-2' onclick='confirmDelete()'>❌ Delete</button>";
                echo "<button class='btn btn-primary btn-sm' onclick='alert(\"Test cases feature coming soon!\")'>✅ Test Case</button>";
                echo "</td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function confirmDelete() {
        if (confirm("Are you sure you want to delete this problem? This action cannot be undone!")) {
            alert("Delete feature coming soon!");
        }
    }
</script>

<?php
echo $OUTPUT->footer();
?>
