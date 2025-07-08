<?php 
require_once '../includes/session.php';
require_once '../includes/db.php';

// Get user information from session with fallbacks
$user_id = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Student';

// If we have a user_id and no full name, try to get it from database
if ($user_id && (!isset($_SESSION['user_name']) || empty($_SESSION['user_name']))) {
    try {
        // Use the new 'users' table structure
        $stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if ($user) {
            $user_name = trim($user['first_name'] . ' ' . $user['last_name']);
            $_SESSION['user_name'] = $user_name;
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
    }
}

// Get borrowed books for the current user (limit to 2 for preview)
$borrowed_books = [];
if ($user_id) {
    try {
        // Updated query to work with new database structure
        $borrowed_query = "
            SELECT 
                bt.title,
                bb.due_date,
                bb.status,
                bb.borrow_date,
                bc.book_id,
                bc.copy_number
            FROM borrowed_books bb
            JOIN book_copies bc ON bb.copy_id = bc.copy_id
            JOIN book_titles bt ON bb.title_id = bt.title_id
            WHERE bb.user_id = ? AND bb.status IN ('borrowed', 'overdue')
            ORDER BY bb.due_date ASC
            LIMIT 2
        ";
        
        $stmt = $conn->prepare($borrowed_query);
        $stmt->execute([$user_id]);
        $borrowed_books = $stmt->fetchAll();
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
        return ['text' => 'Overdue', 'class' => 'status-red'];
    } else {
        return ['text' => 'On Time', 'class' => 'status-green'];
    }
}

// Get available categories for the dropdown (updated for new structure)
$categories = [];
try {
    $cat_stmt = $conn->prepare("SELECT DISTINCT category FROM book_titles WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
    $cat_stmt->execute();
    $categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}

// Get year range for published years (updated for new structure)
$year_range = [];
try {
    $year_stmt = $conn->prepare("SELECT MIN(published_year) as min_year, MAX(published_year) as max_year FROM book_titles WHERE published_year IS NOT NULL");
    $year_stmt->execute();
    $year_data = $year_stmt->fetch();
    if ($year_data && $year_data['min_year'] && $year_data['max_year']) {
        $year_range = ['min' => $year_data['min_year'], 'max' => $year_data['max_year']];
    }
} catch (PDOException $e) {
    error_log("Error fetching year range: " . $e->getMessage());
}

// Get sample books for the catalog display
$fiction_books = [];
$nonfiction_books = [];
try {
    // Get fiction books
    $fiction_stmt = $conn->prepare("SELECT book_image FROM book_titles WHERE category = 'Fiction' AND book_image IS NOT NULL LIMIT 3");
    $fiction_stmt->execute();
    $fiction_books = $fiction_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get non-fiction books
    $nonfiction_stmt = $conn->prepare("SELECT book_image FROM book_titles WHERE category = 'Non-Fiction' AND book_image IS NOT NULL LIMIT 3");
    $nonfiction_stmt->execute();
    $nonfiction_books = $nonfiction_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching sample books: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard - The Cat-alog Library</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
    <?php include '../includes/favicon.php'; ?>
    <style>
        /* Additional styles for enhanced search form */
        .year-inputs {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .year-inputs input {
            flex: 1;
            min-width: 80px;
        }
        
        .year-separator {
            font-weight: bold;
            color: #666;
            padding: 0 0.25rem;
        }
        
        .search-form .form-row-year {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .search-form .form-row-year label {
            font-family: 'Sniglet', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--black);
            white-space: nowrap;
        }

        .form-row .images {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .ads-image {
            width: 100%;        /* or a specific value like 300px */
            max-width: 400px;   /* ensures it doesn't grow too large */
            height: auto;       /* maintains aspect ratio */
            display: block;     /* removes extra spacing below image */
            top margin: 10px auto;  /* centers the image horizontally */
        }

    </style>
</head>
<body>

<!-- Navigation Header - SEPARATE FROM BANNER -->
<header class="main-header">
    <div class="logo-title">
        <img src="../assets/images/logo.png" alt="Logo" class="banner-logo-dashboard">
        <h1 class="sniglet-extrabold">The Cat-alog Library</h1>
    </div>
    <nav class="main-nav">
        <ul class="nav-menu">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="browse_books.php">Browse Books</a></li>
            <li><a href="my_borrowed.php">My Borrowed Books</a></li>
        </ul>
    </nav>
    <a href="logout.php" class="logout-btn">Log Out</a>
</header>

<!-- Banner Section - ONLY WELCOME CONTENT -->
<section class="banner-section">
    <img src="../assets/images/banner-dashboard.png" alt="Library Banner" class="banner-bg">
    
    <!-- Welcome Content -->
    <div class="welcome-content">
        <h2 class="sniglet-extrabold">Welcome to the Dashboard, <?php echo htmlspecialchars($user_name); ?>!</h2>
        <p>We're thrilled to have you back. Whether you're here to borrow a book, check your records, or simply browse our growing collection, you're in the purr-fect place.
        Let your next great read begin here. Happy reading!</p>
    </div>
</section>

<!-- Main Dashboard Content -->
<main class="dashboard-grid">
    <!-- Book Catalog -->
<section class="dashboard-box">
    <h3>Book Catalog</h3>
    <div class="book-catalog">
        <h4>Fiction Books</h4>
        <div class="book-row">
            <?php if (!empty($fiction_books)): ?>
                <?php foreach ($fiction_books as $image): ?>
                    <img src="../uploads/book-images/<?php echo htmlspecialchars($image); ?>" alt="Fiction Book">
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Fallback images if no books found -->
                <img src="../uploads/book-images/fic1.png" alt="Fiction Book">
                <img src="../uploads/book-images/fic2.png" alt="Fiction Book">
                <img src="../uploads/book-images/fic3.png" alt="Fiction Book">
            <?php endif; ?>
        </div>
        <h4>Non-Fiction Books</h4>
        <div class="book-row">
            <?php if (!empty($nonfiction_books)): ?>
                <?php foreach ($nonfiction_books as $image): ?>
                    <img src="../uploads/book-images/<?php echo htmlspecialchars($image); ?>" alt="Non-Fiction Book">
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Fallback images if no books found -->
                <img src="../uploads/book-images/nonfic1.png" alt="Non-Fiction Book">
                <img src="../uploads/book-images/nonfic2.png" alt="Non-Fiction Book">
                <img src="../uploads/book-images/nonfic3.png" alt="Non-Fiction Book">
            <?php endif; ?>
        </div>
        <div class="more-books-text">
            More books available in Browse Books!
        </div>
    </div>
    <a href="browse_books.php" class="btn">Browse All Books</a>
</section>

    <!-- Advanced Search -->
    <section class="dashboard-box">
        <h3>Advanced Search</h3>
        <form action="browse_books.php" method="get" class="search-form">
            <div class="form-row">
                <input type="text" name="title" placeholder="Title">
            </div>
            <div class="form-row-split">
                <input type="text" name="author" placeholder="Author">
                <select name="category">
                    <option value="">Category</option>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>">
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="Fiction">Fiction</option>
                        <option value="Non-Fiction">Non-Fiction</option>
                        <option value="Romance">Romance</option>
                        <option value="Childrens Books">Children's Books</option>
                        <option value="Science and Technology">Science and Technology</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-row">
                <input type="number" name="published_year" placeholder="Published Year (e.g., 1997)" 
                       min="<?php echo $year_range['min'] ?? 1000; ?>" 
                       max="<?php echo date('Y'); ?>" 
                       title="Enter published year">
            </div>
            <div class="form-row">
                <input type="text" name="book_id" placeholder="Book ID (Optional)" title="Enter specific book copy ID like AGAUG071996-FIC00101">
            </div>
            <div class="form-row">
                <select name="status">
                    <option value="">Book Status</option>
                    <option value="Available">Available</option>
                    <option value="Borrowed">Currently Borrowed</option>
                </select>
            </div>
            <div class="form-row">
                <img src="../assets/images/ads.png" alt="Advertisement" class="ads-image">
            </div>
            <button type="submit" class="btn">Search</button>
        </form>
    </section>

<!-- Student Profile and Borrowed Books -->
<section class="dashboard-box">
    <h3>Student Profile</h3>
    <div class="student-profile">
        <img src="../assets/images/student-icon.jpg" alt="Student Icon" class="student-avatar">
        <p class="sniglet-regular" style="font-weight: normal;"><?php echo htmlspecialchars($user_name); ?></p>
    </div>

    <h3>My Borrowed Books</h3>
    <div class="borrowed-books-container">
        <?php if (empty($borrowed_books)): ?>
            <div class="no-books-message">
                <p>You currently have no borrowed books.</p>
            </div>
        <?php else: ?>
            <table class="borrowed-table">
                <thead>
                    <tr>
                        <th>Book Title</th>
                        <th>Copy ID</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($borrowed_books as $book): ?>
                        <?php $status_info = getStatusDisplay($book['due_date'], $book['status']); ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($book['title']); ?></strong></td>
                            <td><small><?php echo htmlspecialchars($book['book_id']); ?></small></td>
                            <td><?php echo date('M j, Y', strtotime($book['due_date'])); ?></td>
                            <td class="<?php echo $status_info['class']; ?>"><?php echo $status_info['text']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <?php if (empty($borrowed_books)): ?>
        <a href="browse_books.php" class="btn">Browse Books to Borrow</a>
    <?php else: ?>
        <a href="my_borrowed.php" class="btn">View All</a>
    <?php endif; ?>
</section>
</main>

<?php include '../includes/footer.php'; ?>

</body>
</html>