<?php 
require_once '../includes/session.php';
require_once '../includes/db.php';

// Get user information from session
$user_id = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Student';

// Get current borrowed books count for the user
$borrowed_count = 0;
if ($user_id) {
    try {
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM borrowed_books WHERE user_id = ? AND status IN ('borrowed', 'overdue')");
        $count_stmt->execute([$user_id]);
        $count_result = $count_stmt->fetch();
        $borrowed_count = $count_result['count'];
    } catch (PDOException $e) {
        error_log("Error getting borrowed count: " . $e->getMessage());
    }
}

// Handle search parameters
$search_title = $_GET['title'] ?? '';
$search_author = $_GET['author'] ?? '';
$search_category = $_GET['category'] ?? '';
$search_book_id = $_GET['book_id'] ?? '';
$search_status = $_GET['status'] ?? '';

// Build the WHERE clause for search
$where_conditions = [];
$params = [];

if (!empty($search_title)) {
    $where_conditions[] = "title LIKE ?";
    $params[] = "%$search_title%";
}

if (!empty($search_author)) {
    $where_conditions[] = "author LIKE ?";
    $params[] = "%$search_author%";
}

if (!empty($search_category)) {
    $where_conditions[] = "category = ?";
    $params[] = $search_category;
}

if (!empty($search_book_id)) {
    $where_conditions[] = "book_id = ?";
    $params[] = $search_book_id;
}

