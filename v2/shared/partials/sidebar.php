<?php
/**
 * Data-driven sidebar — mockup sidenav button pattern.
 *
 * Expects:
 *   $sidebar_menu  — array loaded from portals/{portal}/sidebar_menu.php
 *   $active_tab    — string key for the current page
 */
$sidebar_menu = $sidebar_menu ?? [];
?>
<nav class="sidenav" id="sidebar">

    <?php foreach ($sidebar_menu as $group): ?>
    <?php
        $tabs        = array_column($group['items'] ?? [], 'tab');
        $groupActive = isset($active_tab) && in_array($active_tab, $tabs, true);
        $isSingle    = !empty($group['direct']) || count($group['items'] ?? []) === 1;
    ?>

    <?php if ($isSingle): ?>
        <?php $item = $group['items'][0]; ?>
        <?php $itemActive = isset($active_tab) && $active_tab === $item['tab']; ?>
        <button class="top<?php echo $itemActive ? ' active' : ''; ?>"
                data-href="<?php echo htmlspecialchars(module_url($item['module_key'] ?? '', portal_url($item['page'])), ENT_QUOTES); ?>">
            <?php echo htmlspecialchars($group['title'], ENT_QUOTES); ?>
        </button>
    <?php else: ?>
        <div class="mod<?php echo $groupActive ? ' open' : ''; ?>">
            <button class="modhead">
                <?php echo htmlspecialchars($group['title'], ENT_QUOTES); ?>
            </button>
            <div class="subs">
                <?php foreach ($group['items'] as $item): ?>
                <?php $itemActive = isset($active_tab) && $active_tab === $item['tab']; ?>
                <button class="sub<?php echo $itemActive ? ' active' : ''; ?>"
                        data-href="<?php echo htmlspecialchars(module_url($item['module_key'] ?? '', portal_url($item['page'])), ENT_QUOTES); ?>">
                    <?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php endforeach; ?>

</nav>
