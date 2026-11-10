<?php
/**
 * Clinic-only dynamic top-navbar.
 * Built from `v2/portals/clinic/sidebar_menu.php` so top navigation stays in
 * sync with module metadata (`tab`, `page`, `module_key`) like sidebar.php.
 *
 * Role-module visibility remains API-driven by `clinic/permissions`
 * (role_module_access-backed) in clinic.js, which unhides `#{tab}-menu`.
 */
$active_tab      ??= '';
$sidebar_menu    = $sidebar_menu ?? [];
$nav_menu        = portal_config('nav_menu', []);
$top_nav_aliases = portal_config('top_nav_tab_aliases', ['messanger' => 'messenger']);
$top_nav_badges  = portal_config('top_nav_badges', []);

if ((string) portal_config('portal_name', '') !== 'clinic') {
    include v2_view('shared/partials/navbar');
    return;
}

/**
 * Normalize tab keys so legacy misspellings still match menu IDs/permissions.
 */
$normalize_tab = static function (string $tab) use ($top_nav_aliases): string {
    return $top_nav_aliases[$tab] ?? $tab;
};

/**
 * Build URL from a nav item.
 */
$nav_url = static function (array $item): string {
    $moduleKey = (string) ($item['module_key'] ?? '');

    if (isset($item['page'])) {
        return module_url($moduleKey, portal_url((string) $item['page']));
    }

    $href = (string) ($item['href'] ?? '');
    if ($href === '') {
        return '#';
    }

    if (strpos($href, '/') === 0 || preg_match('#^https?://#i', $href) === 1) {
        return $href;
    }

    return portal_url($href);
};

$items_by_tab = [];
foreach ($sidebar_menu as $group) {
    $groupIcon = (string) ($group['icon'] ?? 'fa fa-circle');
    foreach ((array) ($group['items'] ?? []) as $item) {
        if (isset($item['topnav']) && $item['topnav'] === false) {
            continue;
        }

        $tab = (string) ($item['tab'] ?? '');
        if ($tab === '') {
            continue;
        }

        $normalizedTab = $normalize_tab($tab);
        $items_by_tab[$normalizedTab] = [
            'tab' => $normalizedTab,
            'label' => (string) ($item['nav_label'] ?? $item['label'] ?? $group['title'] ?? $normalizedTab),
            'page' => (string) ($item['page'] ?? ''),
            'module_key' => (string) ($item['module_key'] ?? ''),
            'icon' => (string) ($item['icon'] ?? $groupIcon),
            'badge' => (string) ($item['badge'] ?? ''),
            'order' => (int) ($item['topnav_order'] ?? 0),
        ];
    }
}

uasort($items_by_tab, static function (array $a, array $b): int {
    if ($a['order'] === $b['order']) {
        return strcmp($a['tab'], $b['tab']);
    }
    return $a['order'] <=> $b['order'];
});

$dashboardFallback = portal_config('dashboard_url', portal_url('dashboard'));
$brandUrl = module_url((string) portal_config('top_nav_brand_module_key', ''), $dashboardFallback);
?>
<nav class="navbar navbar-default navbar-fixed-top">
    <div class="top-bar clearfix">
        <div class="container-bar">
            <div class="brand"></div>
            <div class="navbar-right hide">
                <form class="navbar-form navbar-left search-form">
                    <input type="text" value="" class="form-control" placeholder="Type keyword here...">
                    <button type="button" class="btn btn-default"><i class="fa fa-search"></i></button>
                </form>
            </div>
        </div>
    </div>

    <div id="navbar-menu" class="bottom-bar clearfix">
        <div class="navbar-header">
            <div class="brand visible-xs">
                <a href="<?php echo htmlspecialchars($brandUrl, ENT_QUOTES); ?>" class="homepage-link">
                    <span style="font-size: 28px;color: #000;">ReUnite Rx</span>
                </a>
            </div>
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#main-nav">
                <i class="ti-menu"></i>
            </button>
        </div>

        <div class="navbar-collapse collapse" id="main-nav">
            <ul class="nav navbar-nav">
                <?php foreach ($items_by_tab as $tab => $item): ?>
                    <?php
                    if ($tab === 'dashboard') {
                        continue;
                    }
                    $isActive = $normalize_tab((string) $active_tab) === $tab;
                    $badgeId = (string) ($top_nav_badges[$tab] ?? $item['badge']);
                    ?>
                    <li id="<?php echo htmlspecialchars($tab, ENT_QUOTES); ?>-menu" class="hide <?php echo $isActive ? 'active' : ''; ?>">
                        <a href="<?php echo htmlspecialchars(module_url($item['module_key'], portal_url($item['page'])), ENT_QUOTES); ?>">
                            <i class="<?php echo htmlspecialchars($item['icon'], ENT_QUOTES); ?>"></i>
                            <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?></span>
                            <?php if ($badgeId !== ''): ?>
                                <span class="badge bg-danger" id="<?php echo htmlspecialchars($badgeId, ENT_QUOTES); ?>"></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <ul class="nav navbar-nav navbar-right">
                <li class="dropdown hidden-xs hidden-sm">
                    <a href="#" id="tour-help" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="true">
                        <i class="ti-help"></i> <span class="hide">Help</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a href="https://reuniterx.com/faq/" target="blank"><i class="ti-light-bulb"></i> Help/FAQ</a></li>
                    </ul>
                </li>

                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                        <span id="login_user_name"><?php echo htmlspecialchars(portal_config('portal_title_short', 'User'), ENT_QUOTES); ?></span>
                        <i class="ti-angle-down icon-submenu"></i>
                    </a>
                    <ul class="dropdown-menu logged-user-menu">
                        <?php foreach ((array) $nav_menu as $item): ?>
                            <?php
                            $tab = isset($item['tab']) ? $normalize_tab((string) $item['tab']) : '';
                            $liClasses = trim((string) ($item['class'] ?? ''));
                            if ($tab !== '') {
                                $liClasses = trim('hide ' . $liClasses);
                            }
                            ?>
                            <li<?php echo $tab !== '' ? ' id="' . htmlspecialchars($tab, ENT_QUOTES) . '-menu"' : ''; ?><?php echo $liClasses !== '' ? ' class="' . htmlspecialchars($liClasses, ENT_QUOTES) . '"' : ''; ?>>
                                <a href="<?php echo htmlspecialchars($nav_url($item), ENT_QUOTES); ?>">
                                    <i class="<?php echo htmlspecialchars((string) ($item['icon'] ?? 'ti-angle-right'), ENT_QUOTES); ?>"></i>
                                    <span><?php echo htmlspecialchars((string) ($item['label'] ?? 'Menu'), ENT_QUOTES); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <li>
                            <a class="logout-btn" href="javascript:;">
                                <i class="ti-power-off"></i> <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
