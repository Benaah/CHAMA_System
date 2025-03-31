<?php
// view_audit_logs.php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
require 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Audit Trail - Agape Youth Group</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
  <a class="navbar-brand" href="dashboard.php">Dashboard</a>
</nav>
<div class="container mt-4">
  <h2>Audit Logs</h2>
  <table class="table table-striped">
    <thead>
      <tr>
        <th>ID</th>
        <th>User ID</th>
        <th>Action</th>
        <th>Details</th>
        <th>Event Date</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $stmt = $pdo->query("SELECT * FROM audit_logs ORDER BY event_date DESC");
      while ($log = $stmt->fetch(PDO::FETCH_ASSOC)) {
          echo "<tr>
                  <td>" . htmlspecialchars($log['id']) . "</td>
                  <td>" . htmlspecialchars($log['user_id']) . "</td>
                  <td>" . htmlspecialchars($log['action']) . "</td>
                  <td>" . htmlspecialchars($log['details']) . "</td>
                  <td>" . htmlspecialchars($log['event_date']) . "</td>
                </tr>";
      }
      ?>
    </tbody>
  </table>
</div>
</body>
</html>
