<?php
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$isSubdir = (strpos($scriptName, '/admin') !== false || strpos($scriptName, '/student') !== false);
$assetsPrefix = $isSubdir ? '../assets/' : 'assets/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>The Cat-alog Library</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo $assetsPrefix; ?>css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
    <link rel="icon" href="<?php echo $assetsPrefix; ?>images/favicon.png" type="image/png">
</head>
<body>
    <header class="main-header">
        <div class="header-content">
            <img src="<?php echo $assetsPrefix; ?>images/logo.png" alt="Library Logo" class="header-logo">
            <h1 class="header-title">The Cat-alog Library</h1>
        </div>
    </header>
