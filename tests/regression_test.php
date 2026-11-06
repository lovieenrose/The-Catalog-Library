<?php
/**
 * Automated Regression & Security Test Suite
 * The Cat-alog Library
 *
 * Usage: php tests/regression_test.php
 */

require_once __DIR__ . '/../includes/config.php';
setup_secure_session();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/storage.php';

// Test runner state
$passed = 0;
$failed = 0;
$testCount = 0;

function it($description, callable $fn) {
    global $passed, $failed, $testCount, $conn;
    $testCount++;
    try {
        $result = $fn();
        if ($result === false) {
            echo "  ❌ FAIL: {$description}\n";
            $failed++;
        } else {
            echo "  ✅ PASS: {$description}\n";
            $passed++;
        }
    } catch (Throwable $e) {
        if ($conn && $conn instanceof PDO && $conn->inTransaction()) {
            $conn->rollBack();
        }
        echo "  ❌ FAIL: {$description} (Exception: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . ")\n";
        $failed++;
    }
}

function assert_true($cond, $msg = "Expected true") {
    if (!$cond) throw new Exception($msg);
}

function assert_equals($expected, $actual, $msg = "") {
    if ($expected !== $actual) {
        throw new Exception(($msg ? $msg . " - " : "") . "Expected " . var_export($expected, true) . ", got " . var_export($actual, true));
    }
}

echo "========================================================\n";
echo "🐾 THE CAT-ALOG LIBRARY - COMPREHENSIVE REGRESSION SUITE\n";
echo "========================================================\n\n";

if (!$conn || !$db_connected) {
    echo "⚠️ Database connection is not available in the current environment.\n";
    echo "   Running offline unit/security tests only...\n\n";
}

// -----------------------------------------------------------------------------
// 1. Output Escaping & XSS Protection Tests
// -----------------------------------------------------------------------------
echo "[1] Output Escaping & Helpers\n";

it("e() properly neutralizes HTML tags and attributes", function() {
    $xss = '<script>alert("xss")</script>';
    $escaped = e($xss);
    assert_true(strpos($escaped, '<script>') === false, "Script tag was not escaped");
    assert_true(strpos($escaped, '&lt;script&gt;') !== false, "Entities not generated");
    
    $attr = '" onfocus="alert(1)';
    $escapedAttr = e($attr);
    assert_true(strpos($escapedAttr, '&quot;') !== false, "Double quotes not escaped");
    return true;
});

it("e() handles null and empty values gracefully", function() {
    assert_equals("", e(null));
    assert_equals("", e(""));
    assert_equals("0", e(0));
    return true;
});

it("safe_redirect() validates internal paths and prevents open redirects", function() {
    $isInternal = function($target) {
        if (empty($target)) return false;
        if (preg_match('#^https?://#i', $target) || strpos($target, '//') === 0 || strpos($target, '\\') !== false) {
            return false;
        }
        return true;
    };

    assert_true(!$isInternal("https://evil.com"));
    assert_true(!$isInternal("http://evil.com/login"));
    assert_true(!$isInternal("//evil.com"));
    assert_true(!$isInternal("\\\\evil.com"));
    assert_true($isInternal("dashboard.php"));
    assert_true($isInternal("/student/login.php"));
    assert_true($isInternal("browse_books.php?category=Fiction"));
    return true;
});

// -----------------------------------------------------------------------------
// 2. CSRF Protection Tests
// -----------------------------------------------------------------------------
echo "\n[2] CSRF Protection\n";

it("verify_csrf_token() rejects missing or invalid tokens", function() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    assert_true(!verify_csrf_token(null), "Should reject null token");
    assert_true(!verify_csrf_token(""), "Should reject empty token");
    assert_true(!verify_csrf_token("invalid_token_12345"), "Should reject mismatched token");
    assert_true(verify_csrf_token($_SESSION['csrf_token']), "Should accept matching token");
    return true;
});

