<?php
$nav_class = portal_config('nav_class', '');
?>
<header class="topbar <?php echo $nav_class; ?>" id="navbar">
    <div class="d-flex align-items-center gap-3 px-3 py-2" style="max-width:1500px;margin:0 auto">
        <span class="brand">DS3</span>
        <span class="sub d-none d-md-inline">Wholesale API distribution</span>
        <span class="ms-auto sub" id="login_user_name"></span>
        <a href="#" class="btn btn-sm btn-outline-light logout-btn">Sign out</a>
    </div>
</header>
