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
$book = null;
$book_copies = [];

// Get book ID from URL (now title_id)
$title_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($title_id <= 0) {
    header('Location: manage_books.php?error=Invalid book ID');
    exit();
}

// Fetch book data (updated for new structure)
try {
    $stmt = $conn->prepare("SELECT * FROM book_titles WHERE title_id = ?");
    $stmt->execute([$title_id]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$book) {
        header('Location: manage_books.php?error=Book not found');
        exit();
    }
    
    // Get all copies of this book
    $copiesStmt = $conn->prepare("
        SELECT bc.*, 
               CASE WHEN bb.copy_id IS NOT NULL THEN 1 ELSE 0 END as is_borrowed,
               CONCAT(u.first_name, ' ', u.last_name) as borrowed_by
        FROM book_copies bc
        LEFT JOIN borrowed_books bb ON bc.copy_id = bb.copy_id AND bb.status IN ('borrowed', 'overdue', 'renewed')
        LEFT JOIN users u ON bb.user_id = u.user_id
        WHERE bc.title_id = ?
        ORDER BY bc.copy_number
    ");
    $copiesStmt->execute([$title_id]);
    $book_copies = $copiesStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    header('Location: manage_books.php?error=Database error');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $published_year = trim($_POST['published_year'] ?? '');
    $published_month = trim($_POST['published_month'] ?? '');
    $status = $_POST['status'] ?? 'Available';
    
    // Validation
    if (empty($title)) {
        $errors[] = "Book title is required.";
    }
    
    if (empty($author)) {
        $errors[] = "Author name is required.";
    }
    
    if (empty($category)) {
        $errors[] = "Category is required.";
    }
    
    if (!empty($published_year) && (!is_numeric($published_year) || $published_year < 1000 || $published_year > date('Y'))) {
        $errors[] = "Please enter a valid publication year.";
    }
    
    // Handle file upload
    $book_image = $book['book_image']; // Keep existing image by default
    $remove_image = isset($_POST['remove_image']);
    
    if ($remove_image) {
        $book_image = null;
    }
    
    if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/book-images/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_tmp = $_FILES['book_image']['tmp_name'];
        $file_name = $_FILES['book_image']['name'];
        $file_size = $_FILES['book_image']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $max_file_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file_ext, $allowed_extensions)) {
            $errors[] = "Only JPG, JPEG, PNG, GIF, and WEBP files are allowed.";
        } elseif ($file_size > $max_file_size) {
            $errors[] = "File size must be less than 5MB.";
        } else {
            // Remove old image if exists
            if ($book['book_image'] && file_exists($upload_dir . $book['book_image'])) {
                unlink($upload_dir . $book['book_image']);
            }
            
            // Generate unique filename
            $book_image = uniqid() . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $book_image;
            
            if (!move_uploaded_file($file_tmp, $upload_path)) {
                $errors[] = "Failed to upload image.";
                $book_image = $book['book_image']; // Revert to original
            }
        }
    } elseif ($remove_image && $book['book_image']) {
        // Remove old image file
        $upload_dir = '../uploads/book-images/';
        if (file_exists($upload_dir . $book['book_image'])) {
            unlink($upload_dir . $book['book_image']);
        }
    }
    
    // If no errors, update database (updated for new structure)
    if (empty($errors)) {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Update book_titles table
            $sql = "UPDATE book_titles SET title = ?, author = ?, category = ?, published_year = ?, published_month = ?, book_image = ?, status = ? WHERE title_id = ?";
            
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                $title,
                $author,
                $category,
                $published_year ?: null,
                $published_month ?: null,
                $book_image,
                $status,
                $title_id
            ]);
            
            if ($result) {
                // Update available_copies count based on current copy statuses
                $updateCopiesStmt = $conn->prepare("
                    UPDATE book_titles 
                    SET available_copies = (
                        SELECT COUNT(*) 
                        FROM book_copies 
                        WHERE title_id = ? AND status = 'Available'
                    )
                    WHERE title_id = ?
                ");
                $updateCopiesStmt->execute([$title_id, $title_id]);
                
                $conn->commit();
                
                $message = "Book updated successfully!";
                $message_type = "success";
                
                // Refresh book data
                $stmt = $conn->prepare("SELECT * FROM book_titles WHERE title_id = ?");
                $stmt->execute([$title_id]);
                $book = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Refresh copies data
                $copiesStmt = $conn->prepare("
                    SELECT bc.*, 
                           CASE WHEN bb.copy_id IS NOT NULL THEN 1 ELSE 0 END as is_borrowed,
                           CONCAT(u.first_name, ' ', u.last_name) as borrowed_by
                    FROM book_copies bc
                    LEFT JOIN borrowed_books bb ON bc.copy_id = bb.copy_id AND bb.status IN ('borrowed', 'overdue', 'renewed')
                    LEFT JOIN users u ON bb.user_id = u.user_id
                    WHERE bc.title_id = ?
                    ORDER BY bc.copy_number
                ");
                $copiesStmt->execute([$title_id]);
                $book_copies = $copiesStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $conn->rollBack();
                $errors[] = "Failed to update book in database.";
            }
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $message_type = "error";
    }
}