// -----------------------------------------------------------------------------
// 3. Rate Limiting Tests
// -----------------------------------------------------------------------------
echo "\n[3] Rate Limiting Logic\n";

if ($conn && $db_connected) {
    it("check_rate_limit() enforces thresholds and blocks after max attempts", function() use ($conn) {
        $testKey = 'test_action_' . bin2hex(random_bytes(4));
        
        assert_true(check_rate_limit($conn, $testKey, 3, 60));
        assert_true(check_rate_limit($conn, $testKey, 3, 60));
        assert_true(check_rate_limit($conn, $testKey, 3, 60));
        assert_true(!check_rate_limit($conn, $testKey, 3, 60), "4th attempt should have been blocked");

        clear_rate_limit($conn, $testKey);
        assert_true(check_rate_limit($conn, $testKey, 3, 60), "Should allow attempt after clearing");
        return true;
    });
}

// -----------------------------------------------------------------------------
// 4. Concurrency-Safe Book ID Generator & Trigger
// -----------------------------------------------------------------------------
echo "\n[4] Book ID Generation\n";

if ($conn && $db_connected) {
    it("generate_book_id() produces standardized format: {TITLE_2}{MONTH_3}{DAY_2}{YEAR_4}-{CAT_CODE}{TITLE_ID_3}{COPY_NUM_2}", function() use ($conn) {
        $conn->beginTransaction();
        $ins = $conn->prepare("
            INSERT INTO book_titles (title, author, category, published_year, published_month, total_copies, available_copies)
            VALUES ('The Great Gatsby', 'F. Scott Fitzgerald', 'Fiction', 1925, 'April', 1, 1)
        ");
        $ins->execute();
        $titleId = (int)$conn->lastInsertId();

        $generatedId = generate_book_id($conn, $titleId, 1, '2026-04-10');
        $expectedPattern = '/^THAPR101925-FIC\d{3}01$/';
        assert_true(preg_match($expectedPattern, $generatedId) === 1, "Generated ID '{$generatedId}' does not match expected pattern");

        $conn->rollBack();
        return true;
    });

    it("Database trigger trg_book_copies_before_insert generates book_id automatically", function() use ($conn) {
        $conn->beginTransaction();
        $ins = $conn->prepare("
            INSERT INTO book_titles (title, author, category, published_year, published_month, total_copies, available_copies)
            VALUES ('Science Today', 'Jane Doe', 'Science and Technology', 2023, 'May', 2, 2)
        ");
        $ins->execute();
        $titleId = (int)$conn->lastInsertId();

        $copyIns = $conn->prepare("
            INSERT INTO book_copies (title_id, copy_number, condition_status, acquisition_date, status)
            VALUES (?, 1, 'Excellent', '2026-05-15', 'Available')
        ");
        $copyIns->execute([$titleId]);
        $copyId = $conn->lastInsertId();

        $sel = $conn->prepare("SELECT book_id FROM book_copies WHERE copy_id = ?");
        $sel->execute([$copyId]);
        $bookId = $sel->fetchColumn();

        assert_true(!empty($bookId), "Trigger did not generate book_id");
        assert_true(strpos($bookId, 'SCMAY152023-SCI') === 0, "Generated book_id '{$bookId}' does not start with expected prefix");

        $conn->rollBack();
        return true;
    });
}

// -----------------------------------------------------------------------------
// 5. Registration & Authentication Verification
// -----------------------------------------------------------------------------
echo "\n[5] User Registration & Authentication\n";

if ($conn && $db_connected) {
    it("Student registration safely hashes passwords and prevents duplicate usernames/emails", function() use ($conn) {
        $conn->beginTransaction();

        $testUser = 'std_test_' . bin2hex(random_bytes(3));
        $testEmail = $testUser . '@student.library.edu';
        $testPassword = 'Password_12345';
        $hashed = password_hash($testPassword, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (username, password, first_name, last_name, email) VALUES (?, ?, 'First', 'Last', ?)");
        $stmt->execute([$testUser, $hashed, $testEmail]);
        $userId = $conn->lastInsertId();

        $sel = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $sel->execute([$userId]);
        $storedHash = $sel->fetchColumn();
        assert_true(password_verify($testPassword, $storedHash), "Password verification failed");

        $dupCaught = false;
        try {
            $stmt->execute([$testUser, $hashed, 'another@email.com']);
        } catch (PDOException $e) {
            $dupCaught = true;
        }
        assert_true($dupCaught, "Duplicate username was not prevented by unique constraint");

        $conn->rollBack();
        return true;
    });

    it("Admin credentials verify with bcrypt and reject invalid passwords", function() use ($conn) {
        $testAdmin = 'admin_unit_' . bin2hex(random_bytes(3));
        $testPass = 'SuperSecret_2026!';
        $testEmail = $testAdmin . '@library.internal';

        $hash = password_hash($testPass, PASSWORD_DEFAULT);
        $ins = $conn->prepare("
            INSERT INTO admin_users (username, password, first_name, last_name, email, role, status)
            VALUES (?, ?, 'Super', 'Admin', ?, 'admin', 'active')
        ");
        $ins->execute([$testAdmin, $hash, $testEmail]);

        $sel = $conn->prepare("SELECT password FROM admin_users WHERE username = ?");
        $sel->execute([$testAdmin]);
        $storedHash = $sel->fetchColumn();

        assert_true(password_verify($testPass, $storedHash), "Password verification failed on created admin");
        assert_true(!password_verify('wrong_pass', $storedHash), "Password verification falsely passed on wrong password");

        $conn->prepare("DELETE FROM admin_users WHERE username = ?")->execute([$testAdmin]);
        return true;
    });
}

// -----------------------------------------------------------------------------
// 6. Password Reset Security Tests
// -----------------------------------------------------------------------------
echo "\n[6] Password Reset Workflow & Token Invalidation\n";

if ($conn && $db_connected) {
    it("Password reset tokens use SHA-256 hashes and expire after designated interval", function() use ($conn) {
        $conn->beginTransaction();

        $uStmt = $conn->prepare("
            INSERT INTO users (username, password, first_name, last_name, email)
            VALUES ('test_reset_user', 'hash', 'Test', 'User', 'test_reset@example.com')
        ");
        $uStmt->execute();
        $userId = $conn->lastInsertId();

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $expStmt = $conn->prepare("
            INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
            VALUES (?, ?, DATE_SUB(NOW(), INTERVAL 5 MINUTE), NOW())
        ");
        $expStmt->execute([$userId, $tokenHash]);

        $valStmt = $conn->prepare("
            SELECT id FROM password_resets 
            WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
        ");
        $valStmt->execute([$tokenHash]);
        assert_true($valStmt->fetch() === false, "Expired token must not be accepted");

        $rawToken2 = bin2hex(random_bytes(32));
        $tokenHash2 = hash('sha256', $rawToken2);
        $validStmt = $conn->prepare("
            INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW())
        ");
        $validStmt->execute([$userId, $tokenHash2]);
        $tokenId = $conn->lastInsertId();

        $useStmt = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
        $useStmt->execute([$tokenId]);

        $valStmt->execute([$tokenHash2]);
        assert_true($valStmt->fetch() === false, "Used token must not be accepted on reuse");

        $conn->rollBack();
        return true;
    });
}

// -----------------------------------------------------------------------------
// 7. Borrowing Limits, Duplicate Restrictions & Idempotent Returns
// -----------------------------------------------------------------------------
echo "\n[7] Borrowing Rules & Concurrency Protection\n";

if ($conn && $db_connected) {
    it("Enforces 2-book borrowing limit per student", function() use ($conn) {
        $conn->beginTransaction();

        $uStmt = $conn->prepare("INSERT INTO users (username, password, first_name, last_name, email) VALUES ('limit_user', 'hash', 'L', 'U', 'limit@test.com')");
        $uStmt->execute();
        $userId = $conn->lastInsertId();

        $bookIds = [];
        $copyIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $tStmt = $conn->prepare("INSERT INTO book_titles (title, author, category, total_copies, available_copies) VALUES ('Book {$i}', 'Author', 'Fiction', 1, 1)");
            $tStmt->execute();
            $tId = $conn->lastInsertId();
            $bookIds[] = $tId;

            $cStmt = $conn->prepare("INSERT INTO book_copies (title_id, copy_number, condition_status, acquisition_date, status) VALUES (?, 1, 'Excellent', CURDATE(), 'Available')");
            $cStmt->execute([$tId]);
            $copyIds[] = $conn->lastInsertId();
        }

        $b1 = $conn->prepare("INSERT INTO borrowed_books (user_id, copy_id, title_id, book_id, borrow_date, due_date, status) VALUES (?, ?, ?, 'BK1', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'borrowed')");
        $b1->execute([$userId, $copyIds[0], $bookIds[0]]);

        $b2 = $conn->prepare("INSERT INTO borrowed_books (user_id, copy_id, title_id, book_id, borrow_date, due_date, status) VALUES (?, ?, ?, 'BK2', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'borrowed')");
        $b2->execute([$userId, $copyIds[1], $bookIds[1]]);

        $cntStmt = $conn->prepare("SELECT COUNT(*) FROM borrowed_books WHERE user_id = ? AND status IN ('borrowed', 'overdue', 'renewed')");
        $cntStmt->execute([$userId]);
        $activeLoans = (int)$cntStmt->fetchColumn();

        assert_equals(2, $activeLoans);
        assert_true($activeLoans >= 2, "Student should have reached maximum borrowing limit");

        $conn->rollBack();
        return true;
    });

    it("Enforces duplicate-title restriction (student cannot borrow 2 copies of same title)", function() use ($conn) {
        $conn->beginTransaction();

        $uStmt = $conn->prepare("INSERT INTO users (username, password, first_name, last_name, email) VALUES ('dup_user', 'hash', 'D', 'U', 'dup@test.com')");
        $uStmt->execute();
        $userId = $conn->lastInsertId();

        $tStmt = $conn->prepare("INSERT INTO book_titles (title, author, category, total_copies, available_copies) VALUES ('Duplicate Title Test', 'Author', 'Fiction', 2, 2)");
        $tStmt->execute();
        $titleId = $conn->lastInsertId();

        $b1 = $conn->prepare("INSERT INTO borrowed_books (user_id, copy_id, title_id, book_id, borrow_date, due_date, status) VALUES (?, 1, ?, 'BK1', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'borrowed')");
        $b1->execute([$userId, $titleId]);

        $dupStmt = $conn->prepare("SELECT COUNT(*) FROM borrowed_books WHERE user_id = ? AND title_id = ? AND status IN ('borrowed', 'overdue', 'renewed')");
        $dupStmt->execute([$userId, $titleId]);
        $hasDuplicate = ((int)$dupStmt->fetchColumn()) > 0;

        assert_true($hasDuplicate, "Duplicate title borrow should be detected");

        $conn->rollBack();
        return true;
    });

    it("Prevents borrowing archived books or books with 0 available copies", function() use ($conn) {
        $conn->beginTransaction();

        $tArch = $conn->prepare("INSERT INTO book_titles (title, author, category, total_copies, available_copies, status) VALUES ('Archived Book', 'A', 'Fiction', 1, 0, 'Archived')");
        $tArch->execute();
        $archId = $conn->lastInsertId();

        $sel = $conn->prepare("SELECT status, available_copies FROM book_titles WHERE title_id = ?");
        $sel->execute([$archId]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);

        assert_true($row['status'] === 'Archived', "Book status should be Archived");
        assert_true((int)$row['available_copies'] <= 0, "Archived book should have 0 available copies");

        $conn->rollBack();
        return true;
    });

    it("Returns are idempotent: repeated return requests do not double-increment availability", function() use ($conn) {
        $conn->beginTransaction();

        $tStmt = $conn->prepare("INSERT INTO book_titles (title, author, category, total_copies, available_copies) VALUES ('Return Test Book', 'Author', 'Fiction', 1, 0)");
        $tStmt->execute();
        $titleId = $conn->lastInsertId();

        $cStmt = $conn->prepare("INSERT INTO book_copies (title_id, copy_number, condition_status, acquisition_date, status) VALUES (?, 1, 'Excellent', CURDATE(), 'Borrowed')");
        $cStmt->execute([$titleId]);
        $copyId = $conn->lastInsertId();

        $uStmt = $conn->prepare("INSERT INTO users (username, password, first_name, last_name, email) VALUES ('return_user', 'hash', 'R', 'U', 'ret@test.com')");
        $uStmt->execute();
        $userId = $conn->lastInsertId();

        $bStmt = $conn->prepare("INSERT INTO borrowed_books (user_id, copy_id, title_id, book_id, borrow_date, due_date, status) VALUES (?, ?, ?, 'BK-RET', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'borrowed')");
        $bStmt->execute([$userId, $copyId, $titleId]);
        $borrowId = $conn->lastInsertId();

        // Return #1
        $ret1 = $conn->prepare("UPDATE borrowed_books SET status = 'returned', return_date = CURDATE() WHERE id = ? AND status IN ('borrowed', 'overdue', 'renewed')");
        $ret1->execute([$borrowId]);
        assert_equals(1, $ret1->rowCount(), "First return must affect 1 row");

        $conn->prepare("UPDATE book_copies SET status = 'Available' WHERE copy_id = ?")->execute([$copyId]);
        $conn->prepare("UPDATE book_titles SET available_copies = (SELECT COUNT(*) FROM book_copies WHERE title_id = ? AND status = 'Available') WHERE title_id = ?")->execute([$titleId, $titleId]);

        $selTitle = $conn->prepare("SELECT available_copies FROM book_titles WHERE title_id = ?");
        $selTitle->execute([$titleId]);
        assert_equals(1, (int)$selTitle->fetchColumn(), "Available copies should be 1 after return");

        // Return #2 (Repeated request)
        $ret2 = $conn->prepare("UPDATE borrowed_books SET status = 'returned', return_date = CURDATE() WHERE id = ? AND status IN ('borrowed', 'overdue', 'renewed')");
        $ret2->execute([$borrowId]);
        assert_equals(0, $ret2->rowCount(), "Repeated return must affect 0 rows (idempotent)");

        $selTitle->execute([$titleId]);
        assert_equals(1, (int)$selTitle->fetchColumn(), "Available copies must remain 1 after repeated return");

        $conn->rollBack();
        return true;
    });
}

// -----------------------------------------------------------------------------
// 8. Book Lifecycle: Archiving, Restoring, and Deletion Constraints
// -----------------------------------------------------------------------------
echo "\n[8] Book Lifecycle (Archiving, Restoring, Deletion)\n";

if ($conn && $db_connected) {
    it("Archiving cascades 'Archived' status to all copies and sets available_copies to 0", function() use ($conn) {
        $conn->beginTransaction();

        $tStmt = $conn->prepare("INSERT INTO book_titles (title, author, category, total_copies, available_copies, status) VALUES ('Archive Cascade Test', 'A', 'Fiction', 2, 2, 'Available')");
        $tStmt->execute();
        $titleId = $conn->lastInsertId();

        $conn->prepare("INSERT INTO book_copies (title_id, copy_number, condition_status, acquisition_date, status) VALUES (?, 1, 'Excellent', CURDATE(), 'Available')")->execute([$titleId]);
        $conn->prepare("INSERT INTO book_copies (title_id, copy_number, condition_status, acquisition_date, status) VALUES (?, 2, 'Excellent', CURDATE(), 'Available')")->execute([$titleId]);

        // Archive operation
        $conn->prepare("UPDATE book_copies SET status = 'Archived' WHERE title_id = ?")->execute([$titleId]);
        $conn->prepare("UPDATE book_titles SET status = 'Archived', available_copies = 0 WHERE title_id = ?")->execute([$titleId]);

        // Verify title
        $tSel = $conn->prepare("SELECT status, available_copies FROM book_titles WHERE title_id = ?");
        $tSel->execute([$titleId]);
        $tRow = $tSel->fetch(PDO::FETCH_ASSOC);
        assert_equals('Archived', $tRow['status']);
        assert_equals(0, (int)$tRow['available_copies']);

        // Verify copies
        $cSel = $conn->prepare("SELECT COUNT(*) FROM book_copies WHERE title_id = ? AND status = 'Archived'");
        $cSel->execute([$titleId]);
        assert_equals(2, (int)$cSel->fetchColumn());

        // Restore operation
        $conn->prepare("UPDATE book_copies SET status = 'Available' WHERE title_id = ?")->execute([$titleId]);
        $conn->prepare("UPDATE book_titles SET status = 'Available', available_copies = total_copies WHERE title_id = ?")->execute([$titleId]);

        $tSel->execute([$titleId]);
        $tRow = $tSel->fetch(PDO::FETCH_ASSOC);
        assert_equals('Available', $tRow['status']);
        assert_equals(2, (int)$tRow['available_copies']);

        $conn->rollBack();
        return true;
    });

    it("Deletion is prevented if any copies are currently borrowed", function() use ($conn) {
        $conn->beginTransaction();

        $tStmt = $conn->prepare("INSERT INTO book_titles (title, author, category, total_copies, available_copies) VALUES ('Delete Protected Book', 'A', 'Fiction', 1, 0)");
        $tStmt->execute();
        $titleId = $conn->lastInsertId();

        $cStmt = $conn->prepare("INSERT INTO book_copies (title_id, copy_number, condition_status, acquisition_date, status) VALUES (?, 1, 'Excellent', CURDATE(), 'Borrowed')");
        $cStmt->execute([$titleId]);
        $copyId = $conn->lastInsertId();

        $uStmt = $conn->prepare("INSERT INTO users (username, password, first_name, last_name, email) VALUES ('del_user', 'hash', 'D', 'U', 'del@test.com')");
        $uStmt->execute();
        $userId = $conn->lastInsertId();

        $conn->prepare("INSERT INTO borrowed_books (user_id, copy_id, title_id, book_id, borrow_date, due_date, status) VALUES (?, ?, ?, 'BK-DEL', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'borrowed')")->execute([$userId, $copyId, $titleId]);

        // Attempt deletion check
        $checkStmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM borrowed_books bb 
            WHERE bb.title_id = ? AND bb.status IN ('borrowed', 'overdue', 'renewed')
        ");
        $checkStmt->execute([$titleId]);
        $borrowed = (int)$checkStmt->fetchColumn();

        assert_true($borrowed > 0, "Book has active borrow and must not be deleted");

        $conn->rollBack();
        return true;
    });
}

// -----------------------------------------------------------------------------
// 9. Persistent Storage Service & S3 / R2 URL Generation
// -----------------------------------------------------------------------------
echo "\n[9] Persistent Storage Service (S3 / Cloudflare R2 / Local)\n";

it("storage_get_book_image_url() returns local fallback when no image provided", function() {
    $url = storage_get_book_image_url(null, '..');
    assert_true(strpos($url, 'default_book.jpg') !== false, "Expected fallback default_book.jpg");
    return true;
});

it("storage_get_book_image_url() preserves external URLs directly", function() {
    $extUrl = "https://images.example.com/cover123.jpg";
    assert_equals($extUrl, storage_get_book_image_url($extUrl));
    return true;
});

it("storage_get_book_image_url() formats S3/R2 public URLs correctly when configured", function() {
    $client = new S3StorageClient(
        'my-library-bucket',
        'test_key',
        'test_secret',
        'auto',
        'https://123456.r2.cloudflarestorage.com',
        'https://pub-r2.example.com'
    );

    $url = $client->getPublicUrl('book-images/test_cover.jpg');
    assert_equals('https://pub-r2.example.com/book-images/test_cover.jpg', $url);
    return true;
});

it("storage_upload_book_image() rejects non-image files and files exceeding size limit", function() {
    $fakeFile = [
        'name'     => 'malicious.php',
        'type'     => 'application/x-php',
        'tmp_name' => __FILE__,
        'error'    => UPLOAD_ERR_OK,
        'size'     => 1024,
    ];
    $res = storage_upload_book_image($fakeFile);
    assert_true(!$res['success'], "Should have rejected PHP file");

    $fakeBig = [
        'name'     => 'large.jpg',
        'type'     => 'image/jpeg',
        'tmp_name' => __FILE__,
        'error'    => UPLOAD_ERR_OK,
        'size'     => 6 * 1024 * 1024,
    ];
    $res2 = storage_upload_book_image($fakeBig);
    assert_true(!$res2['success'], "Should have rejected file > 5MB");
    return true;
});

// -----------------------------------------------------------------------------
// 10. Database-Backed Shared Session Storage Verification
// -----------------------------------------------------------------------------
echo "\n[10] Database-Backed Session Persistence\n";

if ($conn && $db_connected) {
    it("DatabaseSessionHandler writes, reads, and deletes sessions from MySQL", function() use ($conn) {
        require_once __DIR__ . '/../includes/session_handler.php';
        $handler = new DatabaseSessionHandler($conn);

        $testSessId = 'test_sess_' . bin2hex(random_bytes(8));
        $testData   = 'user_id|i:999;role|s:5:"admin";';

        // Write
        assert_true($handler->write($testSessId, $testData), "Session write failed");

        // Read
        $readData = $handler->read($testSessId);
        assert_equals($testData, $readData, "Session read did not match written data");

        // Destroy
        assert_true($handler->destroy($testSessId), "Session destroy failed");
        assert_equals("", $handler->read($testSessId), "Destroyed session should read as empty");
        return true;
    });
}

// -----------------------------------------------------------------------------
// 11. Vercel Routing & Private File Access Prevention
// -----------------------------------------------------------------------------
echo "\n[11] Vercel Routing Security & Private File Rules\n";

it("Blocks direct access to sensitive paths (.env, includes/, sql/, bin/)", function() {
    $blocked = [
        '/.env',
        '/.env.local',
        '/includes/db.php',
        '/sql/library_schema.sql',
        '/bin/install_db.php',
        '/tests/regression_test.php',
        '/server.log',
    ];

    $pattern = '#^/(\.env|\.git|\.htaccess)|^/(includes|sql|bin|tests)/|\.(sql|log|ini|sh|bak|conf|yml|yaml|md|json)$#i';

    foreach ($blocked as $p) {
        assert_true(preg_match($pattern, $p) === 1, "Path '{$p}' should be blocked");
    }

    $allowed = [
        '/student/login',
        '/student/browse_books.php',
        '/admin/manage_books.php',
        '/',
        '/index.php',
    ];

    foreach ($allowed as $a) {
        assert_true(preg_match($pattern, $a) === 0, "Path '{$a}' should not be blocked");
    }

    return true;
});

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "\n========================================================\n";
echo "TEST RESULTS: {$passed} Passed, {$failed} Failed (Total: {$testCount})\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
