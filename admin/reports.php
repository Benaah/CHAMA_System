<?php
// meetings.php
session_start();
if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit();
}
require 'config.php';

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $title = trim($_POST['title']);
    $meeting_date = trim($_POST['meeting_date']);
    $description = trim($_POST['description']);
    
    $stmt = $pdo->prepare("INSERT INTO meetings (title, meeting_date, description) VALUES (?, ?, ?)");
    if($stmt->execute([$title, $meeting_date, $description])){
        $success = "Meeting scheduled successfully.";
    } else {
        $error = "Error scheduling meeting.";
    }
}

// Retrieve meetings
$stmt = $pdo->query("SELECT * FROM meetings ORDER BY meeting_date DESC");
$meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Meeting Management - Agape Youth Group</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <a class="navbar-brand" href="dashboard.php">Dashboard</a>
</nav>
<div class="container mt-4">
  <h2>Schedule a Meeting</h2>
  <?php if(isset($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php elseif(isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="post" action="meetings.php">
    <div class="form-group">
      <label for="title">Meeting Title</label>
      <input type="text" name="title" class="form-control" id="title" required>
    </div>
    <div class="form-group">
      <label for="meeting_date">Date & Time</label>
      <input type="datetime-local" name="meeting_date" class="form-control" id="meeting_date" required>
    </div>
    <div class="form-group">
      <label for="description">Meeting Description</label>
      <textarea name="description" class="form-control" id="description"></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Schedule Meeting</button>
  </form>
  <hr>
  <h3>Upcoming Meetings</h3>
  <ul class="list-group">
    <?php foreach($meetings as $meeting): ?>
      <li class="list-group-item">
        <strong><?= htmlspecialchars($meeting['title']) ?></strong> on <?= htmlspecialchars($meeting['meeting_date']) ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
</body>
</html>
