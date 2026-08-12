import { init as initEmbeds } from './lazyframe-core.js';

/**
 * PW's InputfieldTinyMCE (inline mode) only initializes an editor lazily, on the
 * first click/mouseover/focus/touchstart — see InputfieldTinyMCE.js initDocumentEvents().
 * Until then, tinymce.get(id) returns undefined and there is no live editor to read
 * from or write to. This simulates that trigger so we can force-init a field the
 * user never actually touched (e.g. resetting on a fresh fetch).
 */
function forceLoadInlineEditor(target) {
    return new Promise((resolve) => {
        const wrapper = target.closest('.InputfieldTinyMCE');
        if (!wrapper || wrapper.classList.contains('InputfieldTinyMCELoaded')) {
            resolve();
            return;
        }
        target.dispatchEvent(new Event('mouseover', { bubbles: true }));
        const check = setInterval(() => {
            if (wrapper.classList.contains('InputfieldTinyMCELoaded')) {
                clearInterval(check);
                resolve();
            }
        }, 50);
        // Safety net in case tinymce.init() never completes (blocked script, etc.)
        // — don't hang the caller forever.
        setTimeout(() => { clearInterval(check); resolve(); }, 2000);
    });
}


/**
 * Single entry point for writing to a description field, whether it's a live
 * TinyMCE inline editor, a not-yet-loaded one, a plain textarea, or a bare
 * contenteditable fallback. Handles force-loading and hidden-input sync so callers
 * (reset, fetch success) don't need to know which case they're in.
 */
async function setDescriptionValue(el, html) {
    await forceLoadInlineEditor(el);
    const editor = window.tinymce && el.id ? tinymce.get(el.id) : null;
    if (editor) {
        editor.setContent(html || '');
    } else if (el.value !== undefined) {
        el.value = html || '';
    } else {
        el.innerHTML = html || '';
    }
    el.dispatchEvent(new Event('input', { bubbles: true }));
}



