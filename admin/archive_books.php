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

// Handle archive book action
if (isset($_POST['archive_book']) && isset($_POST['book_id'])) {
    try {
        $book_id = intval($_POST['book_id']);
        
        // Check if book is currently borrowed
        $borrowCheck = $conn->prepare("SELECT COUNT(*) as count FROM borrowed_books WHERE book_id = ? AND status = 'borrowed'");
        $borrowCheck->execute([$book_id]);
        $borrowed = $borrowCheck->fetch()['count'];
        
        if ($borrowed > 0) {
            $message = "Cannot archive book. It is currently borrowed.";
            $message_type = "error";
        } else {
            // Get book title for confirmation message
            $bookStmt = $conn->prepare("SELECT title FROM books WHERE book_id = ?");
            $bookStmt->execute([$book_id]);
            $book = $bookStmt->fetch();
            
            if ($book) {
                // Archive the book
                $archiveStmt = $conn->prepare("UPDATE books SET status = 'archived' WHERE book_id = ?");
                if ($archiveStmt->execute([$book_id])) {
                    $message = "Book '{$book['title']}' archived successfully!";
                    $message_type = "success";
                } else {
                    $message = "Failed to archive book.";
                    $message_type = "error";
                }
            } else {
                $message = "Book not found.";
                $message_type = "error";
            }
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle restore book action
if (isset($_POST['restore_book']) && isset($_POST['book_id'])) {
    try {
        $book_id = intval($_POST['book_id']);
        
        // Get book title for confirmation message
        $bookStmt = $conn->prepare("SELECT title FROM books WHERE book_id = ?");
        $bookStmt->execute([$book_id]);
        $book = $bookStmt->fetch();
        
        if ($book) {
            // Restore the book
            $restoreStmt = $conn->prepare("UPDATE books SET status = 'Available' WHERE book_id = ?");
            if ($restoreStmt->execute([$book_id])) {
                $message = "Book '{$book['title']}' restored successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to restore book.";
                $message_type = "error";
            }
        } else {
            $message = "Book not found.";
            $message_type = "error";
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle permanent delete action
if (isset($_POST['permanent_delete']) && isset($_POST['book_id'])) {
    try {
        $book_id = intval($_POST['book_id']);
        
        // Get book title for confirmation message
        $bookStmt = $conn->prepare("SELECT title, book_image FROM books WHERE book_id = ?");
        $bookStmt->execute([$book_id]);
        $book = $bookStmt->fetch();
        
        if ($book) {
            // Delete book image if exists
            if ($book['book_image'] && file_exists('../uploads/book_images/' . $book['book_image'])) {
                unlink('../uploads/book_images/' . $book['book_image']);
            }
            
            // Permanently delete the book
            $deleteStmt = $conn->prepare("DELETE FROM books WHERE book_id = ?");
            if ($deleteStmt->execute([$book_id])) {
                $message = "Book '{$book['title']}' permanently deleted!";
                $message_type = "success";
            } else {
                $message = "Failed to delete book.";
                $message_type = "error";
            }
        } else {
            $message = "Book not found.";
            $message_type = "error";
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle search and filtering
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$view = isset($_GET['view']) ? $_GET['view'] : 'available'; // available, archived, all
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build the query based on view
$where_conditions = [];
$params = [];

switch ($view) {
    case 'archived':
        $where_conditions[] = "status = 'archived'";
        break;
    case 'available':
        $where_conditions[] = "status IN ('Available', 'Borrowed')";
        break;
    case 'all':
        // No status filter for all
        break;
}

if (!empty($search)) {
    $where_conditions[] = "(title LIKE ? OR author LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Valid sort columns
$valid_sorts = ['title', 'author', 'category', 'published_year', 'status', 'created_at'];
if (!in_array($sort_by, $valid_sorts)) {
    $sort_by = 'created_at';
}

$valid_orders = ['ASC', 'DESC'];
if (!in_array(strtoupper($sort_order), $valid_orders)) {
    $sort_order = 'DESC';
}

try {
    // Get books based on current view
    $query = "SELECT * FROM books $where_clause ORDER BY $sort_by $sort_order";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $books = $stmt->fetchAll();

    // Get statistics
    $statsQuery = "
        SELECT 
            COUNT(CASE WHEN status IN ('Available', 'Borrowed') THEN 1 END) as active_books,
            COUNT(CASE WHEN status = 'archived' THEN 1 END) as archived_books,
            COUNT(CASE WHEN status = 'Available' THEN 1 END) as available_books,
            COUNT(CASE WHEN status = 'Borrowed' THEN 1 END) as borrowed_books
        FROM books
    ";
    $stats = $conn->query($statsQuery)->fetch();

    // Get categories for filter dropdown
    $categoryStmt = $conn->prepare("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categoryStmt->execute();
    $categories = $categoryStmt->fetchAll();

} catch (PDOException $e) {
    $books = [];
    $categories = [];
    $stats = ['active_books' => 0, 'archived_books' => 0, 'available_books' => 0, 'borrowed_books' => 0];
    $error_message = "Database error: " . $e->getMessage();
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

// Helper function to build URL with current parameters
function buildUrl($newParams = []) {
    $currentParams = $_GET;
    $params = array_merge($currentParams, $newParams);
    $params = array_filter($params, function($value) {
        return $value !== '' && $value !== null;
    });
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Books - The Cat-alog Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../assets/css/admin_archive_books.css">

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
                    <li><a href="manage_borrowed.php">Borrowed Books</a></li>
                    <li><a href="manage_students.php">Students</a></li>
                    <li><a href="archive_books.php" class="active">Archive</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Header -->
            <header class="admin-header">
                <h1 class="page-title">📦 Archive Management</h1>
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
                    <div class="stat-number-small"><?php echo $stats['active_books']; ?></div>
                    <div class="stat-label">Active Books</div>
                </div>
                <div class="stat-card-small">
                    <div class="stat-number-small"><?php echo $stats['archived_books']; ?></div>
                    <div class="stat-label">Archived Books</div>
                </div>
                <div class="stat-card-small">
                    <div class="stat-number-small"><?php echo $stats['available_books']; ?></div>
                    <div class="stat-label">Available Books</div>
                </div>
                <div class="stat-card-small">
                    <div class="stat-number-small"><?php echo $stats['borrowed_books']; ?></div>
                    <div class="stat-label">Currently Borrowed</div>
                </div>
            </div>

            <!-- Messages -->
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php if ($message_type === 'success'): ?>
                        ✅ <?php echo htmlspecialchars($message); ?>
                    <?php else: ?>
                        ❌ <?php echo htmlspecialchars($message); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="message error">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Archive Notice -->
            <?php if ($view === 'archived'): ?>
                <div class="archive-notice">
                    📦 <strong>Archive View:</strong> These books are archived and not available for borrowing. You can restore them to make them available again or permanently delete them.
                </div>
            <?php endif; ?>

            <!-- View Tabs -->
            <div class="view-tabs">
                <a href="<?php echo buildUrl(['view' => 'available']); ?>" 
                   class="view-tab <?php echo $view === 'available' ? 'active' : ''; ?>">
                    📚 Active Books (<?php echo $stats['active_books']; ?>)
                </a>
                <a href="<?php echo buildUrl(['view' => 'archived']); ?>" 
                   class="view-tab <?php echo $view === 'archived' ? 'active' : ''; ?>">
                    📦 Archived Books (<?php echo $stats['archived_books']; ?>)
                </a>
                <a href="<?php echo buildUrl(['view' => 'all']); ?>" 
                   class="view-tab <?php echo $view === 'all' ? 'active' : ''; ?>">
                    📋 All Books
                </a>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" action="">
                    <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
                    <div class="filters-grid">
                        <div class="form-group">
                            <label for="search">Search Books</label>
                            <input type="text" id="search" name="search" placeholder="Search by title or author..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['category']); ?>" 
                                            <?php echo $category_filter === $category['category'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['category']); ?>
                                    </option>
                                <?php endforeach; ?>
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

            <!-- Books Section -->
            <div class="books-table">
                <div class="table-header">
                    <div>
                        <h2>
                            <?php if ($view === 'archived'): ?>
                                📦 Archived Books (<?php echo count($books); ?> books)
                            <?php elseif ($view === 'available'): ?>
                                📚 Active Books (<?php echo count($books); ?> books)
                            <?php else: ?>
                                📋 All Books (<?php echo count($books); ?> books)
                            <?php endif; ?>
                        </h2>
                    </div>
                    <a href="?" class="action-btn" style="background: white; color: var(--orange);">
                        🔄 Clear Filters
                    </a>
                </div>

                <?php if (empty($books)): ?>
                    <div class="empty-state">
                        <h3>📚 No books found</h3>
                        <p>No books match your current filters in this view.</p>
                        <a href="?" class="action-btn">Clear Filters</a>
                    </div>
                <?php else: ?>
                    <div class="books-grid">
                        <?php foreach ($books as $book): ?>
                            <div class="book-card <?php echo $book['status'] === 'archived' ? 'archived' : ''; ?>">
                                <div class="book-image">
                                    <?php if (!empty($book['book_image'])): ?>
                                        <img src="../uploads/book-images/<?php echo htmlspecialchars($book['book_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($book['title']); ?>"
                                             style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                                    <?php else: ?>
                                        📖
                                    <?php endif; ?>
                                </div>
                                
                                <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                                <div class="book-author">by <?php echo htmlspecialchars($book['author'] ?? 'Unknown Author'); ?></div>
                                
                                <div class="book-meta">
                                    <span class="book-category"><?php echo htmlspecialchars($book['category'] ?? 'Uncategorized'); ?></span>
                                    <span class="book-year"><?php echo $book['published_year'] ?? 'N/A'; ?></span>
                                </div>
                                
                                <div style="margin-bottom: 1rem;">
                                    <span class="status-badge <?php echo getStatusBadgeClass($book['status']); ?>">
                                        <?php echo ucfirst($book['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="book-actions">
                                    <?php if ($book['status'] === 'archived'): ?>
                                        <!-- Archived book actions -->
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Restore this book to active status?');">
                                            <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                            <button type="submit" name="restore_book" class="btn-restore">
                                                🔄 Restore
                                            </button>
                                        </form>
                                        
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('PERMANENTLY delete this book? This cannot be undone!');">
                                            <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                            <button type="submit" name="permanent_delete" class="btn-delete">
                                                🗑️ Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- Active book actions -->
                                        <a href="edit_book.php?id=<?php echo $book['book_id']; ?>" class="btn-edit">
                                            ✏️ Edit
                                        </a>
                                        
                                        <?php if ($book['status'] !== 'Borrowed'): ?>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Archive this book? It will no longer be available for borrowing.');">
                                                <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                                <button type="submit" name="archive_book" class="btn-archive">
                                                    📦 Archive
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #999; font-size: 0.9rem;">Cannot archive - Currently borrowed</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
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