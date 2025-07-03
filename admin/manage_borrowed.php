<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin authentication check
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['username'])) {
    header('Location: login.php?error=Please login to access admin panel');
    exit();
}

// Include database connection
require_once '../includes/db.php';

$message = '';
$message_type = '';
$errors = [];

// Handle return book action
if (isset($_POST['return_book']) && isset($_POST['borrow_id'])) {
    try {
        $borrow_id = intval($_POST['borrow_id']);
        
        // Get book details for updating book status
        $bookQuery = $conn->prepare("
            SELECT bb.book_id, b.title 
            FROM borrowed_books bb 
            JOIN books b ON bb.book_id = b.book_id 
            WHERE bb.id = ? AND bb.status = 'borrowed'
        ");
        $bookQuery->execute([$borrow_id]);
        $bookData = $bookQuery->fetch();
        
        if ($bookData) {
            // Update borrowed_books table
            $returnStmt = $conn->prepare("
                UPDATE borrowed_books 
                SET return_date = CURDATE(), status = 'returned' 
                WHERE id = ? AND status = 'borrowed'
            ");
            
            // Update books table status
            $bookStatusStmt = $conn->prepare("
                UPDATE books 
                SET status = 'Available' 
                WHERE book_id = ?
            ");
            
            // Execute both updates
            $conn->beginTransaction();
            $returnResult = $returnStmt->execute([$borrow_id]);
            $bookResult = $bookStatusStmt->execute([$bookData['book_id']]);
            
            if ($returnResult && $bookResult) {
                $conn->commit();
                $message = "Book '{$bookData['title']}' returned successfully!";
                $message_type = "success";
            } else {
                $conn->rollback();
                $message = "Failed to process book return.";
                $message_type = "error";
            }
        } else {
            $message = "Book not found or already returned.";
            $message_type = "error";
        }
        
    } catch (PDOException $e) {
        $conn->rollback();
        $message = "Error processing return: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle new borrowing
if (isset($_POST['borrow_book'])) {
    $user_id = intval($_POST['user_id']);
    $book_id = intval($_POST['book_id']);
    $due_days = 7; // Fixed 7 days borrowing period
    
    try {
        // Check if book is available
        $bookCheck = $conn->prepare("SELECT status, title FROM books WHERE book_id = ?");
        $bookCheck->execute([$book_id]);
        $book = $bookCheck->fetch();
        
        if (!$book) {
            $errors[] = "Book not found.";
        } elseif ($book['status'] !== 'Available') {
            $errors[] = "Book is not available for borrowing.";
        } else {
            // Check if user exists
            $userCheck = $conn->prepare("SELECT user_id, first_name, last_name FROM users WHERE user_id = ?");
            $userCheck->execute([$user_id]);
            $user = $userCheck->fetch();
            
            if (!$user) {
                $errors[] = "User not found.";
            } else {
                // Process borrowing with fixed 7-day period
                $borrow_date = date('Y-m-d');
                $due_date = date('Y-m-d', strtotime("+$due_days days"));
                
                $conn->beginTransaction();
                
                // Insert borrow record
                $borrowStmt = $conn->prepare("
                    INSERT INTO borrowed_books (user_id, book_id, borrow_date, due_date, status) 
                    VALUES (?, ?, ?, ?, 'borrowed')
                ");
                
                // Update book status
                $updateBookStmt = $conn->prepare("UPDATE books SET status = 'Borrowed' WHERE book_id = ?");
                
                $borrowResult = $borrowStmt->execute([$user_id, $book_id, $borrow_date, $due_date]);
                $updateResult = $updateBookStmt->execute([$book_id]);
                
                if ($borrowResult && $updateResult) {
                    $conn->commit();
                    $message = "Book '{$book['title']}' borrowed by {$user['first_name']} {$user['last_name']} successfully! Due date: " . date('M j, Y', strtotime($due_date));
                    $message_type = "success";
                } else {
                    $conn->rollback();
                    $errors[] = "Failed to process borrowing.";
                }
            }
        }
        
    } catch (PDOException $e) {
        $conn->rollback();
        $errors[] = "Database error: " . $e->getMessage();
    }
    
    if (!empty($errors)) {
        $message_type = "error";
    }
}

// Handle search and filtering
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$overdue_filter = isset($_GET['overdue']) ? $_GET['overdue'] : '';

// Build query for borrowed books
$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(b.title LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_conditions[] = "bb.status = ?";
    $params[] = $status_filter;
}

if ($overdue_filter === 'yes') {
    $where_conditions[] = "bb.due_date < CURDATE() AND bb.status = 'borrowed'";
}

$where_clause = implode(" AND ", $where_conditions);

try {
    // Get borrowed books with user and book details
    $query = "
        SELECT 
            bb.id,
            bb.user_id,
            bb.book_id,
            bb.borrow_date,
            bb.due_date,
            bb.return_date,
            bb.status,
            u.first_name,
            u.last_name,
            u.username,
            u.email,
            b.title,
            b.author,
            b.category,
            CASE 
                WHEN bb.status = 'borrowed' AND bb.due_date < CURDATE() THEN 'overdue'
                ELSE bb.status
            END as display_status,
            CASE
                WHEN bb.status = 'borrowed' AND bb.due_date < CURDATE() THEN DATEDIFF(CURDATE(), bb.due_date)
                WHEN bb.status = 'returned' AND bb.return_date > bb.due_date THEN DATEDIFF(bb.return_date, bb.due_date)
                ELSE 0
            END as days_overdue,
            CASE
                WHEN bb.status = 'borrowed' AND bb.due_date < CURDATE() THEN DATEDIFF(CURDATE(), bb.due_date) * 10
                WHEN bb.status = 'returned' AND bb.return_date > bb.due_date THEN DATEDIFF(bb.return_date, bb.due_date) * 10
                ELSE 0
            END as fine_amount
        FROM borrowed_books bb
        JOIN users u ON bb.user_id = u.user_id
        JOIN books b ON bb.book_id = b.book_id
        WHERE $where_clause
        ORDER BY 
            CASE WHEN bb.status = 'borrowed' AND bb.due_date < CURDATE() THEN 1 ELSE 2 END,
            bb.borrow_date DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $borrowed_books = $stmt->fetchAll();
    
    // Get available books for new borrowing
    $availableBooksQuery = "SELECT book_id, title, author FROM books WHERE status = 'Available' ORDER BY title";
    $availableBooks = $conn->query($availableBooksQuery)->fetchAll();
    
    // Get users for borrowing
    $usersQuery = "SELECT user_id, username, first_name, last_name FROM users ORDER BY first_name, last_name";
    $users = $conn->query($usersQuery)->fetchAll();
    
    // Get statistics
    $statsQuery = "
        SELECT 
            COUNT(CASE WHEN status = 'borrowed' THEN 1 END) as currently_borrowed,
            COUNT(CASE WHEN status = 'returned' THEN 1 END) as total_returned,
            COUNT(CASE WHEN status = 'borrowed' AND due_date < CURDATE() THEN 1 END) as overdue_count
        FROM borrowed_books
    ";
    $stats = $conn->query($statsQuery)->fetch();
    
} catch (PDOException $e) {
    $borrowed_books = [];
    $availableBooks = [];
    $users = [];
    $stats = ['currently_borrowed' => 0, 'total_returned' => 0, 'overdue_count' => 0];
    $error_message = "Database error: " . $e->getMessage();
}

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    switch($status) {
        case 'borrowed': return 'status-borrowed';
        case 'returned': return 'status-available';
        case 'overdue': return 'status-overdue';
        default: return 'status-borrowed';
    }
}

// Helper function to calculate days difference
function daysDifference($date1, $date2 = null) {
    $date2 = $date2 ?: date('Y-m-d');
    $diff = strtotime($date2) - strtotime($date1);
    return floor($diff / (60 * 60 * 24));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Borrowed Books - The Cat-alog Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../assets/css/admin_manage_borrowed.css">

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
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="manage_books.php">Manage Books</a></li>
                    <li><a href="manage_borrowed.php" class="active">Borrowed Books</a></li>
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
                <h1 class="page-title">📚 Manage Borrowed Books</h1>
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

            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-card-small">
                    <div class="stat-number-small"><?php echo $stats['currently_borrowed']; ?></div>
                    <div class="stat-label">Currently Borrowed</div>
                </div>
                <div class="stat-card-small">
                    <div class="stat-number-small"><?php echo $stats['overdue_count']; ?></div>
                    <div class="stat-label">Overdue Books</div>
                </div>
                <div class="stat-card-small">
                    <div class="stat-number-small"><?php echo $stats['total_returned']; ?></div>
                    <div class="stat-label">Total Returned</div>
                </div>
            </div>

            <!-- Messages -->
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php if ($message_type === 'success'): ?>
                        ✅ <?php echo htmlspecialchars($message); ?>
                    <?php else: ?>
                        ❌ There were some errors:
                        <ul class="error-list">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="message error">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Overdue Alert -->
            <?php if ($stats['overdue_count'] > 0): ?>
                <div class="overdue-alert">
                    ⚠️ <strong>Alert:</strong> There are <?php echo $stats['overdue_count']; ?> overdue book(s) that need attention!
                    <a href="?overdue=yes" style="color: #e74c3c; text-decoration: underline; margin-left: 1rem;">View Overdue Books</a>
                </div>
            <?php endif; ?>

            <!-- New Borrowing Section -->
            <div class="new-borrow-section">
                <h3>📖 Process New Borrowing</h3>
                <form method="POST" class="borrow-form">
                    <div class="form-group">
                        <label for="user_id">Select Student</label>
                        <select id="user_id" name="user_id" required>
                            <option value="">Choose a student...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>">
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="book_id">Select Book</label>
                        <select id="book_id" name="book_id" required>
                            <option value="">Choose a book...</option>
                            <?php foreach ($availableBooks as $book): ?>
                                <option value="<?php echo $book['book_id']; ?>">
                                    <?php echo htmlspecialchars($book['title'] . ' - ' . $book['author']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Borrowing Period</label>
                        <div style="padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px; background: #f8f9fa; color: #666;">
                            7 days (Fixed period)
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="borrow_book" class="action-btn">
                            📚 Process Borrowing
                        </button>
                    </div>
                </form>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="form-group">
                            <label for="search">Search</label>
                            <input type="text" 
                            id="search" 
                            name="search" 
                            class="search-input-wide"
                            placeholder="Search by book title or student name..." 
                            value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">All Status</option>
                                <option value="borrowed" <?php echo $status_filter === 'borrowed' ? 'selected' : ''; ?>>Currently Borrowed</option>
                                <option value="returned" <?php echo $status_filter === 'returned' ? 'selected' : ''; ?>>Returned</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="overdue">Overdue</label>
                            <select id="overdue" name="overdue">
                                <option value="">All Books</option>
                                <option value="yes" <?php echo $overdue_filter === 'yes' ? 'selected' : ''; ?>>Overdue Only</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="action-btn">
                                🔍 Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Borrowed Books Table -->
            <div class="borrowed-books-table">
                <div class="table-header">
                    <h2 style ="color: black;">📋 Borrowed Books (<?php echo count($borrowed_books); ?> records)</h2>
                    <a href="?" class="action-btn" style="background: white; color: var(--orange);">
                        🔄 Clear Filters
                    </a>
                </div>

                <?php if (empty($borrowed_books)): ?>
                    <div class="empty-state">
                        <h3>📚 No borrowed books found</h3>
                        <p>No books match your current filters.</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Student</th>
                                <th>Borrow Date</th>
                                <th>Due Date</th>
                                <th>Return Date</th>
                                <th>Status</th>
                                <th>Fine</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($borrowed_books as $borrow): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($borrow['title']); ?></strong><br>
                                        <small>by <?php echo htmlspecialchars($borrow['author']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($borrow['first_name'] . ' ' . $borrow['last_name']); ?><br>
                                        <small><?php echo htmlspecialchars($borrow['username']); ?></small>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($borrow['borrow_date'])); ?></td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($borrow['due_date'])); ?>
                                        <?php if ($borrow['display_status'] === 'overdue'): ?>
                                            <br><small style="color: #e74c3c;">
                                                <?php echo $borrow['days_overdue']; ?> days overdue
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($borrow['return_date']): ?>
                                            <?php echo date('M j, Y', strtotime($borrow['return_date'])); ?>
                                            <?php if ($borrow['fine_amount'] > 0 && $borrow['status'] === 'returned'): ?>
                                                <br><small style="color: #e74c3c;">
                                                    Returned <?php echo abs($borrow['days_overdue']); ?> day(s) late
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: #999;">Not returned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo getStatusBadgeClass($borrow['display_status']); ?>">
                                            <?php echo ucfirst($borrow['display_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($borrow['fine_amount'] > 0): ?>
                                            <span style="color: #e74c3c; font-weight: 600;">
                                                ₱<?php echo number_format($borrow['fine_amount'], 2); ?>
                                            </span>
                                            <br><small style="color: #666;">
                                                <?php echo $borrow['days_overdue']; ?> day(s) × ₱10.00
                                            </small>
                                        <?php else: ?>
                                            <span style="color: #27ae60;">₱0.00</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($borrow['status'] === 'borrowed'): ?>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Mark this book as returned?');">
                                                <input type="hidden" name="borrow_id" value="<?php echo $borrow['id']; ?>">
                                                <button type="submit" name="return_book" class="btn-return">
                                                    ↩️ Return
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #27ae60;">✅ Completed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Auto-submit form when filters change
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.querySelector('.filters-section form');
            const selects = filterForm.querySelectorAll('select');
            
            selects.forEach(select => {
                select.addEventListener('change', function() {
                    filterForm.submit();
                });
            });
        });
    </script>
</body>
</html>