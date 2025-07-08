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

// Handle archive book action (updated for new structure)
if (isset($_POST['archive_book']) && isset($_POST['title_id'])) {
    try {
        $title_id = intval($_POST['title_id']);
        
        // Check if any copies are currently borrowed
        $borrowCheck = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM borrowed_books bb 
            WHERE bb.title_id = ? AND bb.status IN ('borrowed', 'overdue', 'renewed')
        ");
        $borrowCheck->execute([$title_id]);
        $borrowed = $borrowCheck->fetch()['count'];
        
        if ($borrowed > 0) {
            $message = "Cannot archive book. One or more copies are currently borrowed.";
            $message_type = "error";
        } else {
            // Get book title for confirmation message
            $bookStmt = $conn->prepare("SELECT title FROM book_titles WHERE title_id = ?");
            $bookStmt->execute([$title_id]);
            $book = $bookStmt->fetch();
            
            if ($book) {
                $conn->beginTransaction();
                
                // Archive all copies of this book
                $archiveCopiesStmt = $conn->prepare("UPDATE book_copies SET status = 'Archived' WHERE title_id = ?");
                $archiveCopiesResult = $archiveCopiesStmt->execute([$title_id]);
                
                // Archive the book title and set available_copies to 0
                $archiveTitleStmt = $conn->prepare("
                    UPDATE book_titles 
                    SET status = 'Archived', available_copies = 0 
                    WHERE title_id = ?
                ");
                $archiveTitleResult = $archiveTitleStmt->execute([$title_id]);
                
                if ($archiveCopiesResult && $archiveTitleResult) {
                    $conn->commit();
                    $message = "Book '{$book['title']}' and all its copies archived successfully!";
                    $message_type = "success";
                } else {
                    $conn->rollback();
                    $message = "Failed to archive book.";
                    $message_type = "error";
                }
            } else {
                $message = "Book not found.";
                $message_type = "error";
            }
        }
    } catch (PDOException $e) {
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle restore book action (updated for new structure)
if (isset($_POST['restore_book']) && isset($_POST['title_id'])) {
    try {
        $title_id = intval($_POST['title_id']);
        
        // Get book title for confirmation message
        $bookStmt = $conn->prepare("SELECT title, total_copies FROM book_titles WHERE title_id = ?");
        $bookStmt->execute([$title_id]);
        $book = $bookStmt->fetch();
        
        if ($book) {
            $conn->beginTransaction();
            
            // Restore all copies of this book
            $restoreCopiesStmt = $conn->prepare("UPDATE book_copies SET status = 'Available' WHERE title_id = ?");
            $restoreCopiesResult = $restoreCopiesStmt->execute([$title_id]);
            
            // Restore the book title and set available_copies to total_copies
            $restoreTitleStmt = $conn->prepare("
                UPDATE book_titles 
                SET status = 'Available', available_copies = total_copies 
                WHERE title_id = ?
            ");
            $restoreTitleResult = $restoreTitleStmt->execute([$title_id]);
            
            if ($restoreCopiesResult && $restoreTitleResult) {
                $conn->commit();
                $message = "Book '{$book['title']}' and all its copies restored successfully!";
                $message_type = "success";
            } else {
                $conn->rollback();
                $message = "Failed to restore book.";
                $message_type = "error";
            }
        } else {
            $message = "Book not found.";
            $message_type = "error";
        }
    } catch (PDOException $e) {
        $conn->rollback();
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle permanent delete action (updated for new structure)
if (isset($_POST['permanent_delete']) && isset($_POST['title_id'])) {
    try {
        $title_id = intval($_POST['title_id']);
        
        // Get book title and image for confirmation message
        $bookStmt = $conn->prepare("SELECT title, book_image FROM book_titles WHERE title_id = ?");
        $bookStmt->execute([$title_id]);
        $book = $bookStmt->fetch();
        
        if ($book) {
            $conn->beginTransaction();
            
            // Delete book image if exists
            if ($book['book_image'] && file_exists('../uploads/book-images/' . $book['book_image'])) {
                unlink('../uploads/book-images/' . $book['book_image']);
            }
            
            // Delete all copies first (due to foreign key constraints)
            $deleteCopiesStmt = $conn->prepare("DELETE FROM book_copies WHERE title_id = ?");
            $deleteCopiesResult = $deleteCopiesStmt->execute([$title_id]);
            
            // Delete the book title
            $deleteTitleStmt = $conn->prepare("DELETE FROM book_titles WHERE title_id = ?");
            $deleteTitleResult = $deleteTitleStmt->execute([$title_id]);
            
            if ($deleteCopiesResult && $deleteTitleResult) {
                $conn->commit();
                $message = "Book '{$book['title']}' and all its copies permanently deleted!";
                $message_type = "success";
            } else {
                $conn->rollback();
                $message = "Failed to delete book.";
                $message_type = "error";
            }
        } else {
            $message = "Book not found.";
            $message_type = "error";
        }
    } catch (PDOException $e) {
        $conn->rollback();
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

// Build the query based on view (updated for new structure)
$where_conditions = [];
$params = [];

switch ($view) {
    case 'archived':
        $where_conditions[] = "bt.status = 'Archived'";
        break;
    case 'available':
        $where_conditions[] = "bt.status IN ('Available', 'Borrowed')";
        break;
    case 'all':
        // No status filter for all
        break;
}

if (!empty($search)) {
    $where_conditions[] = "(bt.title LIKE ? OR bt.author LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "bt.category = ?";
    $params[] = $category_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Valid sort columns for new structure
$valid_sorts = ['title', 'author', 'category', 'published_year', 'total_copies', 'available_copies', 'status', 'created_at'];
if (!in_array($sort_by, $valid_sorts)) {
    $sort_by = 'created_at';
}

$valid_orders = ['ASC', 'DESC'];
if (!in_array(strtoupper($sort_order), $valid_orders)) {
    $sort_order = 'DESC';
}

try {
    // Get books based on current view (updated query for new structure)
    $query = "
        SELECT 
            bt.*,
            CASE 
                WHEN bt.available_copies > 0 THEN 'Available'
                WHEN bt.available_copies = 0 AND bt.total_copies > 0 AND bt.status != 'Archived' THEN 'Borrowed'
                ELSE bt.status
            END as display_status,
            GROUP_CONCAT(bc.book_id ORDER BY bc.copy_number SEPARATOR ', ') as sample_book_ids
        FROM book_titles bt
        LEFT JOIN book_copies bc ON bt.title_id = bc.title_id
        $where_clause
        GROUP BY bt.title_id
        ORDER BY bt.$sort_by $sort_order
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $books = $stmt->fetchAll();

    // Get statistics (updated for new structure)
    $statsQuery = "
        SELECT 
            COUNT(CASE WHEN bt.status IN ('Available', 'Borrowed') THEN 1 END) as active_books,
            COUNT(CASE WHEN bt.status = 'Archived' THEN 1 END) as archived_books,
            COUNT(CASE WHEN bt.available_copies > 0 THEN 1 END) as available_books,
            COUNT(CASE WHEN bt.available_copies = 0 AND bt.total_copies > 0 AND bt.status != 'Archived' THEN 1 END) as borrowed_books,
            SUM(CASE WHEN bt.status = 'Archived' THEN bt.total_copies ELSE 0 END) as archived_copies,
            SUM(CASE WHEN bt.status IN ('Available', 'Borrowed') THEN bt.total_copies ELSE 0 END) as active_copies
        FROM book_titles bt
    ";
    $stats = $conn->query($statsQuery)->fetch();

    // Get categories for filter dropdown
    $categoryStmt = $conn->prepare("SELECT DISTINCT category FROM book_titles WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categoryStmt->execute();
    $categories = $categoryStmt->fetchAll();

} catch (PDOException $e) {
    $books = [];
    $categories = [];
    $stats = ['active_books' => 0, 'archived_books' => 0, 'available_books' => 0, 'borrowed_books' => 0, 'archived_copies' => 0, 'active_copies' => 0];
    $error_message = "Database error: " . $e->getMessage();
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
    <?php include '../includes/favicon.php'; ?>
    <style>
        .book-copies {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .available-copies {
            color: #28a745;
        }
        
        .sample-ids {
            font-size: 0.7rem;
            color: #888;
            font-family: 'Courier New', monospace;
            margin-top: 0.3rem;
            word-break: break-all;
            line-height: 1.2;
        }
    </style>

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
                    <div class="stat-label">Active Titles</div>
                </div>
                <div class="stat-card-small">
                    <div class="stat-number-small"><?php echo $stats['archived_books']; ?></div>
                    <div class="stat-label">Archived Titles</div>
                </div>
                <div class="stat-card-small">
                    <div class="stat-number-small"><?php echo $stats['active_copies']; ?></div>
                    <div class="stat-label">Active Copies</div>
                </div>
                <div class="stat-card-small">
                    <div class="stat-number-small"><?php echo $stats['archived_copies']; ?></div>
                    <div class="stat-label">Archived Copies</div>
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
                    📦 <strong>Archive View:</strong> These book titles and all their copies are archived and not available for borrowing. You can restore them to make them available again or permanently delete them.
                </div>
            <?php endif; ?>

            <!-- View Tabs -->
            <div class="view-tabs">
                <a href="<?php echo buildUrl(['view' => 'available']); ?>" 
                   class="view-tab <?php echo $view === 'available' ? 'active' : ''; ?>">
                    📚 Active Books (<?php echo $stats['active_books']; ?> titles)
                </a>
                <a href="<?php echo buildUrl(['view' => 'archived']); ?>" 
                   class="view-tab <?php echo $view === 'archived' ? 'active' : ''; ?>">
                    📦 Archived Books (<?php echo $stats['archived_books']; ?> titles)
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
                                📦 Archived Books (<?php echo count($books); ?> titles)
                            <?php elseif ($view === 'available'): ?>
                                📚 Active Books (<?php echo count($books); ?> titles)
                            <?php else: ?>
                                📋 All Books (<?php echo count($books); ?> titles)
                            <?php endif; ?>
                        </h2>
                    </div>
                    <a href="?" class="action-btn" style="background: var(--caramel); color: var(--white);">
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
                            <?php
                            $sample_ids = explode(', ', $book['sample_book_ids'] ?? '');
                            $display_ids = array_slice($sample_ids, 0, 2); // Show first 2 IDs
                            ?>
                            <div class="book-card <?php echo $book['status'] === 'Archived' ? 'archived' : ''; ?>">
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
                                <div class="book-copies">
                                    <?php if ($book['status'] === 'Archived'): ?>
                                        <?php echo $book['total_copies']; ?> copies (archived)
                                    <?php else: ?>
                                        <span class="available-copies"><?php echo $book['available_copies']; ?> available</span> 
                                        / <?php echo $book['total_copies']; ?> total
                                    <?php endif; ?>
                                </div>
                                <div class="book-author">by <?php echo htmlspecialchars($book['author'] ?? 'Unknown Author'); ?></div>
                                
                                <div class="book-meta">
                                    <span class="book-category"><?php echo htmlspecialchars($book['category'] ?? 'Uncategorized'); ?></span>
                                    <span class="book-year"><?php echo $book['published_year'] ?? 'N/A'; ?></span>
                                </div>
                                
                                <?php if (!empty($book['sample_book_ids'])): ?>
                                    <div class="sample-ids">
                                        Sample IDs: <?php echo htmlspecialchars(implode(', ', $display_ids)); ?>
                                        <?php if (count($sample_ids) > 2): ?>
                                            ... (+<?php echo count($sample_ids) - 2; ?> more)
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <br>
                                
                                <div style="margin-bottom: 1rem;">
                                    <span class="status-badge <?php echo getStatusBadgeClass($book['display_status']); ?>">
                                        <?php echo ucfirst($book['display_status']); ?>
                                    </span>
                                </div>
                                
                                <div class="book-actions">
                                    <?php if ($book['status'] === 'Archived'): ?>
                                        <!-- Archived book actions -->
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Restore this book and all its copies to active status?');">
                                            <input type="hidden" name="title_id" value="<?php echo $book['title_id']; ?>">
                                            <button type="submit" name="restore_book" class="btn-restore">
                                                🔄 Restore
                                            </button>
                                        </form>
                                        
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('PERMANENTLY delete this book and all its copies? This cannot be undone!');">
                                            <input type="hidden" name="title_id" value="<?php echo $book['title_id']; ?>">
                                            <button type="submit" name="permanent_delete" class="btn-delete">
                                                🗑️ Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- Active book actions -->
                                        <a href="edit_book.php?id=<?php echo $book['title_id']; ?>" class="btn-edit">
                                            ✏️ Edit
                                        </a>
                                        
                                        <?php if ($book['available_copies'] == $book['total_copies']): ?>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Archive this book and all its copies? They will no longer be available for borrowing.');">
                                                <input type="hidden" name="title_id" value="<?php echo $book['title_id']; ?>">
                                                <button type="submit" name="archive_book" class="btn-archive">
                                                    📦 Archive
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #999; font-size: 0.9rem;">Cannot archive - Some copies borrowed</span>
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