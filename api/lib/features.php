<?php
/**
 * Portal feature entitlements + brand-scoped navigation.
 *
 * One source of truth for every assignable capability. `users.features` (JSONB)
 * stores which are enabled per account; the catalog defines what's possible, each
 * feature's brand(s), nav label/route/icon. Add IWC (or any) modules later by
 * adding a catalog entry — no other wiring.
 */

/** brand key => display label (order = nav section order) */
function portal_brand_labels(): array {
    return ['md_dme' => 'MD DME', 'iwc' => 'IWC'];
}

/**
 * The feature catalog. 'core' => always on (no toggle, always shown).
 * 'brands' => sections a feature appears under (a feature may be in more than one).
 * 'pages'  => all page slugs this feature guards (defaults to ['page']).
 */
function portal_feature_catalog(): array {
    return [
        'dashboard'        => ['label' => 'Dashboard',        'page' => 'dashboard',     'brands' => ['core'],           'core' => true, 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        'patients'         => ['label' => 'Patient',          'page' => 'patients',      'brands' => ['core'],           'core' => true, 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
        'photo_reviews'    => ['label' => 'Photo Reviews',    'page' => 'photo-reviews', 'brands' => ['md_dme', 'iwc'],                  'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
        'patient_referral' => ['label' => 'Patient Referral', 'page' => 'orders',        'brands' => ['md_dme'], 'pages' => ['orders'], 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        'healkit'          => ['label' => 'HealKit Order',    'page' => 'healkit',       'brands' => ['md_dme'],         'icon' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z'],
        'wholesale'        => ['label' => 'Wholesale Orders', 'page' => 'wholesale',     'brands' => ['md_dme'], 'pages' => ['wholesale', 'wholesale-order', 'create-wholesale-order', 'my-wholesale-orders', 'dme-orders'], 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
        'clinical_docs'    => ['label' => 'Clinical Documentation', 'page' => 'clinical-notes', 'brands' => ['md_dme'], 'pages' => ['clinical-notes'], 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        'messages'         => ['label' => 'Messages',         'page' => 'messages',      'brands' => ['core'],           'core' => true, 'icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z'],
        'profile'          => ['label' => 'Admin',            'page' => 'profile',       'brands' => ['core'],           'core' => true, 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
    ];
}

/** Feature keys that are admin-assignable (i.e. not core). */
function assignable_feature_keys(): array {
    $keys = [];
    foreach (portal_feature_catalog() as $k => $def) { if (empty($def['core'])) $keys[] = $k; }
    return $keys;
}

/** The account's enabled features (falls back to a sensible default when unset). */
function account_features(array $user): array {
    $raw = $user['features'] ?? null;
    if (is_string($raw) && $raw !== '') {
        $f = json_decode($raw, true);
        if (is_array($f)) return $f;
    } elseif (is_array($raw)) {
        return $raw;
    }
    return default_account_features($user);
}

/** Backward-compatible default from the legacy account_type / license flags. */
function default_account_features(array $user): array {
    $wholesale = !empty($user['has_dme_license']) || !empty($user['is_hybrid'])
        || in_array($user['account_type'] ?? '', ['wholesale', 'dme_hybrid', 'both'], true);
    return ['photo_reviews' => true, 'patient_referral' => true, 'healkit' => true, 'wholesale' => $wholesale];
}

/**
 * Every user-account id that belongs to the same practice as $userId.
 *
 * A practice = a practice_admin owner + the physician accounts linked to it via
 * `users.practice_id` (= the owner's id) + any duplicate owner accounts sharing the
 * same non-empty `practice_name` (the grouping practice-pricing uses). Editing the
 * owner or any linked physician resolves the whole set. A standalone account (no
 * practice_id link and no shared practice_name) is its own practice → [$userId].
 * Used to apply feature entitlements practice-wide.
 */
function practice_member_ids(PDO $pdo, string $userId): array {
    $st = $pdo->prepare("SELECT role, practice_id, practice_name FROM users WHERE id = ?");
    $st->execute([$userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) return [$userId];

    // Resolve the practice owner account: self if this IS the owner, else the owner
    // this physician is linked to via practice_id.
    $ownerId = ($row['role'] ?? '') === 'practice_admin'
        ? $userId
        : (trim((string)($row['practice_id'] ?? '')) ?: null);

    // The authoritative practice_name (the owner's when we have one).
    $practiceName = trim((string)($row['practice_name'] ?? ''));
    if ($ownerId !== null && $ownerId !== $userId) {
        $o = $pdo->prepare("SELECT practice_name FROM users WHERE id = ?");
        $o->execute([$ownerId]);
        $practiceName = trim((string)$o->fetchColumn());
    }

    // Owner id-set: the resolved owner + any duplicate owner accounts sharing the name.
    $owners = [];
    if ($ownerId !== null) $owners[$ownerId] = true;
    if ($practiceName !== '') {
        $du = $pdo->prepare("SELECT id FROM users WHERE practice_name = ?");
        $du->execute([$practiceName]);
        foreach ($du->fetchAll(PDO::FETCH_COLUMN) as $id) $owners[$id] = true;
    }

    $ids = [$userId];
    foreach (array_keys($owners) as $id) $ids[] = $id;
    // Every physician linked to any of those owner accounts.
    if ($owners) {
        $ownerIds = array_keys($owners);
        $in = implode(',', array_fill(0, count($ownerIds), '?'));
        $ph = $pdo->prepare("SELECT id FROM users WHERE practice_id IN ($in)");
        $ph->execute($ownerIds);
        foreach ($ph->fetchAll(PDO::FETCH_COLUMN) as $id) $ids[] = $id;
    }
    return array_values(array_unique($ids));
}

function has_feature(array $user, string $key): bool {
    $cat = portal_feature_catalog();
    if (!isset($cat[$key])) return true;              // not a catalog feature => not gated
    if (!empty($cat[$key]['core'])) return true;      // core always on
    $f = account_features($user);
    return !empty($f[$key]);
}

/** Which feature (if any) guards a given ?page= slug. */
function feature_for_page(string $page): ?string {
    foreach (portal_feature_catalog() as $key => $def) {
        if (!empty($def['core'])) continue;
        $pages = $def['pages'] ?? [$def['page']];
        if (in_array($page, $pages, true)) return $key;
    }
    return null;
}

/** True if the account may view a page (core/unmapped pages are always allowed). */
function page_allowed(array $user, string $page): bool {
    $key = feature_for_page($page);
    return $key === null ? true : has_feature($user, $key);
}

function render_portal_nav_item(string $key, array $def, string $page): void {
    $slug = $def['page'] ?? $key;
    $active = ($page === $slug) ? 'active' : '';
    echo '<a class="' . $active . '" href="?page=' . htmlspecialchars($slug) . '">';
    echo '<svg class="sidebar-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="' . htmlspecialchars($def['icon']) . '"></path></svg>';
    echo '<span>' . htmlspecialchars($def['label']) . '</span></a>';
}

/** Render the whole sidebar: core items, then a header + items per brand the account has. */
function render_portal_nav(array $user, string $page, bool $isPracticeAdmin): void {
    $cat = portal_feature_catalog();
    $acct = account_features($user);
    $rendered = [];
    foreach ($cat as $key => $def) {
        if (empty($def['core'])) continue;
        if ($key === 'profile' && !$isPracticeAdmin) continue; // "Admin" is practice-admin only
        render_portal_nav_item($key, $def, $page);
        $rendered[$key] = true;
    }
    foreach (portal_brand_labels() as $brand => $label) {
        $items = [];
        foreach ($cat as $key => $def) {
            if (!empty($def['core']) || isset($rendered[$key])) continue;
            if (in_array($brand, $def['brands'], true) && !empty($acct[$key])) $items[$key] = $def;
        }
        if (!$items) continue;
        echo '<div class="sidebar-section">' . htmlspecialchars($label) . '</div>';
        foreach ($items as $key => $def) { render_portal_nav_item($key, $def, $page); $rendered[$key] = true; }
    }
}
