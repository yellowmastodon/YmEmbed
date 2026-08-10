function initInputfieldEmbed() {
    const fields = document.querySelectorAll('.InputfieldEmbed');

    fields.forEach(field => {
        const baseID = field.dataset.id;
        const previewContainer = field.querySelector('.embed-preview');
        const previewTabAnchor = field.querySelector('.embed-preview-tab a');
        const spinner = field.querySelector('.InputfieldEmbed__spinner');
        const overlay = field.querySelector('.InputfieldEmbed__overlay');
        const errorContainer = field.querySelector('.InputfieldEmbed__error') || createErrorBox(field);
        const reloadButon = field.querySelector('.InputfieldEmbed__src-url-button');

        const inputs = {
            "processed": field.querySelector(`#${baseID}_processed`),
            "src_url": field.querySelector(`#${baseID}_src_url`),
            "url": field.querySelector(`#${baseID}_url`),
            "html": field.querySelector(`#${baseID}_html`), // hidden field storing iframe html
            "title": field.querySelector(`#${baseID}_title`),
            "description": field.querySelector(`#${baseID}_description`),
            "showtitle": field.querySelector(`#${baseID}_showtitle`),
            "attribution_required": field.querySelector(`#${baseID}_attribution_required`),
        };


        if (!inputs.src_url || inputs.src_url.dataset.init) return;
        inputs.src_url.dataset.init = "1";

        const settings = ProcessWire.config.InputfieldEmbed || {};
        const ajaxUrl = settings.ajaxUrl || '';

        function fireChange(el) {
            if (!el) return;
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // --- Helpers for non-destructive preview DOM manipulation ---

        // Helper to get raw description text (supports TinyMCE or standard Textarea)
        function getDescriptionText() {
            if (!inputs.description) return '';
            if (window.tinymce && tinymce.get(inputs.description.id)) {
                const html = tinymce.get(inputs.description.id).getContent();
                const tmp = document.createElement('div');
                tmp.innerHTML = html;
                return (tmp.textContent || tmp.innerText || '').trim();
            }
            return (inputs.description.value || '').trim();
        }

        // Target update: modifies iframe title, figure wrapper & figcaption in-place
       // Target update: modifies iframe title, figure wrapper & figcaption in-place
        function updatePreviewDOM() {
            if (!previewContainer) return;

            const iframe = previewContainer.querySelector('iframe');
            if (!iframe) return;

            // 1. Update iframe accessibility title attribute
            const titleText = inputs.title ? inputs.title.value.trim() : '';
            if (inputs.title) {
                iframe.setAttribute('title', titleText);
            }

            // 2. Determine caption mode and text
            // Values: 0 = titleHidden, 1 = titleShow, 2 = titleSeparate
            const showTitleVal = inputs.showtitle ? String(inputs.showtitle.value) : '1';
            let captionText = '';

            if (showTitleVal === '1') {
                captionText = titleText;
            } else if (showTitleVal === '2') {
                captionText = getDescriptionText();
            }

            // 3. Target the main embed wrapper container (.embed) or fall back to iframe
            const embedElement = iframe.closest('.embed') || iframe;

            // Ensure <figure> wrapper wraps around the embed container ONCE
            let figure = embedElement.closest('figure');
            if (!figure) {
                figure = document.createElement('figure');
                figure.className = 'embed-figure';
                embedElement.parentNode.insertBefore(figure, embedElement);
                figure.appendChild(embedElement);
            }

            // 4. Ensure <figcaption> exists ONCE inside figure (after embedElement)
            let figcaption = figure.querySelector('figcaption');
            if (!figcaption) {
                figcaption = document.createElement('figcaption');
                figcaption.className = 'embed-figcaption'; // Match with PHP class embed-figure__caption if needed
                figure.appendChild(figcaption);
            }

            // 5. Update caption text or toggle visibility
            if (showTitleVal === '0' || !captionText) {
                figcaption.hidden = true;
                figcaption.textContent = '';
            } else {
                figcaption.textContent = captionText;
                figcaption.hidden = false;
            }
        }

        // Simple debounce helper
        function debounce(fn, delay = 200) {
            let timer;
            return (...args) => {
                clearTimeout(timer);
                timer = setTimeout(() => fn.apply(this, args), delay);
            };
        }

        const debouncedUpdatePreviewDOM = debounce(updatePreviewDOM, 200);

        // --- Event Listeners ---

        // A. Immediate toggle on select change (no debounce)
        if (inputs.showtitle) {
            inputs.showtitle.addEventListener('change', updatePreviewDOM);
        }

        // B. Debounced updates on Title input
        if (inputs.title) {
            inputs.title.addEventListener('input', debouncedUpdatePreviewDOM);
        }

        // C. Debounced updates on Description input (Textarea or TinyMCE)
        if (inputs.description) {
            inputs.description.addEventListener('input', debouncedUpdatePreviewDOM);

            if (window.tinymce) {
                const checkTiny = setInterval(() => {
                    const editor = tinymce.get(inputs.description.id);
                    if (editor) {
                        editor.on('input change keyup NodeChange SetContent', debouncedUpdatePreviewDOM);
                        clearInterval(checkTiny);
                    }
                }, 300);
            }
        }

        // Helper to clear prior error states
        function clearError() {
            const textSpan = errorContainer.querySelector('span');
            if (textSpan) textSpan.textContent = '';
            errorContainer.hidden = true;
            field.classList.remove('InputfieldStateError');
        }

        // Helper to show inline JS error message
        function showError(msg) {
            const textSpan = errorContainer.querySelector('span');
            if (textSpan) {
                textSpan.textContent = msg;
            } else {
                errorContainer.textContent = msg;
            }
            errorContainer.hidden = false;
            field.classList.add('InputfieldStateError');
        }

        // Helper to create native ProcessWire error box
        function createErrorBox(container) {
            const p = document.createElement('p');
            p.className = 'InputfieldError ui-state-error InputfieldEmbed__error';
            p.innerHTML = '<i class="fa fa-fw fa-flash"></i><span></span>';
            p.hidden = true;
            container.querySelector('.InputfieldContent')?.prepend(p);
            return p;
        }

        // Helper to toggle disabled state for all related inputs
        function setInputsDisabled(disabled) {
            Object.values(inputs).forEach(input => {
                if (input) input.disabled = disabled;
            });
            if (reloadButon) reloadButon.disabled = disabled;
        }

        // Set initial visibility of the reload button on init
        if (reloadButon) {
            reloadButon.hidden = !inputs.src_url.value.trim();
        }

        // Fetch oEmbed payload via AJAX
        function fetchEmbed(urlValue) {
            clearError();

            if (!urlValue.trim()) {
                if (previewContainer) previewContainer.innerHTML = '';
                if (reloadButon) reloadButon.hidden = true;
                return;
            }

            // 1. Lock all input fields during fetch
            setInputsDisabled(true);

            if (spinner) spinner.hidden = false;
            if (overlay) overlay.hidden = false;

            const bodyParams = new URLSearchParams({ src_url: urlValue });
            if (window.ProcessWire && ProcessWire.config.TOKEN_NAME) {
                bodyParams.append(ProcessWire.config.TOKEN_NAME, ProcessWire.config.TOKEN_VALUE);
            }

            fetch(ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: bodyParams,
            })
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP ${res.status}: Server Error`);
                    return res.json();
                })
                .then(data => {
                    if (data.error || !data.success) {
                        throw new Error(data.error || 'Failed to fetch embed data for this URL.');
                    }
                    
                    console.log(data);

                    // Store raw oEmbed / html
                    if (inputs.html && data.html) {
                        inputs.html.value = data.html;
                    }

                    if (inputs.title && data.title) {
                        inputs.title.value = data.title;
                    }

                    if (inputs.url && data.url) {
                        inputs.url.value = data.url;
                    }

                    if (inputs.processed) {
                        inputs.processed = 1;
                    }


                    if (inputs.attribution_required) {
                        inputs.attribution_required.value = data.attribution_required ? 1 : 0;
                        if (data.attribution_required) {
                            inputs.showtitle.value = 2;
                        }
                        fireChange(inputs.attribution_required);
                        fireChange(inputs.showtitle);
                    }

                    // Place exact server-rendered HTML into preview container
                    const renderOutput = data.render || data.html;
                    if (renderOutput && previewContainer) {
                        previewContainer.innerHTML = renderOutput;
                        if (previewTabAnchor) previewTabAnchor.click();
                    }

                    // 2. Unhide reload button on successful fetch
                    if (reloadButon) {
                        reloadButon.hidden = false;
                    }
                })
                .catch(err => {
                    console.error('YmEmbed Fetch Error:', err);
                    showError(err.message || 'Could not fetch oEmbed content.');
                })
                .finally(() => {
                    // 3. Unlock inputs after result is placed
                    setInputsDisabled(false);

                    if (spinner) spinner.hidden = true;
                    if (overlay) overlay.hidden = true;
                });
        }

        // Handle Paste & Reload Button
        inputs.src_url.addEventListener('paste', (event) => {
            let paste = (event.clipboardData || window.clipboardData).getData("text");
            event.preventDefault();
            inputs.src_url.value = paste;
            fetchEmbed(paste);
        });

        if (reloadButon) {
            reloadButon.addEventListener('click', (event) => {
                event.preventDefault();
                fetchEmbed(inputs.src_url.value);
            });
        }

        // Clear errors on input typing
        inputs.src_url.addEventListener('input', () => {
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