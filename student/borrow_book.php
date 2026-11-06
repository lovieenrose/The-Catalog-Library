<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title_id'])) {
    if (!verify_csrf_token()) {
        $_SESSION['borrow_error'] = "Security validation failed. Please refresh the page and try again.";
        header("Location: browse_books.php");
        exit();
    }

    $title_id = intval($_POST['title_id']);
    if ($title_id <= 0) {
        $_SESSION['borrow_error'] = "Invalid book selected.";
        header("Location: browse_books.php");
        exit();
    }

    try {
        // Begin transaction with strict locking order to prevent deadlocks and race conditions
        $conn->beginTransaction();

        // 1. Lock Title row first
        $check_stmt = $conn->prepare("
            SELECT title, status, available_copies, total_copies 
            FROM book_titles 
            WHERE title_id = ? 
            FOR UPDATE
        ");
        $check_stmt->execute([$title_id]);
        $book_title = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$book_title) {
            throw new Exception("Book not found.");
        }

        if ($book_title['status'] === 'Archived') {
            throw new Exception("This book is archived and cannot be borrowed.");
        }

        if ((int)$book_title['available_copies'] <= 0) {
            throw new Exception("No copies of this book are currently available for borrowing.");
        }

        // 2. Lock active loan records for this user to check duplicate title and borrowing limit
        $active_loans_stmt = $conn->prepare("
            SELECT id, title_id 
            FROM borrowed_books 
            WHERE user_id = ? AND status IN ('borrowed', 'overdue', 'renewed') 
            FOR UPDATE
        ");
        $active_loans_stmt->execute([$user_id]);
        $active_loans = $active_loans_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($active_loans) >= 2) {
            throw new Exception("You have reached the maximum borrowing limit of 2 books. Please return a book before borrowing a new one.");
        }

        foreach ($active_loans as $loan) {
            if ((int)$loan['title_id'] === $title_id) {
                throw new Exception("You have already borrowed this book title. Please return your current copy before borrowing again.");
            }
        }

        // 3. Find and lock the lowest-numbered available copy for this title
        $copy_stmt = $conn->prepare("
            SELECT copy_id, book_id, copy_number 
            FROM book_copies 
            WHERE title_id = ? AND status = 'Available' 
            ORDER BY copy_number ASC 
            LIMIT 1 
            FOR UPDATE
        ");
        $copy_stmt->execute([$title_id]);
        $available_copy = $copy_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$available_copy) {
            throw new Exception("No available copies found for borrowing.");
        }

        // 4. Calculate borrowing period (7 days)
        $borrow_date = date('Y-m-d');
        $due_date = date('Y-m-d', strtotime('+7 days'));

        // 5. Insert borrowing record
        $borrow_stmt = $conn->prepare("
            INSERT INTO borrowed_books (user_id, copy_id, title_id, book_id, borrow_date, due_date, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'borrowed', NOW())
        ");
        $borrow_stmt->execute([
            $user_id,
            $available_copy['copy_id'],
            $title_id,
            $available_copy['book_id'],
            $borrow_date,
            $due_date
        ]);

        // 6. Update the assigned copy status to Borrowed
        $update_copy_stmt = $conn->prepare("UPDATE book_copies SET status = 'Borrowed' WHERE copy_id = ?");
        $update_copy_stmt->execute([$available_copy['copy_id']]);

        // 7. Update the book title's available copies and status
        $update_title_stmt = $conn->prepare("
            UPDATE book_titles 
            SET available_copies = available_copies - 1,
                status = CASE 
                    WHEN available_copies - 1 <= 0 THEN 'Borrowed' 
                    ELSE status 
                END 
            WHERE title_id = ?
        ");
        $update_title_stmt->execute([$title_id]);

        $conn->commit();

        $_SESSION['borrow_success'] = "Book borrowed successfully! You have been assigned copy #{$available_copy['copy_number']} (ID: {$available_copy['book_id']}). Due date: " . date('F j, Y', strtotime($due_date));
        header("Location: my_borrowed.php");
        exit();

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['borrow_error'] = $e->getMessage();
        header("Location: browse_books.php");
        exit();
    }
} else {
    $_SESSION['borrow_error'] = "Invalid borrowing request.";
    header("Location: browse_books.php");
    exit();
}
