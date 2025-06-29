<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_id'])) {
    $book_id = intval($_POST['book_id']);
    
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Check if book is available
        $check_stmt = $conn->prepare("SELECT status FROM books WHERE book_id = ?");
        $check_stmt->execute([$book_id]);
        $book = $check_stmt->fetch();
        
        if (!$book || $book['status'] !== 'Available') {
            throw new Exception("Book is not available for borrowing.");
        }
        
        // Check if user has already borrowed this book
        $existing_stmt = $conn->prepare("SELECT id FROM borrowed_books WHERE user_id = ? AND book_id = ? AND status IN ('borrowed', 'overdue')");
        $existing_stmt->execute([$user_id, $book_id]);
        if ($existing_stmt->fetch()) {
            throw new Exception("You have already borrowed this book.");
        }
        
        // Check if user has reached borrowing limit (2 books)
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM borrowed_books WHERE user_id = ? AND status IN ('borrowed', 'overdue')");
        $count_stmt->execute([$user_id]);
        $count_result = $count_stmt->fetch();
        
        if ($count_result['count'] >= 2) {
            throw new Exception("You have reached the maximum borrowing limit of 2 books. Please return a book before borrowing a new one.");
        }
        
        // Calculate due date (7 days from today)
        $borrow_date = date('Y-m-d');
        $due_date = date('Y-m-d', strtotime('+7 days'));
        
        // Insert borrowing record
        $borrow_stmt = $conn->prepare("INSERT INTO borrowed_books (user_id, book_id, borrow_date, due_date, status) VALUES (?, ?, ?, ?, 'borrowed')");
        $borrow_stmt->execute([$user_id, $book_id, $borrow_date, $due_date]);
        
        // Update book status to Borrowed
        $update_stmt = $conn->prepare("UPDATE books SET status = 'Borrowed' WHERE book_id = ?");
        $update_stmt->execute([$book_id]);
        
        // Commit transaction
        $conn->commit();
        
        // Redirect with success message
        $_SESSION['borrow_success'] = "Book borrowed successfully! Due date: " . date('F j, Y', strtotime($due_date));
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
    // Invalid request
    header("Location: browse_books.php");
    exit();
}
?>