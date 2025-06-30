<?php 
require_once '../includes/session.php';
require_once '../includes/db.php';

// Get user information from session
$user_id = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Student';

// Get all borrowed books for the current user
$borrowed_books = [];
if ($user_id) {
    try {
        $borrowed_query = "
            SELECT 
                b.book_id,
                b.title,
                b.author,
                b.category,
                b.published_year,
                b.book_image,
                bb.borrow_date,
                bb.due_date,
                bb.status,
                bb.id as borrow_id
            FROM borrowed_books bb
            JOIN books b ON bb.book_id = b.book_id
            WHERE bb.user_id = ? AND bb.status IN ('borrowed', 'overdue')
            ORDER BY bb.due_date ASC
        ";
        
        $stmt = $conn->prepare($borrowed_query);
        $stmt->execute([$user_id]);
        $borrowed_books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching borrowed books: " . $e->getMessage());
        $borrowed_books = [];
    }
}

// Function to determine status display and class
function getStatusDisplay($due_date, $status) {
    $today = new DateTime();
    $due = new DateTime($due_date);
    
    if ($status === 'overdue' || $due < $today) {
        return ['text' => 'Overdue', 'class' => 'status-overdue'];
    } else {
        return ['text' => 'On Time', 'class' => 'status-ontime'];
    }
}

// Calculate days remaining
function getDaysRemaining($due_date) {
    $today = new DateTime();
    $due = new DateTime($due_date);
    $diff = $today->diff($due);
    
    if ($due < $today) {
        return -$diff->days; // Negative for overdue
    } else {
        return $diff->days;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Borrowed Books - The Cat-alog Library</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/my_borrowed.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
</head>
<body>

<!-- Banner Section with Navigation and Welcome -->
<section class="borrowed-header">
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
                <li><a href="browse_books.php">Browse Books</a></li>
                <li><a href="my_borrowed.php" class="active">My Borrowed Books</a></li>
            </ul>
        </nav>
        <a href="logout.php" class="logout-btn">Log Out</a>
    </header>

    <!-- Welcome Content -->
    <div class="welcome-content">
        <h2 class="sniglet-extrabold">My Borrowed Books</h2>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
    </div>
</section>

<!-- Borrowed Books Section -->
<section class="borrowed-books-section">
    <?php if (isset($_SESSION['borrow_success'])): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($_SESSION['borrow_success']); ?>
            <?php unset($_SESSION['borrow_success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (empty($borrowed_books)): ?>
        <div class="no-books-container">
            <div class="no-books-message">
                <h3>No Borrowed Books</h3>
                <p>You currently have no borrowed books.</p>
                <a href="browse_books.php" class="btn">Browse Books to Borrow</a>
            </div>
        </div>
    <?php else: ?>
        <div class="borrowed-books-grid">
            <?php foreach ($borrowed_books as $book): ?>
                <?php 
                $status_info = getStatusDisplay($book['due_date'], $book['status']);
                $days_remaining = getDaysRemaining($book['due_date']);
                
                // Handle book image - carefully check if image exists
                $book_image_src = '';
                if (!empty($book['book_image'])) {
                    $book_image_path = '../uploads/book-images/' . $book['book_image'];
                    if (file_exists($book_image_path)) {
                        $book_image_src = $book_image_path;
                    }
                }
                // If no valid image, keep it empty to show brown placeholder
                ?>
                <div class="borrowed-book-card">
                    <div class="book-image-container">
                        <div class="book-image" <?php if ($book_image_src): ?>style="background-image: url('<?php echo htmlspecialchars($book_image_src); ?>'); background-size: cover; background-position: center;"<?php endif; ?>>
                        </div>
                    </div>
                    <div class="book-details">
                        <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                        <div class="book-meta">
                            <p><strong>Author:</strong> <?php echo htmlspecialchars($book['author'] ?? 'Unknown'); ?></p>
                            <p><strong>Category:</strong> <?php echo htmlspecialchars($book['category'] ?? 'General'); ?></p>
                            <p><strong>Published:</strong> <?php echo htmlspecialchars($book['published_year'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="borrow-info">
                            <div class="borrow-details">
                                <p><strong>Date Borrowed:</strong> <?php echo date('n/j/y', strtotime($book['borrow_date'])); ?></p>
                                <p><strong>Return Book By:</strong> <?php echo date('n/j/y', strtotime($book['due_date'])); ?></p>
                                <p class="status <?php echo $status_info['class']; ?>">
                                    <strong>Status:</strong> <?php echo $status_info['text']; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php include '../includes/footer.php'; ?>

</body>
</html>