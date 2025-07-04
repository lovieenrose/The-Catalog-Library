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

// Get book ID from URL
$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($book_id <= 0) {
    header('Location: manage_books.php?error=Invalid book ID');
    exit();
}

// Fetch book data
try {
    $stmt = $conn->prepare("SELECT * FROM books WHERE book_id = ?");
    $stmt->execute([$book_id]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$book) {
        header('Location: manage_books.php?error=Book not found');
        exit();
    }
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
        $upload_dir = '../uploads/book_images/';
        
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
        $upload_dir = '../uploads/book_images/';
        if (file_exists($upload_dir . $book['book_image'])) {
            unlink($upload_dir . $book['book_image']);
        }
    }
    
    // If no errors, update database
    if (empty($errors)) {
        try {
            $sql = "UPDATE books SET title = ?, author = ?, category = ?, published_year = ?, book_image = ?, status = ? WHERE book_id = ?";
            
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                $title,
                $author,
                $category,
                $published_year ?: null,
                $book_image,
                $status,
                $book_id
            ]);
            
            if ($result) {
                $message = "Book updated successfully!";
                $message_type = "success";
                
                // Refresh book data
                $stmt = $conn->prepare("SELECT * FROM books WHERE book_id = ?");
                $stmt->execute([$book_id]);
                $book = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $errors[] = "Failed to update book in database.";
            }
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $message_type = "error";
    }
}

// Get existing categories for dropdown
try {
    $categoryStmt = $conn->prepare("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category != '' ORDER BY category");
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
    <link rel="stylesheet" href="../assets/css/admin_edit_book.css">

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

                        <!-- Status -->
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="Available" <?php echo ($book['status'] === 'Available') ? 'selected' : ''; ?>>Available</option>
                                <option value="Borrowed" <?php echo ($book['status'] === 'Borrowed') ? 'selected' : ''; ?>>Borrowed</option>
                            </select>
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