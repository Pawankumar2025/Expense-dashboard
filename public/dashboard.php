<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Verify user exists
$userCheck = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$userCheck->execute([$user_id]);

if ($userCheck->rowCount() === 0) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle CSV Export
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="expenses_'.date('Y-m-d').'.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Description', 'Amount', 'Category', 'Date']);
    
    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = ? ORDER BY expense_date DESC");
    $stmt->execute([$user_id]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Add new expense
        if (isset($_POST['add_expense'])) {
            $description = trim($_POST['description']);
            $amount = floatval($_POST['amount']);
            $category = trim($_POST['category']);
            $date = $_POST['date'];

            $stmt = $pdo->prepare("INSERT INTO expenses (user_id, description, amount, category, expense_date)
                                 VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $description, $amount, $category, $date]);
            
            $_SESSION['message'] = "Expense added successfully!";
        }
        
        // Update existing expense
        if (isset($_POST['update_expense'])) {
            $expense_id = $_POST['expense_id'];
            $description = trim($_POST['description']);
            $amount = floatval($_POST['amount']);
            $category = trim($_POST['category']);
            $date = $_POST['date'];

            $stmt = $pdo->prepare("UPDATE expenses SET description = ?, amount = ?, category = ?, expense_date = ?
                                 WHERE id = ? AND user_id = ?");
            $stmt->execute([$description, $amount, $category, $date, $expense_id, $user_id]);
            
            $_SESSION['message'] = "Expense updated successfully!";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }
    header('Location: dashboard.php');
    exit;
}

// Handle delete action
if (isset($_GET['delete'])) {
    $expense_id = $_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
        $stmt->execute([$expense_id, $user_id]);
        
        $_SESSION['message'] = "Expense deleted successfully!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting expense: " . $e->getMessage();
    }
    header('Location: dashboard.php');
    exit;
}

// Check if editing an expense
$editing = false;
$edit_expense = null;
if (isset($_GET['edit'])) {
    $expense_id = $_GET['edit'];
    
    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ? AND user_id = ?");
    $stmt->execute([$expense_id, $user_id]);
    $edit_expense = $stmt->fetch();
    
    if ($edit_expense) {
        $editing = true;
    }
}

// Get data for charts
try {
    // Expenses by Category
    $categoryStmt = $pdo->prepare("
        SELECT category, SUM(amount) as total 
        FROM expenses 
        WHERE user_id = ? 
        GROUP BY category
        ORDER BY total DESC
    ");
    $categoryStmt->execute([$user_id]);
    $categoryData = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $categoryColors = [
        'Food' => '#FF6384',
        'Transport' => '#36A2EB',
        'Housing' => '#FFCE56',
        'Entertainment' => '#4BC0C0',
        'Utilities' => '#9966FF',
        'Other' => '#FF9F40'
    ];
    
    $chartCategories = [];
    $chartTotals = [];
    $chartColors = [];
    
    foreach ($categoryData as $category) {
        $chartCategories[] = $category['category'];
        $chartTotals[] = $category['total'];
        $chartColors[] = $categoryColors[$category['category']] ?? '#CCCCCC';
    }

    // Monthly Expenses (last 12 months)
    $monthlyStmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(expense_date, '%Y-%m') as month, 
            SUM(amount) as total
        FROM expenses
        WHERE user_id = ?
        AND expense_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(expense_date, '%Y-%m')
        ORDER BY month
    ");
    $monthlyStmt->execute([$user_id]);
    $monthlyResults = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $monthlyData = [];
    $currentMonth = date('Y-m');
    $startMonth = date('Y-m', strtotime('-11 months'));
    
    $period = new DatePeriod(
        new DateTime($startMonth),
        new DateInterval('P1M'),
        new DateTime($currentMonth)
    );
    
    foreach ($period as $date) {
        $monthlyData[$date->format('Y-m')] = 0;
    }
    
    foreach ($monthlyResults as $row) {
        $monthlyData[$row['month']] = $row['total'];
    }
    
    $chartMonths = array_keys($monthlyData);
    $chartMonthlyTotals = array_values($monthlyData);
    
} catch (PDOException $e) {
    error_log("Chart data error: " . $e->getMessage());
    $chartCategories = $chartTotals = $chartColors = [];
    $chartMonths = $chartMonthlyTotals = [];
}

// Fetch all expenses
$stmt = $pdo->prepare("SELECT * FROM expenses WHERE user_id = ? ORDER BY expense_date DESC");
$stmt->execute([$user_id]);
$expenses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Expense Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 2rem;
        }
        .action-btns { white-space: nowrap; }
    </style>
