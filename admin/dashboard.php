<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone to Philippines (adjust this to your actual timezone)
date_default_timezone_set('Asia/Manila');

// Admin authentication check (before including other files)
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['username'])) {
    header('Location: login.php?error=Please login to access admin panel');
    exit();
}

// Include database connection (admin folder is inside root, so go up one level)
require_once '../includes/db.php';

// Get dashboard statistics
try {
    // Total Students (users table)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    $totalStudents = $stmt->fetch()['total'];

    // Books borrowed today (updated for new structure)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM borrowed_books WHERE DATE(borrow_date) = CURDATE() AND status = 'borrowed'");
    $stmt->execute();
    $borrowedToday = $stmt->fetch()['total'];

    // Total book copies available (updated for new structure)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM book_copies WHERE status = 'Available'");
    $stmt->execute();
    $totalAvailableCopies = $stmt->fetch()['total'];

    // Total book titles available
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM book_titles WHERE available_copies > 0");
    $stmt->execute();
    $totalAvailableTitles = $stmt->fetch()['total'];

    // Archived books count (updated for new structure)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM book_copies WHERE status = 'Archived'");
    $stmt->execute();
    $archivedBooks = $stmt->fetch()['total'];

    // Recent activities (updated for new structure with proper joins and better time handling)
    $stmt = $conn->prepare("
        SELECT 
            'borrow' as activity_type,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            bt.title as book_title,
            bc.book_id as book_copy_id,
            bb.borrow_date,
            bb.created_at,
            UNIX_TIMESTAMP(bb.created_at) as activity_timestamp
        FROM borrowed_books bb
        JOIN users u ON bb.user_id = u.user_id
        JOIN book_copies bc ON bb.copy_id = bc.copy_id
        JOIN book_titles bt ON bb.title_id = bt.title_id
        WHERE bb.status IN ('borrowed', 'overdue', 'renewed')
        ORDER BY bb.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentActivities = $stmt->fetchAll();

    // Recently added books (updated for new structure)
    $stmt = $conn->prepare("
        SELECT 
            bt.title_id, 
            bt.title, 
            bt.author, 
            bt.category, 
            bt.status,
            bt.total_copies,
            bt.available_copies,
            bt.created_at
        FROM book_titles bt 
        WHERE bt.status != 'Archived'
        ORDER BY bt.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recentBooks = $stmt->fetchAll();

    // Overdue books count (books where due_date has passed)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM borrowed_books bb
        WHERE bb.status IN ('borrowed', 'overdue', 'renewed') AND bb.due_date < CURDATE()
    ");
    $stmt->execute();
    $overdueCount = $stmt->fetch()['total'];

    // Total book titles in system
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM book_titles");
    $stmt->execute();
    $totalBookTitles = $stmt->fetch()['total'];

    // Total book copies in system
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM book_copies");
    $stmt->execute();
    $totalBookCopies = $stmt->fetch()['total'];

} catch (PDOException $e) {
    // Set default values if database query fails
    $totalStudents = 0;
    $borrowedToday = 0;
    $totalAvailableCopies = 0;
    $totalAvailableTitles = 0;
    $archivedBooks = 0;
    $recentActivities = [];
    $recentBooks = [];
    $overdueCount = 0;
    $totalBookTitles = 0;
    $totalBookCopies = 0;
    
    // For debugging - uncomment this line:
    // echo "Database error: " . $e->getMessage();
}

// Updated helper function to format time ago with proper timezone handling
function timeAgo($datetime) {
    // If it's a timestamp, use it directly
    if (is_numeric($datetime)) {
        $timestamp = $datetime;
    } else {
        // Convert datetime string to timestamp
        $timestamp = strtotime($datetime);
    }
    
    // Get current timestamp
    $now = time();
    $time_diff = $now - $timestamp;
    
    // Debug: uncomment to see actual time difference
    // echo "<!-- Time diff: $time_diff seconds, Now: $now, Then: $timestamp -->";
    
    if ($time_diff < 60) {
        return 'Just now';
    } elseif ($time_diff < 3600) {
        $minutes = floor($time_diff / 60);
        return $minutes . ' min' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($time_diff < 86400) {
        $hours = floor($time_diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($time_diff < 604800) {
        $days = floor($time_diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        // For older activities, show the actual date
        return date('M j, Y g:i A', $timestamp);
    }
}

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    switch($status) {
        case 'Available': return 'status-available';
        case 'Borrowed': return 'status-borrowed';
        case 'Archived': return 'status-archived';
        default: return 'status-available';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - The Cat-alog Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <?php include '../includes/favicon.php'; ?>
</head>
<body>
<div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-logo">
                <img src="../assets/images/logo.png" alt="Logo" class="banner-logo-dashboard">
                <div class="sidebar-title">The Cat-alog<br>Library</div>
            </div>
            
            <nav>
                <ul class="sidebar-nav">
                    <li><a href="dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="manage_books.php">Manage Books</a></li>
                    <li><a href="manage_borrowed.php">Borrowed Books</a></li>
                    <li><a href="manage_students.php">Students</a></li>
                    <li><a href="archive_books.php">Archive</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Header -->
            <header class="admin-header">
                <h1 class="page-title">Admin Dashboard</h1>
                <div class="admin-info">
                    <div class="admin-avatar">
                        <img src="../assets/images/admin-icon.jpg" alt="Admin Icon" class="admin-avatar">
                    </div>
                    <div class="admin-details">
                        <h3><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin User'); ?></h3>
                        <p>Library Administrator</p>
                        <small style="color: #666;">Current time: <?php echo date('M j, Y g:i A'); ?></small>
                    </div>
                </div>
            </header>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Students</div>
                        <div class="stat-icon">👥</div>
                    </div>
                    <div class="stat-number"><?php echo $totalStudents; ?></div>
                    <div class="stat-change">
                        <span>📊</span> Registered users
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Borrowed Today</div>
                        <div class="stat-icon">📖</div>
                    </div>
                    <div class="stat-number"><?php echo $borrowedToday; ?></div>
                    <div class="stat-change">
                        <span>📅</span> Today's activity
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Available Copies</div>
                        <div class="stat-icon">📋</div>
                    </div>
                    <div class="stat-number"><?php echo $totalAvailableCopies; ?></div>
                    <div class="stat-change">
                        <span>📚</span> <?php echo $totalAvailableTitles; ?> titles available
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Total Collection</div>
                        <div class="stat-icon">📦</div>
                    </div>
                    <div class="stat-number"><?php echo $totalBookCopies; ?></div>
                    <div class="stat-change">
                        <span>🗃️</span> <?php echo $totalBookTitles; ?> unique titles
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Recent Activities -->
                <div class="content-card">
                    <h2 class="card-title">
                        <div class="card-icon">🕒</div>
                        Recent Activities
                    </h2>
                    
                    <?php if (empty($recentActivities)): ?>
                        <p style="text-align: center; color: #666; margin: 2rem 0;">No recent activities</p>
                    <?php else: ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php echo $activity['activity_type'] === 'borrow' ? '📖' : '↩️'; ?>
                                </div>
                                <div class="activity-content">
                                    <h4>Book <?php echo ucfirst($activity['activity_type']); ?>ed</h4>
                                    <p><?php echo htmlspecialchars($activity['student_name']); ?> 
                                       <?php echo $activity['activity_type'] === 'borrow' ? 'borrowed' : 'returned'; ?> 
                                       "<?php echo htmlspecialchars($activity['book_title']); ?>"</p>
                                    <?php if (isset($activity['book_copy_id'])): ?>
                                        <small style="color: #666; font-family: monospace;">Copy ID: <?php echo htmlspecialchars($activity['book_copy_id']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-time">
                                    <?php 
                                    // Use timestamp if available, otherwise use created_at
                                    $timeToShow = isset($activity['activity_timestamp']) ? $activity['activity_timestamp'] : $activity['created_at'];
                                    echo timeAgo($timeToShow); 
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($overdueCount > 0): ?>
                        <div class="activity-item" style="background-color: #ffe6e6; border-radius: 8px; margin-top: 1rem;">
                            <div class="activity-icon">⚠️</div>
                            <div class="activity-content">
                                <h4>Overdue Alert</h4>
                                <p><?php echo $overdueCount; ?> book<?php echo $overdueCount > 1 ? 's are' : ' is'; ?> overdue</p>
                            </div>
                            <div class="activity-time">
                                <a href="manage_borrowed.php?overdue=yes" style="color: #d32f2f; text-decoration: none; font-weight: 600;">View →</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($archivedBooks > 0): ?>
                        <div class="activity-item" style="background-color: #fff3cd; border-radius: 8px; margin-top: 1rem;">
                            <div class="activity-icon">📦</div>
                            <div class="activity-content">
                                <h4>Archive Status</h4>
                                <p><?php echo $archivedBooks; ?> book cop<?php echo $archivedBooks > 1 ? 'ies are' : 'y is'; ?> archived</p>
                            </div>
                            <div class="activity-time">
                                <a href="archive_books.php?view=archived" style="color: #856404; text-decoration: none; font-weight: 600;">Manage →</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="content-card">
                    <h2 class="card-title">
                        <div class="card-icon">⚡</div>
                        Quick Actions
                    </h2>
                    
                    <div class="quick-actions">
                        <a href="add_book.php" class="action-btn">
                            📚 Add New Book
                        </a>
                        <a href="manage_students.php" class="action-btn">
                            👤 Register Student
                        </a>
                        <a href="manage_borrowed.php" class="action-btn secondary">
                            📖 Process Borrowing
                        </a>
                        <a href="manage_borrowed.php" class="action-btn secondary">
                            ↩️ Process Return
                        </a>
                        <a href="manage_borrowed.php?overdue=yes" class="action-btn secondary">
                            ⚠️ View Overdue Books
                        </a>
                        <a href="archive_books.php" class="action-btn secondary">
                            📦 Manage Archive
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Books Table -->
            <div class="content-card">
                <h2 class="card-title">
                    <div class="card-icon">📋</div>
                    Recently Added Books
                </h2>
                
                <?php if (empty($recentBooks)): ?>
                    <p style="text-align: center; color: #666; margin: 2rem 0;">No books found</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Category</th>
                                <th>Copies</th>
                                <th>Status</th>
                                <th>Date Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentBooks as $book): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($book['title']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                                    <td><?php echo htmlspecialchars($book['category']); ?></td>
                                    <td>
                                        <span style="color: #28a745; font-weight: 600;"><?php echo $book['available_copies']; ?></span>
                                        <span style="color: #666;">/ <?php echo $book['total_copies']; ?></span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo getStatusBadgeClass($book['status']); ?>">
                                            <?php echo ucfirst($book['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($book['created_at'])); ?></td>
                                    <td>
                                        <a href="edit_book.php?id=<?php echo $book['title_id']; ?>" class="action-btn" style="padding: 0.3rem 0.8rem; font-size: 0.8rem;">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="manage_books.php" class="action-btn secondary">View All Books</a>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Simple JavaScript for interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Add click handlers for stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('click', function() {
                    this.style.transform = 'translateY(-3px)';
                    this.style.boxShadow = '6px 6px 0 var(--caramel)';
                    setTimeout(() => {
                        this.style.transform = '';
                        this.style.boxShadow = '';
                    }, 200);
                });
            });

            // Auto-refresh page every 5 minutes for real-time updates
            setTimeout(function() {
                location.reload();
            }, 300000);
        });
    </script>
</body>
</html>