// Get existing categories for dropdown
try {
    $categoryStmt = $conn->prepare("SELECT DISTINCT category FROM book_titles WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categoryStmt->execute();
    $existing_categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $existing_categories = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Book - The Cat-alog Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../assets/css/admin_add_book.css">
    <?php include '../includes/favicon.php'; ?>
    <style>
        .current-image-container {
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .current-image {
            max-width: 200px;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: 2px solid #ddd;
        }
        
        .checkbox-group {
            margin-top: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .checkbox-group label {
            margin: 0;
            font-size: 0.9rem;
            color: #666;
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
                <h1 class="page-title">✏️ Edit Book</h1>
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

            <!-- Book Copies Info Section -->
            <div class="book-id-explanation">
                <h5>📖 Current Book Copies (<?php echo count($book_copies); ?> total, <?php echo $book['available_copies']; ?> available)</h5>
                <?php if (!empty($book_copies)): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; margin: 1rem 0;">
                        <?php foreach ($book_copies as $copy): ?>
                            <div style="background: <?php echo $copy['is_borrowed'] ? '#ffe6e6' : '#e8f5e9'; ?>; padding: 0.8rem; border-radius: 0.5rem; border: 1px solid <?php echo $copy['is_borrowed'] ? '#f5c6cb' : '#c3e6cb'; ?>;">
                                <strong>Copy #<?php echo $copy['copy_number']; ?>:</strong> <?php echo htmlspecialchars($copy['book_id']); ?><br>
                                <small>Status: <?php echo $copy['status']; ?> | Condition: <?php echo $copy['condition_status']; ?>
                                <?php if ($copy['is_borrowed']): ?>
                                    <br><span style="color: #d32f2f; font-weight: bold;">⚠️ Currently Borrowed by <?php echo htmlspecialchars($copy['borrowed_by']); ?></span>
                                <?php endif; ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #d32f2f;">⚠️ No copies found for this book.</p>
                <?php endif; ?>
            </div>

            <!-- Form Container -->
            <div class="form-container">
                <form method="POST" enctype="multipart/form-data" id="editBookForm">
                    <div class="form-grid">
                        <!-- Book Title -->
                        <div class="form-group">
                            <label for="title">Book Title *</label>
                            <input type="text" id="title" name="title" required 
                                   value="<?php echo htmlspecialchars($book['title']); ?>"
                                   placeholder="Enter book title">
                        </div>

                        <!-- Author -->
                        <div class="form-group">
                            <label for="author">Author *</label>
                            <input type="text" id="author" name="author" required 
                                   value="<?php echo htmlspecialchars($book['author']); ?>"
                                   placeholder="Enter author name">
                        </div>

                        <!-- Category -->
                        <div class="form-group">
                            <label for="category">Category *</label>
                            <div class="category-input-group">
                                <select id="categorySelect" name="category" class="category-select" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($existing_categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>" 
                                                <?php echo ($book['category'] === $cat) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="__custom__">+ Add New Category</option>
                                </select>
                                <input type="text" id="categoryInput" name="category_custom" class="category-input" 
                                       placeholder="Enter new category">
                            </div>
                        </div>

                        <!-- Published Year -->
                        <div class="form-group">
                            <label for="published_year">Published Year</label>
                            <input type="number" id="published_year" name="published_year" 
                                   min="1000" max="<?php echo date('Y'); ?>"
                                   value="<?php echo htmlspecialchars($book['published_year']); ?>"
                                   placeholder="e.g., <?php echo date('Y'); ?>">
                        </div>

                        <!-- Published Month -->
                        <div class="form-group">
                            <label for="published_month">Published Month</label>
                            <select id="published_month" name="published_month">
                                <option value="">Select month</option>
                                <option value="January" <?php echo ($book['published_month'] === 'January') ? 'selected' : ''; ?>>January</option>
                                <option value="February" <?php echo ($book['published_month'] === 'February') ? 'selected' : ''; ?>>February</option>
                                <option value="March" <?php echo ($book['published_month'] === 'March') ? 'selected' : ''; ?>>March</option>
                                <option value="April" <?php echo ($book['published_month'] === 'April') ? 'selected' : ''; ?>>April</option>
                                <option value="May" <?php echo ($book['published_month'] === 'May') ? 'selected' : ''; ?>>May</option>
                                <option value="June" <?php echo ($book['published_month'] === 'June') ? 'selected' : ''; ?>>June</option>
                                <option value="July" <?php echo ($book['published_month'] === 'July') ? 'selected' : ''; ?>>July</option>
                                <option value="August" <?php echo ($book['published_month'] === 'August') ? 'selected' : ''; ?>>August</option>
                                <option value="September" <?php echo ($book['published_month'] === 'September') ? 'selected' : ''; ?>>September</option>
                                <option value="October" <?php echo ($book['published_month'] === 'October') ? 'selected' : ''; ?>>October</option>
                                <option value="November" <?php echo ($book['published_month'] === 'November') ? 'selected' : ''; ?>>November</option>
                                <option value="December" <?php echo ($book['published_month'] === 'December') ? 'selected' : ''; ?>>December</option>
                            </select>
                        </div>

                        <!-- Status -->
                        <div class="form-group">
                            <label for="status">Overall Status</label>
                            <select id="status" name="status">
                                <option value="Available" <?php echo ($book['status'] === 'Available') ? 'selected' : ''; ?>>Available</option>
                                <option value="Archived" <?php echo ($book['status'] === 'Archived') ? 'selected' : ''; ?>>Archived</option>
                            </select>
                            <small class="form-hint">Individual copy statuses are managed automatically</small>
                        </div>

                        <!-- Book Image Upload -->
                        <div class="form-group full-width">
                            <label>Book Cover Image</label>
                            
                            <?php if ($book['book_image']): ?>
                                <div class="current-image-container">
                                    <img src="../uploads/book-images/<?php echo htmlspecialchars($book['book_image']); ?>" 
                                         alt="Current book cover" class="current-image">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="remove_image" name="remove_image">
                                        <label for="remove_image">Remove current image</label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="file-upload-area" onclick="document.getElementById('book_image').click()">
                                <div class="upload-icon">📷</div>
                                <div class="upload-text">
                                    <?php echo $book['book_image'] ? 'Click to replace book cover' : 'Click to upload book cover'; ?>
                                </div>
                                <div class="upload-hint">or drag and drop an image here</div>
                                <div class="upload-hint">Supported formats: JPG, PNG, GIF, WEBP (Max: 5MB)</div>
                            </div>
                            <input type="file" id="book_image" name="book_image" class="file-input" 
                                   accept="image/*">
                            <div class="preview-container" id="previewContainer" style="display: none;">
                                <img id="previewImage" class="preview-image" alt="Preview">
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <a href="manage_books.php" class="btn-secondary">
                            ← Back to Books
                        </a>
                        <button type="submit" class="btn-primary">
                            💾 Update Book
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const categorySelect = document.getElementById('categorySelect');
            const categoryInput = document.getElementById('categoryInput');
            const fileInput = document.getElementById('book_image');
            const uploadArea = document.querySelector('.file-upload-area');
            const previewContainer = document.getElementById('previewContainer');
            const previewImage = document.getElementById('previewImage');
            const removeImageCheckbox = document.getElementById('remove_image');
            const currentImageContainer = document.querySelector('.current-image-container');

            // Handle category selection
            categorySelect.addEventListener('change', function() {
                if (this.value === '__custom__') {
                    categoryInput.style.display = 'block';
                    categoryInput.required = true;
                    categoryInput.focus();
                    this.required = false;
                } else {
                    categoryInput.style.display = 'none';
                    categoryInput.required = false;
                    this.required = true;
                }
            });

            // Handle remove image checkbox
            if (removeImageCheckbox) {
                removeImageCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        currentImageContainer.style.opacity = '0.5';
                    } else {
                        currentImageContainer.style.opacity = '1';
                    }
                });
            }

            // Handle file upload preview
            fileInput.addEventListener('change', function(e) {
                handleFilePreview(e.target.files[0]);
            });

            // Drag and drop functionality
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    handleFilePreview(files[0]);
                }
            });

            function handleFilePreview(file) {
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        previewContainer.style.display = 'block';
                        
                        // Uncheck remove image if new image is selected
                        if (removeImageCheckbox) {
                            removeImageCheckbox.checked = false;
                            currentImageContainer.style.opacity = '1';
                        }
                    };
                    reader.readAsDataURL(file);
                } else {
                    previewContainer.style.display = 'none';
                }
            }

            // Form validation
            document.getElementById('editBookForm').addEventListener('submit', function(e) {
                const categorySelect = document.getElementById('categorySelect');
                const categoryInput = document.getElementById('categoryInput');
                
                if (categorySelect.value === '__custom__' && !categoryInput.value.trim()) {
                    e.preventDefault();
                    alert('Please enter a category name.');
                    categoryInput.focus();
                    return false;
                }
                
                // Set the actual category value for submission
                if (categorySelect.value === '__custom__') {
                    categorySelect.name = '';
                    categoryInput.name = 'category';
                }
            });
        });
    </script>
</body>
</html>