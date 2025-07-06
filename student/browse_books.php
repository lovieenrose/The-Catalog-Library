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

// Handle search and filter parameters
$search_title = $_GET['title'] ?? '';
$search_author = $_GET['author'] ?? '';
$search_category = $_GET['category'] ?? '';
$search_book_id = $_GET['book_id'] ?? '';
$search_status = $_GET['status'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'title_asc'; // Default sort

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

// Build ORDER BY clause based on sort_by parameter
$order_clause = "ORDER BY ";
switch ($sort_by) {
    case 'title_asc':
        $order_clause .= "title ASC";
        break;
    case 'title_desc':
        $order_clause .= "title DESC";
        break;
    case 'author_asc':
        $order_clause .= "author ASC, title ASC";
        break;
    case 'author_desc':
        $order_clause .= "author DESC, title ASC";
        break;
    case 'year_asc':
        $order_clause .= "published_year ASC, title ASC";
        break;
    case 'year_desc':
        $order_clause .= "published_year DESC, title ASC";
        break;
    default:
        $order_clause .= "title ASC";
}

// Get all books with search filters and sorting
try {
    $query = "SELECT book_id, title, author, category, published_year, status, book_image FROM books $where_clause $order_clause";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $books = [];
    error_log("Error fetching books: " . $e->getMessage());
}

$total_books = count($books);

// Get filter options display text
function getFilterDisplayText($sort_by) {
    switch ($sort_by) {
        case 'title_asc': return 'A to Z';
        case 'title_desc': return 'Z to A';
        case 'author_asc': return 'Author (A-Z)';
        case 'author_desc': return 'Author (Z-A)';
        case 'year_asc': return 'Year (Oldest First)';
        case 'year_desc': return 'Year (Newest First)';
        default: return 'A to Z';
    }
}
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
    <style>
        /* Filter dropdown styles */
        .filter-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .filter-btn {
            background-color: var(--blush);
            border: 1px solid var(--black);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-family: 'Sniglet', sans-serif;
            font-size: 0.9rem;
            transition: background-color 0.3s ease;
        }
        
        .filter-btn:hover {
            background-color: var(--pinkish);
        }
        
        .filter-options {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: white;
            border: 1px solid var(--black);
            border-radius: 0.5rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            z-index: 1000;
            min-width: 180px;
            margin-top: 0.25rem;
        }
        
        .filter-options.show {
            display: block;
        }
        
        .filter-option {
            padding: 0.7rem 1rem;
            cursor: pointer;
            font-family: 'Sniglet', sans-serif;
            font-size: 0.9rem;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s ease;
        }
        
        .filter-option:last-child {
            border-bottom: none;
        }
        
        .filter-option:hover {
            background-color: var(--blush);
        }
        
        .filter-option.active {
            background-color: var(--pinkish);
            color: white;
            font-weight: bold;
        }
        
        .current-filter {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
        }
    </style>
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
    
    <form method="GET" action="browse_books.php" id="searchForm">
        <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars($sort_by); ?>">
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
        <div class="filter-dropdown">
            <div class="filter-btn" onclick="toggleFilterDropdown()">
                <span>⚙️</span>
                <span>Filter</span>
            </div>
            <div class="current-filter">
                Sorted by: <?php echo getFilterDisplayText($sort_by); ?>
            </div>
            <div class="filter-options" id="filterDropdown">
                <div class="filter-option <?php echo $sort_by === 'title_asc' ? 'active' : ''; ?>" onclick="applyFilter('title_asc')">
                    A to Z
                </div>
                <div class="filter-option <?php echo $sort_by === 'title_desc' ? 'active' : ''; ?>" onclick="applyFilter('title_desc')">
                    Z to A
                </div>
                <div class="filter-option <?php echo $sort_by === 'author_asc' ? 'active' : ''; ?>" onclick="applyFilter('author_asc')">
                    Author (A-Z)
                </div>
                <div class="filter-option <?php echo $sort_by === 'author_desc' ? 'active' : ''; ?>" onclick="applyFilter('author_desc')">
                    Author (Z-A)
                </div>
                <div class="filter-option <?php echo $sort_by === 'year_asc' ? 'active' : ''; ?>" onclick="applyFilter('year_asc')">
                    Year (Oldest First)
                </div>
                <div class="filter-option <?php echo $sort_by === 'year_desc' ? 'active' : ''; ?>" onclick="applyFilter('year_desc')">
                    Year (Newest First)
                </div>
            </div>
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
                <?php
                // Handle book image
                $book_image_path = '../uploads/book-images/' . ($book['book_image'] ?? 'default_book.jpg');
                $default_image_path = '../uploads/book-images/default_book.jpg';
                
                // Check if book image exists, fallback to default
                if (!$book['book_image'] || !file_exists($book_image_path)) {
                    $book_image_path = $default_image_path;
                }
                ?>
                <div class="book-card">
                    <div class="book-content">
                        <div class="book-image">
                            <img src="<?php echo htmlspecialchars($book_image_path); ?>" 
                                 alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                 onerror="this.src='../uploads/book-images/default_book.jpg'">
                        </div>
                        <div class="book-details">
                            <div class="book-info">
                                <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                                <div class="book-meta"><strong>Author:</strong> <?php echo htmlspecialchars($book['author'] ?? 'Unknown'); ?></div>
                                <div class="book-meta"><strong>Category:</strong> <?php echo htmlspecialchars($book['category'] ?? 'General'); ?></div>
                                <div class="book-meta"><strong>Published:</strong> <?php echo htmlspecialchars($book['published_year'] ?? 'N/A'); ?></div>
                                <div class="book-meta"><strong>Status:</strong> 
                                    <span class="book-status <?php echo $book['status'] === 'Available' ? 'status-available' : 'status-borrowed'; ?>">
                                        <?php echo htmlspecialchars($book['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="button-container">
    <?php if ($book['status'] === 'Available'): ?>
        <button class="borrow-btn" onclick="showBorrowModal(
            <?php echo $book['book_id']; ?>, 
            '<?php echo addslashes($book['title']); ?>', 
            '<?php echo addslashes($book['author'] ?? 'Unknown'); ?>', 
            '<?php echo addslashes($book['category'] ?? 'General'); ?>',
            '<?php echo addslashes($book['published_year'] ?? 'N/A'); ?>',
            '<?php echo addslashes($book_image_path); ?>',
            '<?php echo addslashes($book['status']); ?>'
        )">Borrow Now</button>
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
            <div class="modal-book-image">
                <img id="modalBookImage" src="" alt="" 
                     onerror="this.src='../uploads/book-images/default_book.jpg'">
            </div>
            <div class="modal-book-details">
                <h3 id="modalBookTitle"></h3>
                <p><strong>Author:</strong> <span id="modalBookAuthor"></span></p>
                <p><strong>Category:</strong> <span id="modalBookCategory"></span></p>
                <p><strong>Published:</strong> <span id="modalBookPublished"></span></p>
                <p><strong>Status:</strong> <span class="status-available"></span></p>
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

// Filter functionality
function toggleFilterDropdown() {
    const dropdown = document.getElementById('filterDropdown');
    dropdown.classList.toggle('show');
}

function applyFilter(sortBy) {
    const url = new URL(window.location);
    url.searchParams.set('sort_by', sortBy);
    window.location.href = url.toString();
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('filterDropdown');
    const filterBtn = event.target.closest('.filter-btn');
    
    if (!filterBtn && !dropdown.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});

// FIXED showBorrowModal function with forced image constraints
function showBorrowModal(bookId, title, author, category, publishedYear, imagePath, status) {
    console.log('showBorrowModal called with:', {
        bookId: bookId,
        title: title, 
        author: author, 
        category: category, 
        publishedYear: publishedYear, 
        imagePath: imagePath,
        status: status
    });
    
    currentBookId = bookId;
    
    // Set ALL book details
    document.getElementById('modalBookTitle').textContent = title;
    document.getElementById('modalBookAuthor').textContent = author;
    document.getElementById('modalBookCategory').textContent = category;
    document.getElementById('modalBookPublished').textContent = publishedYear || 'N/A';
    
    // Update status
    const statusElement = document.querySelector('#borrowModal .status-available');
    if (statusElement) {
        statusElement.textContent = status || 'Available';
    }
    
    // FIXED: Handle the image with forced constraints
    const modalBookImage = document.getElementById('modalBookImage');
    const modalImageContainer = document.querySelector('.modal-book-image');
    
    if (modalBookImage && modalImageContainer) {
        console.log('Setting up modal image with forced constraints...');
        
        // Reset any inline styles that might interfere
        modalBookImage.removeAttribute('style');
        
        // FORCE the container sizes immediately
        modalImageContainer.style.width = '120px';
        modalImageContainer.style.height = '180px';
        modalImageContainer.style.overflow = 'hidden';
        modalImageContainer.style.position = 'relative';
        modalImageContainer.style.flexShrink = '0';
        modalImageContainer.style.display = 'block';
        modalImageContainer.style.boxSizing = 'border-box';
        
        // FORCE the image constraints before setting the source
        modalBookImage.style.width = '120px';
        modalBookImage.style.height = '180px';
        modalBookImage.style.maxWidth = '120px';
        modalBookImage.style.maxHeight = '180px';
        modalBookImage.style.minWidth = '120px';
        modalBookImage.style.minHeight = '180px';
        modalBookImage.style.objectFit = 'cover';
        modalBookImage.style.position = 'absolute';
        modalBookImage.style.top = '0';
        modalBookImage.style.left = '0';
        modalBookImage.style.right = '0';
        modalBookImage.style.bottom = '0';
        modalBookImage.style.margin = '0';
        modalBookImage.style.padding = '0';
        modalBookImage.style.display = 'block';
        modalBookImage.style.borderRadius = '6px';
        modalBookImage.style.zIndex = '1';
        modalBookImage.style.transform = 'none';
        modalBookImage.style.boxSizing = 'border-box';
        
        // Set the image source with fallback
        const imageToUse = imagePath && imagePath !== '../uploads/book-images/default_book.jpg' ? imagePath : '../uploads/book-images/default_book.jpg';
        modalBookImage.src = imageToUse;
        modalBookImage.alt = title;
        
        console.log('Modal image configured with path:', imageToUse);
        
        // Handle image load/error events with constraint re-enforcement
        modalBookImage.onload = function() {
            console.log('✅ Modal image loaded successfully');
            
            // RE-ENFORCE constraints after image loads (critical!)
            this.style.width = '120px';
            this.style.height = '180px';
            this.style.maxWidth = '120px';
            this.style.maxHeight = '180px';
            this.style.objectFit = 'cover';
            this.style.position = 'absolute';
            this.style.top = '0';
            this.style.left = '0';
            this.style.transform = 'none';
            
            // Show the image container when image loads
            modalImageContainer.style.display = 'block';
            
            console.log('✅ Image constraints re-enforced after load');
        };
        
        modalBookImage.onerror = function() {
            console.log('❌ Modal image failed to load, using default');
            this.src = '../uploads/book-images/default_book.jpg';
            
            // Apply constraints to fallback image too
            this.style.width = '120px';
            this.style.height = '180px';
            this.style.maxWidth = '120px';
            this.style.maxHeight = '180px';
            this.style.objectFit = 'cover';
            this.style.position = 'absolute';
            this.style.top = '0';
            this.style.left = '0';
        };
    } else {
        console.error('❌ Modal image elements not found!');
    }
    
    // Show modal and prevent body scroll
    const modal = document.getElementById('borrowModal');
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // FORCE modal layout constraints
    const modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
        modalContent.style.maxWidth = '600px';
        modalContent.style.overflow = 'hidden';
        modalContent.style.boxSizing = 'border-box';
    }
    
    // FORCE book info constraints
    const modalBookInfo = modal.querySelector('.modal-book-info');
    if (modalBookInfo) {
        modalBookInfo.style.overflow = 'hidden';
        modalBookInfo.style.width = '100%';
        modalBookInfo.style.maxWidth = '100%';
        modalBookInfo.style.boxSizing = 'border-box';
    }
    
    // FORCE book details constraints
    const modalBookDetails = modal.querySelector('.modal-book-details');
    if (modalBookDetails) {
        modalBookDetails.style.minWidth = '0';
        modalBookDetails.style.maxWidth = 'calc(100% - 140px)';
        modalBookDetails.style.overflow = 'hidden';
        modalBookDetails.style.overflowWrap = 'break-word';
        modalBookDetails.style.wordWrap = 'break-word';
        modalBookDetails.style.boxSizing = 'border-box';
    }
    
    console.log('Modal displayed with forced constraints');
}

function closeBorrowModal() {
    document.getElementById('borrowModal').style.display = 'none';
    currentBookId = null;
    
    // Restore body scroll
    document.body.style.overflow = '';
    
    console.log('Modal closed');
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
    const confirmBtn = document.getElementById('confirmBorrow');
    confirmBtn.textContent = 'Processing...';
    confirmBtn.disabled = true;
    
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

// Additional safety: Re-enforce constraints when modal becomes visible
const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
            const modal = document.getElementById('borrowModal');
            if (modal && modal.style.display === 'block') {
                // Modal is now visible, double-check image constraints
                const modalBookImage = document.getElementById('modalBookImage');
                const modalImageContainer = document.querySelector('.modal-book-image');
                
                if (modalBookImage && modalImageContainer) {
                    // Small delay to let any other scripts finish
                    setTimeout(function() {
                        // Re-enforce container constraints
                        modalImageContainer.style.width = '120px';
                        modalImageContainer.style.height = '180px';
                        modalImageContainer.style.overflow = 'hidden';
                        
                        // Re-enforce image constraints
                        modalBookImage.style.width = '120px';
                        modalBookImage.style.height = '180px';
                        modalBookImage.style.maxWidth = '120px';
                        modalBookImage.style.maxHeight = '180px';
                        modalBookImage.style.objectFit = 'cover';
                        modalBookImage.style.position = 'absolute';
                        modalBookImage.style.top = '0';
                        modalBookImage.style.left = '0';
                        
                        console.log('🔒 Image constraints re-enforced by observer');
                    }, 50);
                }
            }
        }
    });
});

// Start observing the modal for changes
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('borrowModal');
    if (modal) {
        observer.observe(modal, {
            attributes: true,
            attributeFilter: ['style']
        });
        console.log('📋 Modal observer initialized');
    }
});
</script>


<?php include '../includes/footer.php'; ?>

</body>
</html>