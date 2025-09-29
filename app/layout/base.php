<?php
$title = $GLOBALS['page_title'] ?? 'Sistema';
require __DIR__.'/partial_header.php';
require __DIR__.'/partial_nav.php';
?>
<div class="container mt-3">
  <?= $_SESSION['_alerts'] ?? '' ?>
  <?= $content ?>
</div>
<?php require __DIR__.'/partial_footer.php'; ?>
