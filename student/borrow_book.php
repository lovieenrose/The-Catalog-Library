<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title_id'])) {
    $title_id = intval($_POST['title_id']);
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Check if the book title exists and has available copies
        $check_stmt = $conn->prepare("SELECT title, available_copies, total_copies FROM book_titles WHERE title_id = ?");
        $check_stmt->execute([$title_id]);
        $book_title = $check_stmt->fetch();
        
        if (!$book_title) {
            throw new Exception("Book not found.");
        }
        
        if ($book_title['available_copies'] <= 0) {
            throw new Exception("No copies of this book are currently available for borrowing.");
        }
        
        // Check if user has already borrowed this specific title
        $existing_stmt = $conn->prepare("SELECT id FROM borrowed_books WHERE user_id = ? AND title_id = ? AND status IN ('borrowed', 'overdue', 'renewed')");
        $existing_stmt->execute([$user_id, $title_id]);
        if ($existing_stmt->fetch()) {
            throw new Exception("You have already borrowed this book title. Please return it before borrowing again.");
        }
        
        // Check if user has reached borrowing limit (2 books)
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM borrowed_books WHERE user_id = ? AND status IN ('borrowed', 'overdue', 'renewed')");
        $count_stmt->execute([$user_id]);
        $count_result = $count_stmt->fetch();
        
        if ($count_result['count'] >= 2) {
            throw new Exception("You have reached the maximum borrowing limit of 2 books. Please return a book before borrowing a new one.");
        }
        
        // Find an available copy to assign
        $copy_stmt = $conn->prepare("
            SELECT copy_id, book_id, copy_number 
            FROM book_copies 
            WHERE title_id = ? AND status = 'Available' 
            ORDER BY copy_number ASC 
            LIMIT 1
        ");
        $copy_stmt->execute([$title_id]);
        $available_copy = $copy_stmt->fetch();
        
        if (!$available_copy) {
            throw new Exception("No available copies found for borrowing.");
        }
        
        // Calculate due date (7 days from today)
        $borrow_date = date('Y-m-d');
        $due_date = date('Y-m-d', strtotime('+7 days'));
        
        // Insert borrowing record with the assigned copy
        $borrow_stmt = $conn->prepare("
            INSERT INTO borrowed_books (user_id, copy_id, title_id, book_id, borrow_date, due_date, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'borrowed')
        ");
        $borrow_stmt->execute([
            $user_id, 
            $available_copy['copy_id'], 
            $title_id, 
            $available_copy['book_id'], 
            $borrow_date, 
            $due_date
        ]);
        
        // Update the specific copy status to Borrowed
        $update_copy_stmt = $conn->prepare("UPDATE book_copies SET status = 'Borrowed' WHERE copy_id = ?");
        $update_copy_stmt->execute([$available_copy['copy_id']]);
        
        // Update the book title's available copies count
        $update_title_stmt = $conn->prepare("UPDATE book_titles SET available_copies = available_copies - 1 WHERE title_id = ?");
        $update_title_stmt->execute([$title_id]);
        
        // Update the book title status if no more copies available
        $remaining_stmt = $conn->prepare("SELECT available_copies FROM book_titles WHERE title_id = ?");
        $remaining_stmt->execute([$title_id]);
        $remaining = $remaining_stmt->fetch();
        
        if ($remaining['available_copies'] <= 0) {
            $status_stmt = $conn->prepare("UPDATE book_titles SET status = 'Borrowed' WHERE title_id = ?");
            $status_stmt->execute([$title_id]);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Redirect with success message including copy information
        $_SESSION['borrow_success'] = "Book borrowed successfully! You have been assigned copy #{$available_copy['copy_number']} (ID: {$available_copy['book_id']}). Due date: " . date('F j, Y', strtotime($due_date));
        header("Location: my_borrowed.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        
        // Redirect with error message
        $_SESSION['borrow_error'] = $e->getMessage();
        header("Location: browse_books.php");
        exit();
    }
} else {
    // Invalid request - check for old book_id parameter for backwards compatibility
    if (isset($_POST['book_id'])) {
        $_SESSION['borrow_error'] = "Invalid request format. Please try borrowing from the updated book catalog.";
    } else {
        $_SESSION['borrow_error'] = "Invalid borrowing request.";
    }
    header("Location: browse_books.php");
    exit();
}
?>