</head>
<body class="container mt-5">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between mb-4">
        <h2>Expense Dashboard</h2>
        <div>
            <a href="dashboard.php?export=1" class="btn btn-success me-2">
                <i class="bi bi-download"></i> Export CSV
            </a>
            <a href="logout.php" class="btn btn-outline-danger">Logout</a>
        </div>
    </div>

    <!-- Dashboard Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Expenses by Category</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Monthly Expenses</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Expense Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><?= $editing ? 'Edit Expense' : 'Add New Expense' ?></h5>
        </div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <?php if ($editing): ?>
                    <input type="hidden" name="update_expense" value="1">
                    <input type="hidden" name="expense_id" value="<?= $edit_expense['id'] ?>">
                <?php else: ?>
                    <input type="hidden" name="add_expense" value="1">
                <?php endif; ?>
                
                <div class="col-md-4">
                    <label for="description" class="form-label">Description</label>
                    <input type="text" class="form-control" id="description" name="description" 
                           value="<?= $editing ? htmlspecialchars($edit_expense['description']) : '' ?>" required>
                </div>
                
                <div class="col-md-2">
                    <label for="amount" class="form-label">Amount ($)</label>
                    <input type="number" step="0.01" class="form-control" id="amount" name="amount"
                           value="<?= $editing ? $edit_expense['amount'] : '' ?>" required>
                </div>
                
                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category" required>
                        <option value="">Select Category</option>
                        <option value="Food" <?= $editing && $edit_expense['category'] === 'Food' ? 'selected' : '' ?>>Food</option>
                        <option value="Transport" <?= $editing && $edit_expense['category'] === 'Transport' ? 'selected' : '' ?>>Transport</option>
                        <option value="Housing" <?= $editing && $edit_expense['category'] === 'Housing' ? 'selected' : '' ?>>Housing</option>
                        <option value="Entertainment" <?= $editing && $edit_expense['category'] === 'Entertainment' ? 'selected' : '' ?>>Entertainment</option>
                        <option value="Utilities" <?= $editing && $edit_expense['category'] === 'Utilities' ? 'selected' : '' ?>>Utilities</option>
                        <option value="Other" <?= $editing && $edit_expense['category'] === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="date" class="form-label">Date</label>
                    <input type="date" class="form-control" id="date" name="date"
                           value="<?= $editing ? $edit_expense['expense_date'] : date('Y-m-d') ?>" required>
                </div>
                
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <?= $editing ? 'Update' : 'Add' ?>
                    </button>
                </div>
                
                <?php if ($editing): ?>
                    <div class="col-md-1 d-flex align-items-end">
                        <a href="dashboard.php" class="btn btn-outline-secondary w-100">Cancel</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Expenses List -->
    <div class="card">
        <div class="card-header">
            <h5>Your Expenses</h5>
        </div>
        <div class="card-body">
            <?php if (count($expenses) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Category</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td><?= htmlspecialchars($expense['description']) ?></td>
                                    <td>$<?= number_format($expense['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($expense['category']) ?></td>
                                    <td><?= date('M j, Y', strtotime($expense['expense_date'])) ?></td>
                                    <td class="action-btns">
                                        <a href="dashboard.php?edit=<?= $expense['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <a href="dashboard.php?delete=<?= $expense['id'] ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to delete this expense?')">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Total Expenses -->
                <div class="mt-3 p-3 bg-light rounded">
                    <h5>Total Expenses: $<?= number_format(array_sum(array_column($expenses, 'amount')), 2) ?></h5>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No expenses found. Add your first expense above.</div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($chartCategories) ?>,
                datasets: [{
                    data: <?= json_encode($chartTotals) ?>,
                    backgroundColor: <?= json_encode($chartColors) ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: $${value.toFixed(2)} (${percentage}%)`;
                            }
                        }
                    },
                    legend: {
                        position: 'right'
                    }
                }
            }
        });

        // Monthly Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartMonths) ?>,
                datasets: [{
                    label: 'Monthly Expenses ($)',
                    data: <?= json_encode($chartMonthlyTotals) ?>,
                    borderColor: '#36A2EB',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    borderWidth: 2,
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '$' + context.raw.toFixed(2);
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>