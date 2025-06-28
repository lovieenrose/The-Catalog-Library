<?php require_once '../includes/session.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard - The Cat-alog Library</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
</head>
<body>

<!-- Banner Section with Navigation and Welcome -->
<section class="banner-section">
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
                <li><a href="my_borrowed.php">My Borrowed Books</a></li>
            </ul>
        </nav>
        <a href="logout.php" class="logout-btn">Log Out</a>
    </header>

    <!-- Welcome Content -->
    <div class="welcome-content">
        <h2 class="sniglet-extrabold">Welcome to the Dashboard</h2>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
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
                <img src="../assets/images/fiction1.jpg" alt="Fiction Book">
                <img src="../assets/images/fiction2.jpg" alt="Fiction Book">
                <img src="../assets/images/fiction3.jpg" alt="Fiction Book">
            </div>
            <h4>Non-Fiction Books</h4>
            <div class="book-row">
                <img src="../assets/images/nonfic1.jpg" alt="Non-Fiction Book">
                <img src="../assets/images/nonfic2.jpg" alt="Non-Fiction Book">
                <img src="../assets/images/nonfic3.jpg" alt="Non-Fiction Book">
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
                    <option value="Fiction">Fiction</option>
                    <option value="Non-Fiction">Non-Fiction</option>
                </select>
            </div>
            <div class="form-row">
                <input type="text" name="book_id" placeholder="Book ID (Optional)">
            </div>
            <div class="form-row">
                <select name="status">
                    <option value="">Book Status</option>
                    <option value="Available">Available</option>
                    <option value="Unavailable">Unavailable</option>
                </select>
            </div>
            <button type="submit" class="btn">Search</button>
        </form>
    </section>

    <!-- Student Profile and Borrowed Books -->
    <section class="dashboard-box">
        <h3>Student Profile</h3>
        <div class="student-profile">
            <img src="../assets/images/student-icon.png" alt="Student Icon" class="student-avatar">
            <p class="sniglet-regular">Maria Theresa</p>
        </div>

        <h3>My Borrowed Books</h3>
        <table class="borrowed-table">
            <thead>
                <tr>
                    <th>Book Title</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>To Kill A Mockingbird</strong></td>
                    <td>July 5, 2025</td>
                    <td class="status-green">On Time</td>
                </tr>
                <tr>
                    <td><strong>The Diary of a Young Girl</strong></td>
                    <td>June 15, 2025</td>
                    <td class="status-red">Overdue</td>
                </tr>
            </tbody>
        </table>
        <a href="my_borrowed.php" class="btn">View All</a>
    </section>
</main>

<?php include '../includes/footer.php'; ?>

</body>
</html>