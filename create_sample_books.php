<?php
// create_sample_books.php - Run this once to create sample books
require_once 'includes/db.php';

$sample_books = [
    ['title' => 'The Hobbit', 'author' => 'J.R.R. Tolkien', 'category' => 'Fiction', 'status' => 'Available'],
    ['title' => 'To Kill a Mockingbird', 'author' => 'Harper Lee', 'category' => 'Fiction', 'status' => 'Available'],
    ['title' => '1984', 'author' => 'George Orwell', 'category' => 'Fiction', 'status' => 'Borrowed'],
    ['title' => 'The Great Gatsby', 'author' => 'F. Scott Fitzgerald', 'category' => 'Fiction', 'status' => 'Available'],
    ['title' => 'Pride and Prejudice', 'author' => 'Jane Austen', 'category' => 'Romance', 'status' => 'Available'],
    ['title' => 'The Catcher in the Rye', 'author' => 'J.D. Salinger', 'category' => 'Fiction', 'status' => 'Borrowed'],
    ['title' => 'Lord of the Rings', 'author' => 'J.R.R. Tolkien', 'category' => 'Fiction', 'status' => 'Available'],
    ['title' => 'Harry Potter and the Philosopher\'s Stone', 'author' => 'J.K. Rowling', 'category' => 'Fiction', 'status' => 'Available'],
    ['title' => 'The Diary of a Young Girl', 'author' => 'Anne Frank', 'category' => 'Non-Fiction', 'status' => 'Available'],
    ['title' => 'A Brief History of Time', 'author' => 'Stephen Hawking', 'category' => 'Non-Fiction', 'status' => 'Available'],
    ['title' => 'The Art of War', 'author' => 'Sun Tzu', 'category' => 'Non-Fiction', 'status' => 'Available'],
    ['title' => 'Romeo and Juliet', 'author' => 'William Shakespeare', 'category' => 'Romance', 'status' => 'Available'],
    ['title' => 'Wuthering Heights', 'author' => 'Emily Brontë', 'category' => 'Romance', 'status' => 'Borrowed'],
    ['title' => 'The Notebook', 'author' => 'Nicholas Sparks', 'category' => 'Romance', 'status' => 'Available'],
    ['title' => 'Dune', 'author' => 'Frank Herbert', 'category' => 'Fiction', 'status' => 'Available']
];

try {
    foreach ($sample_books as $book) {
        // Check if book already exists
        $check_stmt = $conn->prepare("SELECT book_id FROM books WHERE title = ? AND author = ?");
        $check_stmt->execute([$book['title'], $book['author']]);
        
        if (!$check_stmt->fetch()) {
            // Insert new book
            $stmt = $conn->prepare("INSERT INTO books (title, author, category, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $book['title'],
                $book['author'],
                $book['category'],
                $book['status']
            ]);
            echo "Added book: " . $book['title'] . " by " . $book['author'] . "<br>";
        } else {
            echo "Book already exists: " . $book['title'] . "<br>";
        }
    }
    echo "<br><strong>Sample books created successfully!</strong><br>";
    echo "You now have " . count($sample_books) . " books in your library.<br>";
    
} catch (PDOException $e) {
    echo "Error creating books: " . $e->getMessage();
}
?>