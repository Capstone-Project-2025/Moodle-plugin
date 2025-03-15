<?php
require_once('../../config.php');

$PAGE->set_url(new moodle_url('/local/problems/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Problem Management');
$PAGE->set_heading('Moodle Interface for Problem Management');

echo $OUTPUT->header();
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="style.css">

<div class="container mt-5 text-center">
    <h1 class="fw-bold">Moodle Interface for Problem Management</h1>
    <p class="lead">Upload, edit problems and test cases.</p>

    <div class="row mt-4">
        <div class="col-md-4">
            <button class="btn btn-primary w-100 py-3" onclick="alert('Upload Feature Coming Soon!')">
                📂 Upload Problem
            </button>
        </div>
        <div class="col-md-4">
            <button class="btn btn-warning w-100 py-3" onclick="alert('Edit Feature Coming Soon!')">
                ✏️ Edit Problem
            </button>
        </div>
        <div class="col-md-4">
            <button class="btn btn-success w-100 py-3" onclick="alert('Test Feature Coming Soon!')">
                ✅ Test Case
            </button>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6 offset-md-3">
            <button class="btn btn-danger w-100 py-3" onclick="confirmDelete()">
                ❌ Delete Problem
            </button>
        </div>
    </div>
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
