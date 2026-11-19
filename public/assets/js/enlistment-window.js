(function () {
    var configEl = document.getElementById('ewConfig');
    if (!configEl) return;
    var config = JSON.parse(configEl.textContent);

    document.addEventListener('click', function (e) {
        var grantBtn = e.target.closest('[data-grant-window-override]');
        if (grantBtn) {
            var studentId = grantBtn.getAttribute('data-grant-window-override');
            var reason = window.prompt('Reason for unlocking enlistment for this student (required):');
            if (!reason || !reason.trim()) return;

            grantBtn.disabled = true;
            fetch(config.grantUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'student_id=' + encodeURIComponent(studentId) + '&ay_id=' + encodeURIComponent(config.ayId) + '&reason=' + encodeURIComponent(reason.trim())
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) { window.alert(data.error || 'Could not grant this override.'); grantBtn.disabled = false; return; }
                    window.location.reload();
                })
                .catch(function () { window.alert('Could not grant this override.'); grantBtn.disabled = false; });
            return;
        }

        var revokeBtn = e.target.closest('[data-revoke-window-override]');
        if (revokeBtn) {
            if (!window.confirm('Revoke this override? The student will lose access again.')) return;
            var overrideId = revokeBtn.getAttribute('data-revoke-window-override');

            revokeBtn.disabled = true;
            fetch(config.revokeUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'override_id=' + encodeURIComponent(overrideId)
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) { window.alert(data.error || 'Could not revoke this override.'); revokeBtn.disabled = false; return; }
                    window.location.reload();
                })
                .catch(function () { window.alert('Could not revoke this override.'); revokeBtn.disabled = false; });
        }
    });
})();
