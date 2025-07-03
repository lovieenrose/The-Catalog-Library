<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

    // Books borrowed today (fix the join to match your database structure)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM borrowed_books WHERE DATE(borrow_date) = CURDATE() AND status = 'borrowed'");
    $stmt->execute();
    $borrowedToday = $stmt->fetch()['total'];

    // Total books available
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM books WHERE status = 'Available'");
    $stmt->execute();
    $totalBooks = $stmt->fetch()['total'];

    // Archived books count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM books WHERE status = 'archived'");
    $stmt->execute();
    $archivedBooks = $stmt->fetch()['total'];

    // Recent activities (fix joins to match your actual table structure)
    $stmt = $conn->prepare("
        SELECT 
            'borrow' as activity_type,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            b.title as book_title,
            bb.borrow_date as activity_date
        FROM borrowed_books bb
        JOIN users u ON bb.user_id = u.user_id
        JOIN books b ON bb.book_id = b.book_id
        WHERE bb.status = 'borrowed'
        ORDER BY bb.borrow_date DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentActivities = $stmt->fetchAll();

    // Recently added books
    $stmt = $conn->prepare("
        SELECT book_id, title, author, category, status, created_at
        FROM books 
        WHERE status != 'archived'
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recentBooks = $stmt->fetchAll();

    // Overdue books count (books where due_date has passed)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM borrowed_books bb
        WHERE bb.status = 'borrowed' AND bb.due_date < CURDATE()
    ");
    $stmt->execute();
    $overdueCount = $stmt->fetch()['total'];

} catch (PDOException $e) {
    // Set default values if database query fails
    $totalStudents = 0;
    $borrowedToday = 0;
    $totalBooks = 0;
    $archivedBooks = 0;
    $recentActivities = [];
    $recentBooks = [];
    $overdueCount = 0;
    
    // For debugging - uncomment this line:
    // echo "Database error: " . $e->getMessage();
}

// Helper function to format time ago
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' min ago';
    if ($time < 86400) return floor($time/3600) . ' hour' . (floor($time/3600) > 1 ? 's' : '') . ' ago';
    if ($time < 2592000) return floor($time/86400) . ' day' . (floor($time/86400) > 1 ? 's' : '') . ' ago';
    
    return date('M j, Y', strtotime($datetime));
}

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    switch($status) {
        case 'Available': return 'status-available';
        case 'Borrowed': return 'status-borrowed';
        case 'archived': return 'status-archived';
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
                        <div class="stat-title">Available Books</div>
                        <div class="stat-icon">📋</div>
                    </div>
                    <div class="stat-number"><?php echo $totalBooks; ?></div>
                    <div class="stat-change">
                        <span>📚</span> Ready to borrow
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-title">Archived Books</div>
                        <div class="stat-icon">📦</div>
                    </div>
                    <div class="stat-number"><?php echo $archivedBooks; ?></div>
                    <div class="stat-change">
                        <span>🗃️</span> In archive
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
                                </div>
                                <div class="activity-time"><?php echo timeAgo($activity['activity_date']); ?></div>
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
                                <a href="manage_borrowed.php?filter=overdue" style="color: #d32f2f; text-decoration: none; font-weight: 600;">View →</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($archivedBooks > 0): ?>
                        <div class="activity-item" style="background-color: #fff3cd; border-radius: 8px; margin-top: 1rem;">
                            <div class="activity-icon">📦</div>
                            <div class="activity-content">
                                <h4>Archive Status</h4>
                                <p><?php echo $archivedBooks; ?> book<?php echo $archivedBooks > 1 ? 's are' : ' is'; ?> archived</p>
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
                        <a href="manage_students.php?action=register" class="action-btn">
                            👤 Register Student
                        </a>
                        <a href="manage_borrowed.php?action=new" class="action-btn secondary">
                            📖 Process Borrowing
                        </a>
                        <a href="manage_borrowed.php?action=return" class="action-btn secondary">
                            ↩️ Process Return
                        </a>
                        <a href="manage_borrowed.php?filter=overdue" class="action-btn secondary">
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
                                        <span class="status-badge <?php echo getStatusBadgeClass($book['status']); ?>">
                                            <?php echo ucfirst($book['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($book['created_at'])); ?></td>
                                    <td>
                                        <a href="edit_book.php?id=<?php echo $book['book_id']; ?>" class="action-btn" style="padding: 0.3rem 0.8rem; font-size: 0.8rem;">Edit</a>
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