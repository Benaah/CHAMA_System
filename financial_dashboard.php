<?php
// financial_dashboard.php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
require 'config.php';

// Fetch total contributions
$contribStmt = $pdo->query("SELECT SUM(amount) AS total_contributions FROM contributions_schedule WHERE paid_status IN ('paid', 'late')");
$contrib = $contribStmt->fetch(PDO::FETCH_ASSOC);

// Fetch total loans issued
$loanStmt = $pdo->query("SELECT SUM(amount) AS total_loans FROM loans WHERE status = 'active'");
$loan = $loanStmt->fetch(PDO::FETCH_ASSOC);

// Fetch total savings
$savingsStmt = $pdo->query("SELECT SUM(amount) AS total_savings FROM savings");
$savings = $savingsStmt->fetch(PDO::FETCH_ASSOC);

// Fetch total investments
$investStmt = $pdo->query("SELECT SUM(amount) AS total_investments FROM investments");
$investment = $investStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Financial Dashboard - Agape Youth Group</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script></head>
<body>
<nav class="navbar navbar-light bg-light">
  <a class="navbar-brand" href="dashboard.php">Dashboard</a>
</nav>
<div class="container mt-4">
  <h2>Financial Overview</h2>
  <div class="row">
    <div class="col-md-3">
      <div class="card text-white bg-success mb-3">
        <div class="card-header">Contributions</div>
        <div class="card-body">
          <h5 class="card-title">KES <?= number_format($contrib['total_contributions'] ?? 0, 2) ?></h5>
          <p class="card-text">Total contributions received.</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-white bg-danger mb-3">
        <div class="card-header">Loans</div>
        <div class="card-body">
          <h5 class="card-title">KES <?= number_format($loan['total_loans'] ?? 0, 2) ?></h5>
          <p class="card-text">Total active loans.</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-white bg-info mb-3">
        <div class="card-header">Savings</div>
        <div class="card-body">
          <h5 class="card-title">KES <?= number_format($savings['total_savings'] ?? 0, 2) ?></h5>
          <p class="card-text">Total savings.</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-white bg-warning mb-3">
        <div class="card-header">Investments</div>
        <div class="card-body">
          <h5 class="card-title">KES <?= number_format($investment['total_investments'] ?? 0, 2) ?></h5>
          <p class="card-text">Total investments made.</p>
        </div>
      </div>
    </div>
  </div>
  <!-- Financial Data Tables and Charts -->
  <div class="row mt-4">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          Monthly Contributions Trend
        </div>
        <div class="card-body">
          <canvas id="contributionsChart"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">
          Loan Distribution
        </div>
        <div class="card-body">
          <canvas id="loansChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="row mt-4">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header">
          Recent Transactions
        </div>
        <div class="card-body">
          <table class="table table-striped">
            <thead>
              <tr>
                <th>Date</th>
                <th>Transaction Type</th>
                <th>Member</th>
                <th>Amount (KES)</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($recent_transactions as $transaction): ?>
              <tr>
                <td><?= date('Y-m-d', strtotime($transaction['date'])) ?></td>
                <td><?= $transaction['type'] ?></td>
                <td><?= $transaction['member_name'] ?></td>
                <td><?= number_format($transaction['amount'], 2) ?></td>
                <td><span class="badge badge-<?= $transaction['status'] == 'Completed' ? 'success' : 'warning' ?>"><?= $transaction['status'] ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Monthly Contributions Chart
    const contributionsCtx = document.getElementById('contributionsChart').getContext('2d');
    new Chart(contributionsCtx, {
      type: 'line',
      data: {
        labels: <?= json_encode($monthly_labels) ?>,
        datasets: [{
          label: 'Monthly Contributions',
          data: <?= json_encode($monthly_contributions) ?>,
          borderColor: 'rgb(40, 167, 69)',
          tension: 0.1
        }]
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true
          }
        }
      }
    });

    // Loan Distribution Chart
    const loansCtx = document.getElementById('loansChart').getContext('2d');
    new Chart(loansCtx, {
      type: 'pie',
      data: {
        labels: <?= json_encode($loan_categories) ?>,
        datasets: [{
          data: <?= json_encode($loan_amounts) ?>,
          backgroundColor: [
            'rgb(220, 53, 69)',
            'rgb(255, 193, 7)',
            'rgb(23, 162, 184)',
            'rgb(40, 167, 69)'
          ]
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'bottom'
          }
        }
      }
    });
  </script></div>
</body>
</html>
