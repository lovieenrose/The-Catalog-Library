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

// Handle book deletion (updated for new structure)
if (isset($_POST['delete_book']) && isset($_POST['title_id'])) {
    try {
        // Check if any copies of this book are currently borrowed
        $checkStmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM borrowed_books bb 
            WHERE bb.title_id = ? AND bb.status IN ('borrowed', 'overdue', 'renewed')
        ");
        $checkStmt->execute([$_POST['title_id']]);
        $borrowed = $checkStmt->fetch()['count'];
        
        if ($borrowed > 0) {
            $message = "Cannot delete book. One or more copies are currently borrowed.";
            $message_type = "error";
        } else {
            // Start transaction
            $conn->beginTransaction();
            
            // Delete all copies first (due to foreign key constraints)
            $deleteCopiesStmt = $conn->prepare("DELETE FROM book_copies WHERE title_id = ?");
            $deleteCopiesStmt->execute([$_POST['title_id']]);
            
            // Delete the book title
            $deleteTitleStmt = $conn->prepare("DELETE FROM book_titles WHERE title_id = ?");
            if ($deleteTitleStmt->execute([$_POST['title_id']])) {
                $conn->commit();
                $message = "Book and all its copies deleted successfully.";
                $message_type = "success";
            } else {
                $conn->rollBack();
                $message = "Failed to delete book.";
                $message_type = "error";
            }
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle search and filtering
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build the query for new structure
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(bt.title LIKE ? OR bt.author LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "bt.category = ?";
    $params[] = $category_filter;
}

if (!empty($status_filter)) {
    if ($status_filter === 'Available') {
        $where_conditions[] = "bt.available_copies > 0";
    } elseif ($status_filter === 'Borrowed') {
        $where_conditions[] = "bt.available_copies = 0 AND bt.total_copies > 0";
    } elseif ($status_filter === 'Archived') {
        $where_conditions[] = "bt.status = 'Archived'";
    }
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Valid sort columns for new structure
$valid_sorts = ['title', 'author', 'category', 'published_year', 'total_copies', 'available_copies', 'created_at'];
if (!in_array($sort_by, $valid_sorts)) {
    $sort_by = 'created_at';
}

$valid_orders = ['ASC', 'DESC'];
if (!in_array(strtoupper($sort_order), $valid_orders)) {
    $sort_order = 'DESC';
}

try {
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM book_titles bt $where_clause";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->execute($params);
    $totalBooks = $countStmt->fetch()['total'];

    // Pagination
    $books_per_page = 9;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $books_per_page;
    $total_pages = ceil($totalBooks / $books_per_page);

    // Get books with pagination (updated query)
    $query = "
        SELECT 
            bt.*,
            CASE 
                WHEN bt.available_copies > 0 THEN 'Available'
                WHEN bt.available_copies = 0 AND bt.total_copies > 0 THEN 'Borrowed'
                ELSE 'Archived'
            END as display_status,
            GROUP_CONCAT(bc.book_id ORDER BY bc.copy_number SEPARATOR ', ') as sample_book_ids
        FROM book_titles bt
        LEFT JOIN book_copies bc ON bt.title_id = bc.title_id
        $where_clause
        GROUP BY bt.title_id
        ORDER BY bt.$sort_by $sort_order 
        LIMIT $books_per_page OFFSET $offset
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $books = $stmt->fetchAll();

    // Get categories for filter dropdown
    $categoryStmt = $conn->prepare("SELECT DISTINCT category FROM book_titles ORDER BY category");
    $categoryStmt->execute();
    $categories = $categoryStmt->fetchAll();

} catch (PDOException $e) {
    $books = [];
    $categories = [];
    $totalBooks = 0;
    $total_pages = 0;
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
    <title>Manage Books - The Cat-alog Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../assets/css/admin_manage_books.css">
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
                    <li><a href="manage_books.php" class="active">Manage Books</a></li>
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
                <h1 class="page-title">Manage Books</h1>
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

            <!-- Messages -->
            <?php if (isset($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="message error">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="search">Search Books</label>
                            <input type="text" id="search" name="search" placeholder="Search by title or author..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="filter-group">
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
                        
                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">All Status</option>
                                <option value="Available" <?php echo $status_filter === 'Available' ? 'selected' : ''; ?>>Available</option>
                                <option value="Borrowed" <?php echo $status_filter === 'Borrowed' ? 'selected' : ''; ?>>All Borrowed</option>
                                <option value="Archived" <?php echo $status_filter === 'Archived' ? 'selected' : ''; ?>>Archived</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
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
                        <h2>📚 Books (<?php echo $totalBooks; ?> titles)</h2>
                        <p>Sort by: 
                            <a href="<?php echo buildUrl(['sort' => 'title', 'order' => $sort_by === 'title' && $sort_order === 'ASC' ? 'DESC' : 'ASC']); ?>">Title</a> | 
                            <a href="<?php echo buildUrl(['sort' => 'author', 'order' => $sort_by === 'author' && $sort_order === 'ASC' ? 'DESC' : 'ASC']); ?>">Author</a> | 
                            <a href="<?php echo buildUrl(['sort' => 'total_copies', 'order' => $sort_by === 'total_copies' && $sort_order === 'ASC' ? 'DESC' : 'ASC']); ?>">Copies</a> |
                            <a href="<?php echo buildUrl(['sort' => 'created_at', 'order' => $sort_by === 'created_at' && $sort_order === 'ASC' ? 'DESC' : 'ASC']); ?>">Date Added</a>
                        </p>
                    </div>
                    <a href="add_book.php" class="action-btn" style="background: var(--caramel); color: var(--white);">
                        ➕ Add New Book
                    </a>
                </div>

                <?php if (empty($books)): ?>
                    <div class="empty-state">
                        <h3>📚 No books found</h3>
                        <p>No books match your current filters.</p>
                        <a href="?" class="action-btn">Clear Filters</a>
                    </div>
                <?php else: ?>
                    <div class="books-grid">
                        <?php foreach ($books as $book): ?>
                            <?php
                            $sample_ids = explode(', ', $book['sample_book_ids'] ?? '');
                            $display_ids = array_slice($sample_ids, 0, 2); // Show first 2 IDs
                            ?>
                            <div class="book-card">
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
                                    <span class="available-copies"><?php echo $book['available_copies']; ?> available</span> 
                                    / <?php echo $book['total_copies']; ?> total
                                </div>
                                <div class="book-author">by <?php echo htmlspecialchars($book['author'] ?? 'Unknown Author'); ?></div>
                                
                                <div class="book-meta">
                                    <span class="book-category"><?php echo htmlspecialchars($book['category'] ?? 'Uncategorized'); ?></span>
                                    <span class="book-year"><?php echo $book['published_year'] ?? 'N/A'; ?></span>
                                </div>
                                
                                <?php if (!empty($book['sample_book_ids'])): ?>
                                    <div class="sample-ids">
                                        Sample IDs: <?php echo htmlspecialchars(implode(', ', $display_ids)); ?>
                                        <br>
                                        <?php if (count($sample_ids) > 2): ?>
                                            ... (+<?php echo count($sample_ids) - 2; ?> more)
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="margin-bottom: 1rem;">
                                    <span class="status-badge <?php echo getStatusBadgeClass($book['display_status']); ?>">
                                        <?php echo ucfirst($book['display_status']); ?>
                                    </span>
                                </div>
                                
                                <div class="book-actions">
                                    <a href="edit_book.php?id=<?php echo $book['title_id']; ?>" class="btn-edit">
                                        ✏️ Edit
                                    </a>
                                    
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('Are you sure you want to delete this book and all its copies? This action cannot be undone.');">
                                        <input type="hidden" name="title_id" value="<?php echo $book['title_id']; ?>">
                                        <button type="submit" name="delete_book" class="btn-delete">
                                            🗑️ Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="<?php echo buildUrl(['page' => $page - 1]); ?>">← Previous</a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <?php if ($i === $page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="<?php echo buildUrl(['page' => $i]); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="<?php echo buildUrl(['page' => $page + 1]); ?>">Next →</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
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