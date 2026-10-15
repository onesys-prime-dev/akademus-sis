(function () {
    'use strict';

    var root = document.getElementById('studentDocuments');
    var dialog = document.getElementById('sdViewDialog');
    if (!root || !dialog) return;

    var frame = document.getElementById('sdViewFrame');
    var title = document.getElementById('sdViewTitle');
    var downloadLink = document.getElementById('sdViewDownload');
    var loading = document.getElementById('sdViewLoading');
    var loadingText = document.getElementById('sdViewLoadingText');
    var spinner = loading.querySelector('.sd-spinner');
    var closeBtn = document.getElementById('sdViewClose');
    var returnFocus = null;
    var blobUrl = null;
    var requestToken = 0;

    function releaseBlob() {
        if (blobUrl) { URL.revokeObjectURL(blobUrl); blobUrl = null; }
    }

    function showLoading(message) {
        loadingText.textContent = message;
        spinner.hidden = false;
        loading.hidden = false;
        frame.hidden = true;
    }

    function openDoc(button) {
        var token = ++requestToken;
        returnFocus = button;
        title.textContent = button.dataset.viewTitle || 'Document';
        downloadLink.href = button.dataset.viewUrl.replace('/documents/view/', '/documents/download/');
        releaseBlob();
        frame.src = 'about:blank';
        showLoading('Loading document…');
        if (!dialog.open) dialog.showModal();

        // Fetched as a blob (not pointed at the URL directly) so there's a
        // reliable completion signal to hide the spinner on — Chrome's
        // built-in PDF viewer doesn't consistently fire the iframe's own
        // `load` event once it takes over rendering.
        fetch(button.dataset.viewUrl, { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) throw new Error('Request failed: ' + response.status);
                return response.blob();
            })
            .then(function (blob) {
                if (token !== requestToken) return; // a newer open() superseded this one
                blobUrl = URL.createObjectURL(blob);
                frame.src = blobUrl;
                loading.hidden = true;
                frame.hidden = false;
            })
            .catch(function () {
                if (token !== requestToken) return;
                spinner.hidden = true;
                loadingText.textContent = 'Could not load this document. Try the Download button instead.';
            });
    }

    root.addEventListener('click', function (event) {
        var button = event.target.closest('[data-view-doc]');
        if (button) openDoc(button);
    });

    closeBtn.addEventListener('click', function () { dialog.close(); });

    dialog.addEventListener('click', function (event) {
        if (event.target !== dialog) return;
        var r = dialog.getBoundingClientRect();
        var inside = event.clientX >= r.left && event.clientX <= r.right && event.clientY >= r.top && event.clientY <= r.bottom;
        if (!inside) dialog.close();
    });

    dialog.addEventListener('close', function () {
        requestToken++; // ignore any still-in-flight fetch from this view
        frame.src = 'about:blank';
        releaseBlob();
        if (returnFocus && returnFocus.isConnected) returnFocus.focus({ preventScroll: true });
    });
})();