if (!empty($search_status)) {
    $where_conditions[] = "status = ?";
    $params[] = $search_status;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get all books with search filters
try {
    $query = "SELECT * FROM books $where_clause ORDER BY title ASC";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $books = [];
    error_log("Error fetching books: " . $e->getMessage());
}

$total_books = count($books);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Browse Books - The Cat-alog Library</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/browse_books.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">

</head>
<body>

<!-- Banner Section with Navigation and Welcome -->
<section class="browse-header">
    <img src="../assets/images/library-banner.jpg" alt="Library Banner" class="banner-bg">
    <!-- Navigation Overlay -->
    <header class="main-header">
        <div class="logo-title">
            <img src="../assets/images/logo.png" alt="Logo" class="banner-logo-dashboard">
            <h1 class="sniglet-extrabold">The Cat-alog Library</h1>
        </div>
        <nav class="main-nav">
            <ul class="nav-menu">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="browse_books.php" class="active">Browse Books</a></li>
                <li><a href="my_borrowed.php">My Borrowed Books</a></li>
            </ul>
        </nav>
        <a href="logout.php" class="logout-btn">Log Out</a>
    </header>

    <!-- Welcome Content -->
    <div class="welcome-content">
        <h2 class="sniglet-extrabold">Browse Books</h2>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
    </div>
</section>

<!-- Search Section -->
<section class="search-section">
    <?php if (isset($_SESSION['borrow_error'])): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($_SESSION['borrow_error']); ?>
            <?php unset($_SESSION['borrow_error']); ?>
        </div>
    <?php endif; ?>
    
    <form method="GET" action="browse_books.php">
        <div class="search-bar">
            <input type="text" name="title" placeholder="Search By Title" value="<?php echo htmlspecialchars($search_title); ?>">
            <button type="submit">🔍</button>
        </div>
    </form>
</section>

<!-- Books Section -->
<section class="books-section">
    <div class="books-header">
        <h3>All Books (<?php echo $total_books; ?>)</h3>
        <div class="filter-btn">
            <span>⚙️</span>
            <span>Filter</span>
        </div>
    </div>

    <?php if (empty($books)): ?>
        <div class="no-books">
            <img src="../assets/images/no-books.png" alt="No books found">
            <h3>No books found</h3>
            <p>Try adjusting your search criteria or browse all available books.</p>
            <a href="browse_books.php" class="btn" style="display: inline-block; margin-top: 15px;">View All Books</a>
        </div>
    <?php else: ?>
        <div class="books-grid">
            <?php foreach ($books as $book): ?>
                <div class="book-card">
                    <div class="book-content">
                        <div class="book-image"></div>
                        <div class="book-details">
                            <div class="book-info">
                                <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                                <div class="book-meta"><strong>Author:</strong> <?php echo htmlspecialchars($book['author'] ?? 'Unknown'); ?></div>
                                <div class="book-meta"><strong>Category:</strong> <?php echo htmlspecialchars($book['category'] ?? 'General'); ?></div>
                                <div class="book-meta"><strong>Published:</strong> <?php echo htmlspecialchars($book['created_at'] ? date('Y', strtotime($book['created_at'])) : 'N/A'); ?></div>
                                <div class="book-meta"><strong>Status:</strong> 
                                    <span class="book-status <?php echo $book['status'] === 'Available' ? 'status-available' : 'status-borrowed'; ?>">
                                        <?php echo htmlspecialchars($book['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="button-container">
                                <?php if ($book['status'] === 'Available'): ?>
                                    <button class="borrow-btn" onclick="showBorrowModal(<?php echo $book['book_id']; ?>, '<?php echo addslashes($book['title']); ?>', '<?php echo addslashes($book['author'] ?? 'Unknown'); ?>', '<?php echo addslashes($book['category'] ?? 'General'); ?>')">Borrow Now</button>
                                <?php else: ?>
                                    <button class="borrow-btn" disabled>Not Available</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Borrow Modal -->
<div id="borrowModal" class="modal">
    <div class="modal-content">
        <h2>You are about to Borrow:</h2>
        <div class="modal-book-info">
            <div class="modal-book-image"></div>
            <div class="modal-book-details">
                <h3 id="modalBookTitle"></h3>
                <p><strong>Author:</strong> <span id="modalBookAuthor"></span></p>
                <p><strong>Category:</strong> <span id="modalBookCategory"></span></p>
                <p><strong>Published:</strong> <span id="modalBookPublished">1937</span></p>
                <p><strong>Status:</strong> <span class="status-available">Available</span></p>
            </div>
        </div>
        <div class="borrow-note">
            <p><strong>Note:</strong> You may borrow up to 2 books for a period of 7 days (including weekends). A ₱10.00 fine will be charged per day for each overdue book.</p>
            <p><strong>Current Status:</strong> You have borrowed <span id="currentBorrowedCount"><?php echo $borrowed_count; ?></span> out of 2 books.</p>
        </div>
        <div class="modal-buttons">
            <button id="confirmBorrow" class="btn-confirm">Confirm</button>
            <button onclick="closeBorrowModal()" class="btn-cancel">Cancel</button>
        </div>
    </div>
</div>

<script>
let currentBookId = null;
const borrowedCount = <?php echo $borrowed_count; ?>;
const maxBorrowLimit = 2;

function showBorrowModal(bookId, title, author, category) {
    currentBookId = bookId;
    document.getElementById('modalBookTitle').textContent = title;
    document.getElementById('modalBookAuthor').textContent = author;
    document.getElementById('modalBookCategory').textContent = category;
    document.getElementById('borrowModal').style.display = 'block';
}

function closeBorrowModal() {
    document.getElementById('borrowModal').style.display = 'none';
    currentBookId = null;
}

document.getElementById('confirmBorrow').onclick = function() {
    if (currentBookId) {
        // Check borrowing limit before proceeding
        if (borrowedCount >= maxBorrowLimit) {
            alert('⚠️ Borrowing Limit Reached!\n\nYou have already borrowed ' + borrowedCount + ' books. You can only borrow a maximum of ' + maxBorrowLimit + ' books at a time.\n\nPlease return a book before borrowing a new one.');
            closeBorrowModal();
            return;
        }
        borrowBook(currentBookId);
    }
};

function borrowBook(bookId) {
    // Double-check the limit client-side
    if (borrowedCount >= maxBorrowLimit) {
        alert('⚠️ You have reached the maximum borrowing limit of ' + maxBorrowLimit + ' books.');
        return;
    }
    
    // Show loading state
    document.getElementById('confirmBorrow').textContent = 'Processing...';
    document.getElementById('confirmBorrow').disabled = true;
    
    // Create a form and submit it
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'borrow_book.php';
    
    const bookInput = document.createElement('input');
    bookInput.type = 'hidden';
    bookInput.name = 'book_id';
    bookInput.value = bookId;
    
    form.appendChild(bookInput);
    document.body.appendChild(form);
    form.submit();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('borrowModal');
    if (event.target === modal) {
        closeBorrowModal();
    }
}
</script>

<?php include '../includes/footer.php'; ?>

</body>
</html>