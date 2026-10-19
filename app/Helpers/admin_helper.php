<?php

if (! function_exists('admin_icon')) {
    /**
     * Inline stroke-icon SVG used across the admin console.
     */
    function admin_icon(string $name, int $size = 18): string
    {
        $paths = [
            'grid'        => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
            'building'    => '<rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 22v-4h6v4M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01"/>',
            'users'       => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            'door'        => '<path d="M3 21h18M6 21V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v17M14 12h.01"/>',
            'cal'         => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
            'shield'      => '<path d="M12 2l8 4v6c0 5-3.5 8-8 10-4.5-2-8-5-8-10V6z"/>',
            'plus'        => '<path d="M12 5v14M5 12h14"/>',
            'x'           => '<path d="M18 6L6 18M6 6l12 12"/>',
            'check'       => '<path d="M20 6L9 17l-5-5"/>',
            'alert'       => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>',
            'cap'         => '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1 2 3 6 3s6-2 6-3v-5"/>',
            'edit'        => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>',
            'search'      => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/>',
            'logout'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        ];

        $d = $paths[$name] ?? '';

        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $d . '</svg>';
    }
}

if (! function_exists('avatar_color')) {
    /**
     * Deterministic background color for a letter avatar, picked from a
     * fixed palette by hashing $seed (username, or any other stable
     * per-person string) — the same person always gets the same color, and
     * different people are spread across the palette instead of every
     * avatar in the app being an identical flat blue.
     */
    function avatar_color(string $seed): string
    {
        static $palette = ['#174ea6', '#0f766e', '#a65b08', '#15803d', '#4f5eb3', '#b91c1c', '#7c3aed', '#0e7490'];

        return $palette[crc32($seed) % count($palette)];
    }
}

if (! function_exists('avatar_photo_url')) {
    /**
     * The URL for a user's uploaded profile photo, or null if they haven't
     * set one (callers fall back to the letter avatar). Served through
     * AvatarController straight from the users.photo_blob column, never a
     * public upload path — see its own docblock. $photoMime (users.photo_mime)
     * is what signals "a photo is set"; the blob itself is never passed
     * around just to check presence.
     */
    function avatar_photo_url(int $userId, ?string $photoMime): ?string
    {
        return $photoMime !== null && $photoMime !== '' ? site_url("avatar/{$userId}") : null;
    }
}

if (! function_exists('time_ago')) {
    /**
     * "2h ago" / "3d ago" style relative timestamp.
     */
    function time_ago(?string $datetime): string
    {
        if (! $datetime) {
            return '';
        }

        $diff = time() - strtotime($datetime);

        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            return floor($diff / 60) . 'm ago';
        }
        if ($diff < 86400) {
            return floor($diff / 3600) . 'h ago';
        }
        if ($diff < 2592000) {
            return floor($diff / 86400) . 'd ago';
        }

        return date('M j, Y', strtotime($datetime));
    }
}