function initInputfieldEmbed() {

    initEmbeds();

    const fields = document.querySelectorAll('.InputfieldEmbed');

    fields.forEach(field => {
        const baseID = field.dataset.id;
        const previewContainer = field.querySelector('.embed-preview');
        const previewTabAnchor = field.querySelector('.embed-preview-tab a');
        const spinner = field.querySelector('.InputfieldEmbed__spinner');
        const overlay = field.querySelector('.InputfieldEmbed__overlay');
        const errorContainer = field.querySelector('.InputfieldEmbed__error') || createErrorBox(field);
        const reloadButton = field.querySelector('.InputfieldEmbed__src-url-button');

        const inputs = {
            "provider": field.querySelector(`#${baseID}_provider`),
            "processed": field.querySelector(`#${baseID}_processed`),
            "src_url": field.querySelector(`#${baseID}_src_url`),
            "url": field.querySelector(`#${baseID}_url`),
            "html": field.querySelector(`#${baseID}_html`), // hidden field storing iframe html
            "showtitle": field.querySelector(`#${baseID}_showtitle`),
            "attribution_required": field.querySelector(`#${baseID}_attribution_required`),
            "description": field.querySelectorAll('.InputfieldEmbed__description .mce-content-body, .InputfieldEmbed__description [contenteditable="true"], .InputfieldEmbed__description textarea'), // multilang fields
            "description_default_lang": field.querySelector(`#${baseID}_description`),
            "title": field.querySelectorAll(`.InputfieldEmbed__title input[type="text"]`),
            "title_default_lang": field.querySelector(`#${baseID}_title`),
            "thumbnail_url": field.querySelector(`#${baseID}_thumbnail_url`),
            "aspect_ratio": field.querySelector(`#${baseID}_aspect_ratio`)
        };

        if (!inputs.src_url || inputs.src_url.dataset.init) return;
        inputs.src_url.dataset.init = "1";

        const settings = ProcessWire.config.InputfieldEmbed || {};
        const ajaxUrl = settings.ajaxUrl || '';

        function fireChange(el) {
            if (!el) return;
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // --- Helpers for preview DOM manipulation ---

        // Helper to get full HTML description (supports TinyMCE or standard Textarea/Input)
        function getDescriptionText() {
            if (!inputs.description_default_lang) return '';

            const elementId = inputs.description_default_lang.id;
            if (window.tinymce && elementId && tinymce.get(elementId)) {
                return tinymce.get(elementId).getContent();
            }

            if (inputs.description_default_lang.value !== undefined) {
                return inputs.description_default_lang.value.trim();
            }

            return (inputs.description_default_lang.innerHTML || '').trim();
        }

        // Helper function to dynamically swap element tag names (<figure> <-> <div>)
        function changeTagName(el, newTagName) {
            if (el.tagName.toLowerCase() === newTagName.toLowerCase()) return el;
            const newEl = document.createElement(newTagName);
            for (const attr of el.attributes) {
                newEl.setAttribute(attr.name, attr.value);
            }
            while (el.firstChild) {
                newEl.appendChild(el.firstChild);
            }
            el.parentNode.replaceChild(newEl, el);
            return newEl;
        }

        // Modifies iframe title, outer tag (<figure>/<div>) & figcaption in-place matching PHP render() logic
        function updatePreviewDOM() {
            if (!previewContainer) return;

            const iframe = previewContainer.querySelector('iframe');
            if (!iframe) return;

            // 1. Update iframe accessibility title attribute
            const titleText = inputs.title_default_lang ? inputs.title_default_lang.value.trim() : '';
            if (inputs.title_default_lang) {
                iframe.setAttribute('title', titleText);
            }

            // 2. Evaluate PHP-equivalent caption rules
            const showTitleVal = inputs.showtitle ? String(inputs.showtitle.value) : '1';
            const isAttributionRequired = inputs.attribution_required ? String(inputs.attribution_required.value) === '1' : false;
            const descriptionText = getDescriptionText();

            let captionText = '';
            if (isAttributionRequired) {
                captionText = descriptionText;
            } else if (showTitleVal === '2') { // titleSeparate
                captionText = descriptionText;
            } else if (showTitleVal === '1') { // titleShow
                captionText = titleText;
            }

            const hasCaption = isAttributionRequired || Boolean(captionText);

            // 3. Locate outer container (.embed) and wrapper (.embed__wrapper)
            let embedEl = previewContainer.querySelector('.embed');
            if (!embedEl) return;

            // 4. Swap outer tag between <figure> and <div> to match PHP $hasCaption logic
            const targetTag = hasCaption ? 'figure' : 'div';
            embedEl = changeTagName(embedEl, targetTag);


            // 5. Handle <figcaption> inside .embed__wrapper
            let figcaption = embedEl.querySelector('figcaption');

            if (hasCaption) {
                if (!figcaption) {
                    figcaption = document.createElement('figcaption');
                    embedEl.appendChild(figcaption);
                }

                // Set classes to match PHP output
                figcaption.className = 'embed__caption';
                if (isAttributionRequired) {
                    figcaption.classList.add('embed__caption--attribution');
                }

                figcaption.innerHTML = captionText;
                figcaption.hidden = false;
            } else if (figcaption) {
                figcaption.remove();
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

        // A. Immediate update on select/radio change
        if (inputs.showtitle) {
            inputs.showtitle.addEventListener('change', updatePreviewDOM);
        }

        // B. Debounced updates on Title input (handles typing + Ctrl+Z keyup)
        if (inputs.title_default_lang) {
            ['input', 'change', 'keyup'].forEach(evt => {
                inputs.title_default_lang.addEventListener(evt, debouncedUpdatePreviewDOM);
            });
        }

        // C. Debounced updates on Description input (Textarea or TinyMCE)
        if (inputs.description_default_lang) {
            ['input', 'change', 'keyup'].forEach(evt => {
                inputs.description_default_lang.addEventListener(evt, debouncedUpdatePreviewDOM);
            });

            if (window.tinymce && inputs.description_default_lang.id) {
                let attempts = 0;
                const checkTiny = setInterval(() => {
                    attempts++;
                    const editor = tinymce.get(inputs.description_default_lang.id);
                    if (editor) {
                        editor.on('input change keyup NodeChange SetContent Undo Redo ExecCommand', debouncedUpdatePreviewDOM);
                        clearInterval(checkTiny);
                    } else if (attempts >= 10) {
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
                if (input instanceof NodeList || Array.isArray(input)) {
                    input.forEach(el => el.disabled = disabled);
                } else if (input) {
                    input.disabled = disabled;
                }
            });
            if (reloadButton) reloadButton.disabled = disabled;
        }

        // Set initial visibility of the reload button on init
        if (reloadButton) {
            reloadButton.hidden = !inputs.src_url.value.trim();
        }

        // Fetch oEmbed payload via AJAX
        function fetchEmbed(urlValue) {
            clearError();
            if (!urlValue.trim()) {
                if (previewContainer) previewContainer.innerHTML = '';
                if (reloadButton) reloadButton.hidden = true;
                return;
            }

            setInputsDisabled(true);

            // Reset previous values ahead of a fresh fetch.
            // NOTE: inputs.processed is the hidden <input> element — set its .value,
            // never reassign inputs.processed itself, or the object stops holding a
            // reference to the DOM node at all (silent bug: later reads/writes of
            // inputs.processed would operate on a plain number, not the field).
            if (inputs.processed) inputs.processed.value = 0;
            if (inputs.aspect_ratio) inputs.aspect_ratio.value = 1.777778;

            // setDescriptionValue() forces lazy-loaded inline TinyMCE editors to
            // initialize (if not already) and syncs their real POST-able hidden
            // input via 'saveReady' — plain editor.setContent('') alone would only
            // clear the on-screen editor, not the field that actually gets saved.
            inputs.description.forEach(el => {
                setDescriptionValue(el, '');
            });

            inputs.title.forEach(el => {
                el.value = '';
                el.dispatchEvent(new Event('input', { bubbles: true }));
            });
            if (inputs.showtitle) inputs.showtitle.value = "0"; // TitleHidden



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
                .then(async data => {
                    if (data.error || !data.success) {
                        const errorText = data.message
                            || (typeof data.error === 'string' ? data.error : null)
                            || 'Failed to fetch embed data for this URL.';
                        throw new Error(errorText);
                    }

                    if (inputs.processed) inputs.processed.value = 1;

                    if (inputs.html && data.html) {
                        inputs.html.value = data.html;
                    }

                    if (data.provider && inputs.provider) {
                        inputs.provider.value = data.provider;
                    }

                    if (data.title && inputs.title_default_lang) {
                        inputs.title_default_lang.value = data.title;
                    }

                    if (data.url && inputs.url) {
                        inputs.url.value = data.url;
                    }
                    if (data.aspect_ratio && inputs.aspect_ratio){
                        inputs.aspect_ratio.value = data.aspect_ratio;
                    }

                    if (data.thumbnail_url && inputs.thumbnail_url) {
                        inputs.thumbnail_url.value = data.thumbnail_url;
                    }

                    if (data.description && inputs.description_default_lang) {
                        // Routed through setDescriptionValue() (force-load + saveReady
                        // sync) instead of writing straight to editor/element — same
                        // reasoning as the reset path above.
                        await setDescriptionValue(inputs.description_default_lang, data.description);

                        if (inputs.showtitle) inputs.showtitle.value = "2"; // TitleSeparate
                        inputs.description_default_lang.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    if (inputs.attribution_required) {
                        const isAttributionRequired = Boolean(data.attribution_required);

                        inputs.attribution_required.value = isAttributionRequired ? 1 : 0;

                        if (isAttributionRequired) {
                            if (inputs.showtitle) inputs.showtitle.value = "2"; // TitleSeparate
                            field.classList.add('InputfieldEmbed--attribution-required');
                        } else {
                            field.classList.remove('InputfieldEmbed--attribution-required');
                        }

                        fireChange(inputs.attribution_required);
                        fireChange(inputs.showtitle);
                    }

                    const renderOutput = data.render || data.html;
                    if (renderOutput && previewContainer) {
                        previewContainer.innerHTML = renderOutput;
                        updatePreviewDOM(); // Sync initial preview caption & tag structure
                        if (previewTabAnchor) previewTabAnchor.click();
                    }

                    if (reloadButton) {
                        reloadButton.hidden = false;
                    }
                })
                .catch(err => {
                    console.error('YmEmbed Fetch Error:', err.message);
                    showError(err.message || 'Could not fetch oEmbed content.');
                })
                .finally(() => {
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

        if (reloadButton) {
            reloadButton.addEventListener('click', (event) => {
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