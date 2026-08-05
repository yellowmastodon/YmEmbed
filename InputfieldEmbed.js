function initInputfieldEmbed() {
    const fields = document.querySelectorAll('.InputfieldEmbed');

    fields.forEach(field => {
        const baseID = field.dataset.id;
        const previewContainer = field.querySelector('.embed-preview');
        const previewTabAnchor = field.querySelector('.embed-preview-tab a');
        const spinner = field.querySelector('.InputfieldEmbed__spinner');
        const overlay = field.querySelector('.InputfieldEmbed__overlay');
        const errorContainer = field.querySelector('.InputfieldEmbed__error') || createErrorBox(field);
        const reloadButon = field.querySelector('.InputfieldEmbed__url-button');


        const inputs = {
            "processed": field.querySelector(`#${baseID}_processed`),
            "url": field.querySelector(`#${baseID}_url`),
            "html": field.querySelector(`#${baseID}_html`), // hidden field storing iframe html
            "title": field.querySelector(`#${baseID}_title`)
        };

        if (!inputs.url || inputs.url.dataset.init) return;
        inputs.url.dataset.init = "1";

        const settings = ProcessWire.config.InputfieldEmbed || {};
        const ajaxUrl = settings.ajaxUrl || '';

        let state = inputs.processed ? Number(inputs.processed.value) : 0;

        // Helper to clear prior error states
        function clearError() {
            errorContainer.textContent = '';
            errorContainer.hidden = true;
        }

        // Helper to show inline JS error message
        function showError(msg) {
            errorContainer.textContent = msg;
            errorContainer.hidden = false;
        }

        // Helper to create inline error box if not already present in HTML
        function createErrorBox(container) {
            const el = document.createElement('div');
            el.className = 'InputfieldEmbed__error ui-state-error ui-corner-all ';
            el.style.cssText = 'margin-top: 8px; padding: 6px 10px; font-size: 13px;';
            el.hidden = true;
            container.querySelector('.InputfieldContent')?.prepend(el);
            return el;
        }

        // Fetch oEmbed payload via AJAX
        function fetchEmbed(urlValue) {
            clearError();

            if (!urlValue.trim()) {
                if (previewContainer) previewContainer.innerHTML = '';
                return;
            }

            inputs.url.disabled = true;
            if (spinner) spinner.hidden = false;
            if (overlay) overlay.hidden = false;

            fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({ url: urlValue }),
            })
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP ${res.status}: Server Error`);
                    console.log(res);
                    return res.json();
                })
                .then(data => {
                    if (data.error || !data.success) {
                        throw new Error(data.error || 'Failed to fetch embed data for this URL.');
                    }

                    // Update hidden HTML input if present
                    if (inputs.html && data.html) {
                        inputs.html.value = data.html;
                    }

                    if (inputs.title && data.title){
                        inputs.title.value = data.title;
                    }

                    // Populate Preview Tab
                    if (data.html && previewContainer) {
                        previewContainer.innerHTML = data.html;
                        if (previewTabAnchor) previewTabAnchor.click();
                    }
                })
                .catch(err => {
                    console.error('YmEmbed Fetch Error:', err);
                    showError(err.message || 'Could not fetch oEmbed content.');
                })
                .finally(() => {
                    inputs.url.disabled = false;
                    if (spinner) spinner.hidden = true;
                    if (overlay) overlay.hidden = true;
                    inputs.url.focus();
                });
        }

        // Handle Paste
        inputs.url.addEventListener('paste', (event) => {
            let paste = (event.clipboardData || window.clipboardData).getData("text");
            event.preventDefault();
            inputs.url.value = paste;
            fetchEmbed(paste);
        });

        reloadButon.addEventListener('click', (event) => {
            event.preventDefault();
            fetchEmbed(inputs.url.value);
        })

        // Clear errors on input typing
        inputs.url.addEventListener('input', () => {
            if (!errorContainer.hidden) clearError();
        });
    });
}

// Event Listeners for ProcessWire DOM states
document.addEventListener("repeateradd", (event) => {
    if (event.target.classList.contains("Inputfield")) initInputfieldEmbed();
});

document.addEventListener("reloaded", (event) => {
    if (event.target.classList.contains("Inputfield")) initInputfieldEmbed();
});

document.addEventListener('DOMContentLoaded', initInputfieldEmbed);