/**
 * Client-side mirror of app/Helpers/admin_helper.php's avatar_color()/
 * avatar_photo_url() — same 8-color palette, same crc32 hash — so a
 * person's avatar color matches everywhere in the app, whether rendered
 * server-side or built from a JSON response.
 */
window.AvatarHelper = (function () {
    'use strict';

    var PALETTE = ['#174ea6', '#0f766e', '#a65b08', '#15803d', '#4f5eb3', '#b91c1c', '#7c3aed', '#0e7490'];
    var table = null;

    function crc32(str) {
        if (!table) {
            table = [];
            for (var n = 0; n < 256; n++) {
                var c = n;
                for (var k = 0; k < 8; k++) c = c & 1 ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
                table[n] = c;
            }
        }
        var crc = 0xFFFFFFFF;
        for (var i = 0; i < str.length; i++) crc = table[(crc ^ str.charCodeAt(i)) & 0xFF] ^ (crc >>> 8);
        return (crc ^ 0xFFFFFFFF) >>> 0;
    }

    function color(seed) {
        return PALETTE[crc32(String(seed ?? '')) % PALETTE.length];
    }

    function initials(name) {
        return String(name || '?').slice(0, 2).toUpperCase();
    }

    // avatarBaseUrl is each page's own site_url('avatar') (the app may not
    // be served from the domain root), so this never hardcodes a path.
    function photoUrl(avatarBaseUrl, userId, photoMime) {
        return photoMime ? avatarBaseUrl + '/' + userId : null;
    }

    return { color: color, initials: initials, photoUrl: photoUrl };
})();
