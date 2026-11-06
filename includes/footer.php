<?php
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$isSubdir = (strpos($scriptName, '/admin') !== false || strpos($scriptName, '/student') !== false);
$assetsPrefix = $isSubdir ? '../assets/' : 'assets/';
?>
<footer class="main-footer">
    <div class="footer-left">© The Cat-alog Library 2025 | Developed by MingMao</div>
    <div class="footer-right">Version 1.0.0</div>
</footer>
<script src="<?php echo $assetsPrefix; ?>js/script.js"></script>
</body>
</html>
