<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: /todo_list/login.php?redirect=tasks.php");
    exit();
}

// Koneksi database (ganti sesuai konfigurasi Anda)
$conn = new mysqli('localhost', 'root', '', 'todo_list');
if ($conn->connect_error) die("DB Connection failed");

// Ambil user_id dari session
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    // Jika belum ada user_id di session, redirect ke login
    header("Location: /todo_list/login.php?redirect=tasks.php");
    exit();
}

// Cek koneksi database
if (!$conn || $conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $_SESSION['profile_name'] = trim($_POST['profile_name']);
    // upload gambar profil
    if (isset($_FILES['profile_img_file']) && $_FILES['profile_img_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['profile_img_file']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($ext, $allowed)) {
            $target = 'uploads/profile_' . session_id() . '.' . $ext;
            if (!is_dir('uploads')) mkdir('uploads');
            move_uploaded_file($_FILES['profile_img_file']['tmp_name'], $target);
            $_SESSION['profile_img'] = $target;
        }
    } elseif (!empty($_POST['profile_img'])) {
        $_SESSION['profile_img'] = trim($_POST['profile_img']);
    }
    header("Location: tasks.php");
    exit();
}

// Handle add task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $title = trim($_POST['title']);
    $desc = trim($_POST['desc'] ?? '');
    $due = trim($_POST['due'] ?? '');
    $due_time = trim($_POST['due_time'] ?? '');
    if (!empty($title)) {
        // Validasi input
        $stmt = $conn->prepare("INSERT INTO task (user_id, title, `desc`, due, due_time, created, checked) VALUES (?, ?, ?, ?, ?, NOW(), 0)");
        if (!$stmt) {
            die("Kolom pada tabel 'task' tidak sesuai. Pastikan ada kolom: user_id, title, desc, due, due_time, created, checked.");
        }
        $stmt->bind_param("issss", $user_id, $title, $desc, $due, $due_time);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle delete task
if (isset($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM task WHERE task_id=? AND user_id=?");
    $stmt->bind_param("ii", $deleteId, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: tasks.php");
    exit();
}

// Handle edit task
$editTask = null;
$editId = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM task WHERE task_id=? AND user_id=?");
    $stmt->bind_param("ii", $editId, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $editTask = $result->fetch_assoc();
    $stmt->close();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_task'])) {
    $id = intval($_POST['idx']);
    $title = trim($_POST['title']);
    $desc = trim($_POST['desc']);
    $due = trim($_POST['due']);
    $due_time = trim($_POST['due_time'] ?? '');
    $stmt = $conn->prepare("UPDATE task SET title=?, `desc`=?, due=?, due_time=? WHERE task_id=? AND user_id=?");
    $stmt->bind_param("ssssii", $title, $desc, $due, $due_time, $id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: tasks.php");
    exit();
}

// Handle check/uncheck task
if (isset($_GET['toggle'])) {
    $toggleId = intval($_GET['toggle']);
    $stmt = $conn->prepare("SELECT checked FROM task WHERE task_id=? AND user_id=?");
    $stmt->bind_param("ii", $toggleId, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $checked = !empty($row['checked']) ? 0 : 1;
    $stmt->close();
    $stmt = $conn->prepare("UPDATE task SET checked=? WHERE task_id=? AND user_id=?");
    $stmt->bind_param("iii", $checked, $toggleId, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: tasks.php?cat=" . urlencode($_GET['cat'] ?? 'All'));
    exit();
}

// Helper: status jatuh tempo
function get_due_status($due) {
    if (!$due) return 'No Due Date';
    $today = date('Y-m-d');
    if ($due < $today) return 'Overdue';
    if ($due == $today) return 'Today';
    if ($due == date('Y-m-d', strtotime('+1 day'))) return 'Tomorrow';
    if ($due == date('Y-m-d', strtotime('+2 day'))) return 'After Tomorrow';
    return 'Upcoming';
}

// Sidebar categories (hanya All dan Completed)
$categories = [
    'All', 'Completed'
];

// Ambil semua tasks user dari database
$tasks = [];
// Pastikan nama tabel sesuai dengan database Anda
$res = $conn->query("SHOW TABLES LIKE 'task'");
if ($res && $res->num_rows == 0) {
    die("Tabel 'task' tidak ditemukan di database. Silakan cek nama tabel di database Anda.");
}
$res = $conn->query("SELECT * FROM task WHERE user_id=" . intval($user_id));
while ($row = $res->fetch_assoc()) {
    $tasks[] = $row;
}

// Filter tasks berdasarkan kategori yang dipilih
$selected_cat = $_GET['cat'] ?? 'All';
$filtered_tasks = [];
foreach ($tasks as $task) {
    $status = get_due_status($task['due']);
    $checked = !empty($task['checked']);
    if (
        ($selected_cat == 'All' && ($status == 'Upcoming' || $status == 'Overdue' || $status == 'Today' || $status == 'Tomorrow' || $status == 'After Tomorrow' || $status == 'No Due Date') && !$checked)
        || ($selected_cat == 'Completed' && $checked)
    ) {
        $task['_idx'] = $task['task_id'];
        $filtered_tasks[] = $task;
    }
}

// sort tasks
$sort = $_GET['sort'] ?? 'created_desc';
if (!empty($filtered_tasks)) {
    if ($sort === 'title_asc') {
        usort($filtered_tasks, fn($a, $b) => strcmp($a['title'], $b['title']));
    } elseif ($sort === 'title_desc') {
        usort($filtered_tasks, fn($a, $b) => strcmp($b['title'], $a['title']));
    } elseif ($sort === 'due_asc') {
        usort($filtered_tasks, fn($a, $b) => strcmp($a['due'], $b['due']));
    } elseif ($sort === 'due_desc') {
        usort($filtered_tasks, fn($a, $b) => strcmp($b['due'], $a['due']));
    } else { // created_desc
        usort($filtered_tasks, fn($a, $b) => strcmp($b['created'], $a['created']));
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['user']);
    unset($_SESSION['user_id']);
    header("Location: login.php");
    exit();
}

// Profile
$profile_name = $_SESSION['profile_name'] ?? $_SESSION['user'];
$profile_img = $_SESSION['profile_img'] ?? 'https://www.rukita.co/stories/wp-content/uploads/2022/08/karakter-anime-terimut.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
    <style>
        html, body {
            height: 100%;
        }
        body {
            background: radial-gradient(ellipse at bottom, #1b2735 0%, #090a1a 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            position: relative;
            overflow-x: hidden;
        }
        /* Efek bintang */
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            pointer-events: none;
            z-index: 0;
            background: transparent url('https://raw.githubusercontent.com/JulianLaval/canvas-space-background/master/images/stars.png') repeat;
            opacity: 0.25;
            animation: moveStars 120s linear infinite;
        }
        @keyframes moveStars {
            0% { background-position: 0 0; }
            100% { background-position: 1000px 1000px; }
        }
        .main-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            background: transparent;
            position: relative;
            z-index: 1;
        }
        .sidebar {
            background: rgba(24, 29, 56, 0.97);
            min-width: 250px;
            max-width: 270px;
            height: 90vh;
            border-radius: 16px 0 0 16px;
            box-shadow: 0 4px 32px 0 rgba(44, 62, 80, 0.25), 0 0 24px #3a1c71 inset;
            padding: 0;
            display: flex;
            flex-direction: column;
            border-right: 2px solid #3a1c71;
        }
        .sidebar .profile {
            padding: 24px 20px 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-direction: column;
        }
        .sidebar .profile-img {
            width: 110px; height: 110px; border-radius: 50%; object-fit: cover;
            box-shadow: 0 2px 16px 2px #ffe066, 0 0 0 4px #3a1c71 inset;
            border: 3px solid #ffe066;
            background: #222;
        }
        .sidebar .profile-name {
            font-weight: 700;
            font-size: 1.15rem;
            color: #ffe066;
            text-shadow: 0 0 8px #3a1c71, 0 0 2px #fff;
        }
        .sidebar .list-group {
            border-radius: 0;
            border: none;
        }
        .sidebar .list-group-item {
            border: none;
            border-radius: 0;
            background: none;
            color: #e0e6ff;
            font-size: 1rem;
            padding: 10px 24px;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
        }
        .sidebar .list-group-item.active, .sidebar .list-group-item:active {
            background: linear-gradient(90deg, #3a1c71 0%, #5e3be1 100%);
            color: #ffe066;
            font-weight: 700;
            box-shadow: 0 0 12px #3a1c71;
        }
        .sidebar .list-group-item:hover {
            background: rgba(58,28,113,0.18);
            color: #ffe066;
        }
        .sidebar .sidebar-footer {
            margin-top: auto;
            padding: 16px 24px;
            border-top: 1px solid #3a1c71;
            display: none;
        }
        .content-area {
            flex: 1;
            background: rgba(34, 40, 80, 0.92);
            min-height: 90vh;
            padding: 0;
            border-radius: 0 16px 16px 0;
            box-shadow: 0 4px 32px 0 rgba(44, 62, 80, 0.18);
            display: flex;
            border-left: 2px solid #3a1c71;
        }
        .tasks-panel {
            flex: 2;
            padding: 36px 32px 32px 32px;
            border-right: 1px solid #3a1c71;
            min-width: 350px;
        }
        .tasks-panel h3 {
            font-weight: 700;
            color: #ffe066;
            text-shadow: 0 0 8px #3a1c71;
        }
        .tasks-panel .new-task-box {
            background: rgba(58,28,113,0.13);
            border-radius: 8px;
            padding: 12px 18px;
            margin-bottom: 18px;
            border: 1px solid #5e3be1;
            box-shadow: 0 2px 8px #3a1c711a;
        }
        .tasks-list {
            margin-bottom: 24px;
        }
        .tasks-list .task-item {
            background: rgba(24, 29, 56, 0.98);
            border-radius: 8px;
            margin-bottom: 8px;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid #3a1c71;
            transition: background 0.15s, box-shadow 0.2s;
            box-shadow: 0 2px 8px #3a1c711a;
        }
        .tasks-list .task-item:hover {
            background: #3a1c71;
            box-shadow: 0 0 16px #ffe06688;
        }
        .tasks-list .task-title {
            font-weight: 500;
            color: #ffe066;
            text-shadow: 0 0 4px #3a1c71;
        }
        .tasks-list .task-title.checked {
            text-decoration: line-through;
            color: #b0b0b0;
            opacity: 0.7;
        }
        .tasks-list .task-actions .btn {
            margin-left: 6px;
            background: #232a36;
            border: 1px solid #ffe066;
            color: #ffe066;
            transition: background 0.2s, color 0.2s;
        }
        .tasks-list .task-actions .btn:hover {
            background: #ffe066;
            color: #3a1c71;
            border-color: #ffe066;
        }
        /* === Tanggal & waktu pada task lebih jelas === */
        .task-date-badge {
            display: inline-block;
            background: #ffe066;
            color: #3a1c71;
            font-weight: bold;
            font-size: 1.08em;
            border-radius: 8px;
            padding: 4px 12px 4px 10px;
            margin-left: 12px;
            margin-right: 4px;
            box-shadow: 0 0 12px #ffe06699, 0 0 2px #fff;
            border: 2px solid #3a1c71;
            letter-spacing: 0.5px;
            vertical-align: middle;
        }
        .task-time-badge {
            display: inline-block;
            background: #5e3be1;
            color: #ffe066;
            font-weight: bold;
            font-size: 1.08em;
            border-radius: 8px;
            padding: 4px 10px 4px 10px;
            margin-left: 4px;
            box-shadow: 0 0 10px #5e3be199;
            border: 2px solid #ffe066;
            letter-spacing: 0.5px;
            vertical-align: middle;
        }
        /* Untuk detail panel  */
        .detail-date .task-date-badge,
        .detail-date .task-time-badge {
            margin-left: 0;
            margin-right: 8px;
        }
        .detail-panel {
            flex: 1.2;
            padding: 36px 24px 32px 24px;
            background: rgba(24, 29, 56, 0.98);
            border-radius: 0 16px 16px 0;
            min-width: 300px;
            box-shadow: 0 0 24px #3a1c711a;
            color: #e0e6ff;
        }
        .detail-panel .detail-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #ffe066;
            text-shadow: 0 0 8px #3a1c71;
        }
        .detail-panel .detail-desc {
            margin: 18px 0;
            color: #e0e6ff;
        }
        .detail-panel .detail-date {
            color: #ffe066;
            font-size: 0.98rem;
        }
        .profile-edit-btn {
            background: none;
            border: none;
            color: #ffe066;
            font-size: 1.1rem;
            margin-left: 6px;
            cursor: pointer;
            text-shadow: 0 0 4px #3a1c71;
        }
        .profile-edit-btn:hover { color: #fff; }
        .modal-backdrop.show { opacity: 0.25; }
        .sort-btn-group .btn {
            font-size: 0.98rem;
            background: #232a36;
            color: #ffe066;
            border: 1px solid #3a1c71;
        }
        .sort-btn-group .btn.active, .sort-btn-group .btn:active {
            background: #ffe066;
            color: #3a1c71;
            border-color: #ffe066;
        }
        .due-status {
            font-size: 0.92rem;
            font-weight: 500;
            padding: 2px 10px;
            border-radius: 12px;
            margin-left: 8px;
            box-shadow: 0 0 8px #ffe06644;
        }
        .due-status.Overdue { background: #ff6b6b; color: #fff; box-shadow: 0 0 8px #ff6b6b88; }
        .due-status.Today { background: #ffe066; color: #3a1c71; box-shadow: 0 0 8px #ffe06688; }
        .due-status.Tomorrow { background: #5e3be1; color: #ffe066; }
        .due-status.AfterTomorrow, .due-status.After { background: #3a1c71; color: #ffe066; }
        .due-status.Upcoming { background: #232a36; color: #ffe066; }
        .due-status['No Due Date'] { background: #232a36; color: #b0b0b0; }
        .due-status.checked { opacity: 0.6; }
        /* Badge style for sidebar */
        .sidebar .badge {
            background: #232a36;
            color: #ffe066;
            font-weight: 600;
            border: 1px solid #3a1c71;
            box-shadow: 0 0 4px #3a1c71;
        }
        /* Input & modal style */
        .form-control, .modal-content {
            background: #232a36;
            color: #ffe066;
            border: 1px solid #3a1c71;
        }
        .form-control:focus {
            border-color: #ffe066;
            box-shadow: 0 0 8px #ffe06688;
            background: #232a36;
            color: #ffe066;
        }
        .modal-content {
            background: #232a36;
            color: #ffe066;
            border: 2px solid #3a1c71;
        }
        .modal-title, .form-label {
            color: #ffe066;
        }
        .btn-primary {
            background: linear-gradient(90deg, #3a1c71 0%, #5e3be1 100%);
            border: none;
            color: #ffe066;
            font-weight: 700;
            box-shadow: 0 0 8px #3a1c71;
        }
        .btn-primary:hover {
            background: #ffe066;
            color: #3a1c71;
        }
        .btn-warning {
            background: #ffe066;
            color: #3a1c71;
            border: none;
            font-weight: 700;
        }
        .btn-warning:hover {
            background: #fff;
            color: #3a1c71;
        }
        .btn-danger {
            background: #ff6b6b;
            color: #fff;
            border: none;
            font-weight: 700;
        }
        .btn-danger:hover {
            background: #fff;
            color: #ff6b6b;
        }
        .btn-secondary {
            background: #232a36;
            color: #ffe066;
            border: 1px solid #3a1c71;
        }
        .btn-secondary:hover {
            background: #ffe066;
            color: #3a1c71;
        }
        /* Scrollbar angkasa */
        ::-webkit-scrollbar {
            width: 10px;
            background: #232a36;
        }
        ::-webkit-scrollbar-thumb {
            background: #3a1c71;
            border-radius: 8px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #5e3be1;
        }
        @media (max-width: 1100px) {
            .main-wrapper { flex-direction: column; }
            .sidebar, .content-area { border-radius: 0; width: 100vw; min-width: 0; }
            .content-area { flex-direction: column; }
            .tasks-panel, .detail-panel { min-width: 0; width: 100vw; }
            .tasks-panel { border-right: none; border-bottom: 1px solid #3a1c71; }
        }
        /* Efek glow pada elemen penting */
        .tasks-panel h3, .detail-panel .detail-title, .sidebar .profile-name {
            text-shadow: 0 0 12px #ffe066, 0 0 2px #fff;
        }
        /* Mark highlight pencarian */
        mark {
            background: #ffe066;
            color: #3a1c71;
            border-radius: 4px;
            padding: 0 2px;
        }
    </style>
</head>
<body>
<div class="main-wrapper">
    <div class="sidebar">
        <div class="profile flex-column align-items-center text-center">
            <img src="<?php echo htmlspecialchars($profile_img); ?>" class="profile-img mb-2" alt="profile">
            <span class="profile-name mb-1"><?php echo htmlspecialchars($profile_name); ?></span>
            <button class="profile-edit-btn mb-2" title="Edit Profile" data-bs-toggle="modal" data-bs-target="#profileModal">
                <i class="fa fa-pen"></i>
            </button>
        </div>
        <div class="px-3 pb-2">
            <input type="text" class="form-control" placeholder="Search" style="font-size:0.98rem;" id="searchTaskInput">
        </div>
        <ul class="list-group list-group-flush flex-grow-1">
            <?php foreach ($categories as $cat): ?>
                <?php
                // Hitung jumlah task untuk setiap kategori
                $cat_count = 0;
                foreach ($tasks as $t) {
                    $status = get_due_status($t['due']);
                    $checked = !empty($t['checked']);
                    if (
                        ($cat == 'All' && ($status == 'Upcoming' || $status == 'Overdue' || $status == 'Today' || $status == 'Tomorrow' || $status == 'After Tomorrow' || $status == 'No Due Date') && !$checked)
                        || ($cat == 'Completed' && $checked)
                    ) $cat_count++;
                }
                ?>
                <a href="?cat=<?php echo urlencode($cat); ?>" class="list-group-item<?php echo ($selected_cat == $cat) ? ' active' : ''; ?>">
                    <?php echo $cat; ?>
                    <span class="float-end badge bg-light text-dark"><?php echo $cat_count; ?></span>
                </a>
            <?php endforeach; ?>
        </ul>
        <!-- Sidebar footer is now hidden -->
    </div>
    <div class="content-area">
        <div class="tasks-panel">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h3 class="mb-0"><?php echo htmlspecialchars($selected_cat); ?></h3>
                <div class="sort-btn-group btn-group">
                    <a href="?cat=<?php echo urlencode($selected_cat); ?>&sort=created_desc" class="btn btn-outline-secondary<?php if($sort=='created_desc') echo ' active'; ?>" title="Terbaru"><i class="fa fa-clock"></i></a>
                    <a href="?cat=<?php echo urlencode($selected_cat); ?>&sort=title_asc" class="btn btn-outline-secondary<?php if($sort=='title_asc') echo ' active'; ?>" title="A-Z"><i class="fa fa-sort-alpha-down"></i></a>
                    <a href="?cat=<?php echo urlencode($selected_cat); ?>&sort=title_desc" class="btn btn-outline-secondary<?php if($sort=='title_desc') echo ' active'; ?>" title="Z-A"><i class="fa fa-sort-alpha-up"></i></a>
                    <a href="?cat=<?php echo urlencode($selected_cat); ?>&sort=due_asc" class="btn btn-outline-secondary<?php if($sort=='due_asc') echo ' active'; ?>" title="Due ↑"><i class="fa fa-calendar-day"></i></a>
                    <a href="?cat=<?php echo urlencode($selected_cat); ?>&sort=due_desc" class="btn btn-outline-secondary<?php if($sort=='due_desc') echo ' active'; ?>" title="Due ↓"><i class="fa fa-calendar-alt"></i></a>
                </div>
            </div>
            <?php if ($editTask !== null): ?>
            <form method="POST" action="" class="new-task-box mb-3">
                <input type="hidden" name="idx" value="<?php echo $editId; ?>">
                <div class="mb-2">
                    <input type="text" class="form-control mb-2" name="title" value="<?php echo htmlspecialchars($editTask['title']); ?>" required placeholder="Edit title...">
                    <textarea class="form-control mb-2" name="desc" rows="2" required placeholder="Edit description..."><?php echo htmlspecialchars($editTask['desc']); ?></textarea>
                    <div class="row g-2">
                        <div class="col-7">
                            <input type="date" class="form-control mb-2" name="due" value="<?php echo htmlspecialchars($editTask['due']); ?>">
                        </div>
                        <div class="col-5">
                            <input type="time" class="form-control mb-2" name="due_time" value="<?php echo isset($editTask['due_time']) ? htmlspecialchars($editTask['due_time']) : ''; ?>">
                        </div>
                    </div>
                </div>
                <button type="submit" name="edit_task" class="btn btn-warning w-100 shadow-sm mb-2"><i class="fa fa-edit me-1"></i>Update Task</button>
                <a href="tasks.php?cat=<?php echo urlencode($selected_cat); ?>" class="btn btn-secondary w-100">Cancel</a>
            </form>
            <?php else: ?>
            <form method="POST" action="" class="new-task-box d-flex flex-column gap-2 mb-3">
                <input type="text" class="form-control" name="title" placeholder="New task..." required>
                <textarea class="form-control" name="desc" rows="2" placeholder="Description..."></textarea>
                <div class="row g-2">
                    <div class="col-7">
                        <input type="date" class="form-control" name="due">
                    </div>
                    <div class="col-5">
                        <input type="time" class="form-control" name="due_time">
                    </div>
                </div>
                <button type="submit" name="add_task" class="btn btn-primary mt-2"><i class="fa fa-plus"></i> Add Task</button>
            </form>
            <?php endif; ?>
            <div class="tasks-list" id="tasksList">
                <?php if (!empty($filtered_tasks)): ?>
                    <?php foreach ($filtered_tasks as $task): ?>
                        <div class="task-item" data-title="<?php echo htmlspecialchars(strtolower($task['title'])); ?>">
                            <div>
                                <form method="get" action="" style="display:inline;">
                                    <input type="hidden" name="toggle" value="<?php echo $task['_idx']; ?>">
                                    <input type="hidden" name="cat" value="<?php echo htmlspecialchars($selected_cat); ?>">
                                    <input type="checkbox" class="form-check-input me-2" <?php if(!empty($task['checked'])) echo 'checked'; ?> onchange="this.form.submit()">
                                </form>
                                <span class="task-title<?php if(!empty($task['checked'])) echo ' checked'; ?>"><?php echo htmlspecialchars($task['title']); ?></span>
                                <?php
                                    $status = get_due_status($task['due']);
                                    $status_class = str_replace(' ', '', $status);
                                ?>
                                <span class="due-status <?php echo $status_class; if(!empty($task['checked'])) echo ' checked'; ?>"><?php echo $status; ?></span>
                                <?php if (!empty($task['due'])): ?>
                                    <span class="task-date-badge">
                                        <i class="fa fa-calendar"></i>
                                        <?php echo date('d M Y', strtotime($task['due'])); ?>
                                    </span>
                                    <?php if (!empty($task['due_time'])): ?>
                                        <span class="task-time-badge">
                                            <i class="fa fa-clock"></i>
                                            <?php echo htmlspecialchars($task['due_time']); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div class="task-actions">
                                <a href="?edit=<?php echo $task['_idx']; ?>&cat=<?php echo urlencode($selected_cat); ?>" class="btn btn-warning btn-sm"><i class="fa fa-edit"></i></a>
                                <a href="?delete=<?php echo $task['_idx']; ?>&cat=<?php echo urlencode($selected_cat); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus tugas ini?')"><i class="fa fa-trash"></i></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-secondary text-center py-3">No tasks yet.</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="detail-panel">
            <?php
            $showTask = $editTask ?? ($filtered_tasks[0] ?? null);
            if ($showTask):
            ?>
                <div class="detail-title"><?php echo htmlspecialchars($showTask['title']); ?></div>
                <div class="detail-desc"><?php echo nl2br(htmlspecialchars($showTask['desc'] ?? '')); ?></div>
                <div class="detail-date">
                    <?php
                        if (!empty($showTask['due'])) {
                            echo '<span class="task-date-badge"><i class="fa fa-calendar"></i> ' . date('d M Y', strtotime($showTask['due'])) . '</span>';
                        } else {
                            echo '<span class="task-date-badge">-</span>';
                        }
                        if (!empty($showTask['due_time'])) {
                            echo '<span class="task-time-badge"><i class="fa fa-clock"></i> ' . htmlspecialchars($showTask['due_time']) . '</span>';
                        } elseif (!empty($showTask['created'])) {
                            echo '<span class="task-time-badge"><i class="fa fa-clock"></i> ' . date('H:i', strtotime($showTask['created'])) . '</span>';
                        }
                    ?>
                </div>
                <div class="mt-2">
                    <?php
                        $status = get_due_status($showTask['due']);
                        $status_class = str_replace(' ', '', $status);
                    ?>
                    <span class="due-status <?php echo $status_class; ?>"><?php echo $status; ?></span>
                </div>
                <?php
                // Show up to 3 upcoming tasks (excluding current detail)
                $upcoming = [];
                foreach ($tasks as $t) {
                    if (get_due_status($t['due']) == 'Upcoming' && empty($t['checked']) && $t !== $showTask) {
                        $upcoming[] = $t;
                        if (count($upcoming) >= 3) break;
                    }
                }
                if (!empty($upcoming)): ?>
                <div class="mt-4">
                    <div class="fw-bold mb-2" style="color:#2575fc;">Upcoming Tasks</div>
                    <?php foreach ($upcoming as $utask): ?>
    <div class="mb-1">
        <span class="fw-semibold"><?php echo htmlspecialchars($utask['title']); ?></span>
        <span class="due-status Upcoming ms-1">Upcoming</span>
        <?php if (!empty($utask['due'])): ?>
            <span class="task-date-badge">
                <i class="fa fa-calendar"></i>
                <?php echo date('d M Y', strtotime($utask['due'])); ?>
            </span>
            <?php if (!empty($utask['due_time'])): ?>
                <span class="task-time-badge">
                    <i class="fa fa-clock"></i>
                    <?php echo htmlspecialchars($utask['due_time']); ?>
                </span>
            <?php endif; ?>
        <?php endif; ?>
    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-secondary">Select a task to see details.</div>
            <?php endif; ?>
            <a href="?logout=true" class="btn btn-danger w-100 mt-4 logout-btn shadow-sm"><i class="fa fa-sign-out-alt me-1"></i>Logout</a>
        </div>
    </div>
</div>

<!-- Profile Edit  -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title" id="profileModalLabel">Edit Profile</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Profile Name</label>
            <input type="text" class="form-control" name="profile_name" value="<?php echo htmlspecialchars($profile_name); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Profile Image URL</label>
            <input type="text" class="form-control mb-2" name="profile_img" value="<?php echo htmlspecialchars($profile_img); ?>">
            <small class="text-muted">Or upload from device:</small>
            <input type="file" class="form-control" name="profile_img_file" accept="image/*">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="update_profile" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Bootstrap JS & Interactivity -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('searchTaskInput').addEventListener('input', function() {
    var val = this.value.toLowerCase();
    document.querySelectorAll('#tasksList .task-item').forEach(function(item) {
        item.style.display = item.getAttribute('data-title').includes(val) ? '' : 'none';
    });
});

// Highlight task yang akan jatuh tempo hari ini dengan animasi blink
document.querySelectorAll('.task-item').forEach(function(item) {
    var dueStatus = item.querySelector('.due-status');
    if (dueStatus && dueStatus.textContent.trim() === 'Today') {
        item.style.animation = "blink 1s linear infinite";
    }
});
const style = document.createElement('style');
style.innerHTML = `
@keyframes blink {
    0% { box-shadow: 0 0 0px #2575fc; }
    50% { box-shadow: 0 0 16px #2575fc; }
    100% { box-shadow: 0 0 0px #2575fc; }
}
`;
document.head.appendChild(style);

// Tombol "Scroll to Top" muncul saat scroll ke bawah
const scrollBtn = document.createElement('button');
scrollBtn.innerHTML = '<i class="fa fa-arrow-up"></i>';
scrollBtn.style.position = 'fixed';
scrollBtn.style.right = '32px';
scrollBtn.style.bottom = '32px';
scrollBtn.style.display = 'none';
scrollBtn.style.zIndex = 9999;
scrollBtn.className = 'btn btn-primary rounded-circle shadow';
scrollBtn.title = 'Scroll to Top';
scrollBtn.onclick = function() { window.scrollTo({top:0,behavior:'smooth'}); };
document.body.appendChild(scrollBtn);

window.addEventListener('scroll', function() {
    scrollBtn.style.display = (window.scrollY > 120) ? 'block' : 'none';
});

// Fitur: Animasi confetti saat menandai task selesai
function confettiAt(x, y) {
    for (let i = 0; i < 30; i++) {
        let conf = document.createElement('div');
        conf.style.position = 'fixed';
        conf.style.left = (x + Math.random()*40-20) + 'px';
        conf.style.top = (y + Math.random()*20-10) + 'px';
        conf.style.width = conf.style.height = (Math.random()*8+4) + 'px';
        conf.style.background = `hsl(${Math.random()*360},90%,60%)`;
        conf.style.borderRadius = '50%';
        conf.style.opacity = 0.8;
        conf.style.zIndex = 99999;
        conf.style.pointerEvents = 'none';
        document.body.appendChild(conf);
        let dx = (Math.random()-0.5)*2, dy = Math.random()*-2-1;
        let gravity = 0.08 + Math.random()*0.04;
        let life = 0;
        function animate() {
            if (life++ > 40) return conf.remove();
            conf.style.left = (parseFloat(conf.style.left) + dx) + 'px';
            conf.style.top = (parseFloat(conf.style.top) + dy) + 'px';
            dy += gravity;
            requestAnimationFrame(animate);
        }
        animate();
    }
}
document.querySelectorAll('.tasks-list .task-item input[type=checkbox]').forEach(function(box) {
    box.addEventListener('change', function(e) {
        if (box.checked) {
            let rect = box.getBoundingClientRect();
            confettiAt(rect.left+10, rect.top+10);
        }
    });
});

// Fitur: Tooltip pada tombol aksi
document.querySelectorAll('.task-actions .btn').forEach(function(btn) {
    btn.setAttribute('data-bs-toggle', 'tooltip');
    btn.setAttribute('data-bs-placement', 'top');
});
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
tooltipTriggerList.forEach(function (tooltipTriggerEl) {
    new bootstrap.Tooltip(tooltipTriggerEl);
});

// Fitur: Highlight pencarian
document.getElementById('searchTaskInput').addEventListener('input', function() {
    var val = this.value.toLowerCase();
    document.querySelectorAll('#tasksList .task-title').forEach(function(span) {
        var text = span.textContent;
        if (val && text.toLowerCase().includes(val)) {
            span.innerHTML = text.replace(new RegExp('('+val+')','gi'), '<mark>$1</mark>');
        } else {
            span.innerHTML = text;
        }
    });
});
</script>
</body>
</html>

<?php
// Tambahkan agar waktu bisa disimpan saat tambah/edit task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
   
    $due_time = trim($_POST['due_time'] ?? '');
    if (!empty($title)) {
        $stmt = $conn->prepare("INSERT INTO task (user_id, title, `desc`, due, due_time, created, checked) VALUES (?, ?, ?, ?, ?, NOW(), 0)");
        $stmt->bind_param("issss", $user_id, $title, $desc, $due, $due_time);
        $stmt->execute();
        $stmt->close();
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_task'])) {

    $due_time = trim($_POST['due_time'] ?? '');
    $stmt = $conn->prepare("UPDATE task SET title=?, `desc`=?, due=?, due_time=? WHERE task_id=? AND user_id=?");
    $stmt->bind_param("ssssii", $title, $desc, $due, $due_time, $id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: tasks.php");
    exit();
}
?>
