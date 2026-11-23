const sessionConsents = new Set();

function unlock(root) {
    if (!root || root.dataset.state === 'playing') return;
    if (!hasEmbedConsent(root)) return;
    const iframe = root.querySelector('.embed__iframe[data-src]');
    if (!iframe) return;
    iframe.src = iframe.dataset.src;
    root.dataset.state = 'playing';
}

function hasConsent(category, consentProvider) {
    const adapter = window.YmEmbedConsent;
    if (adapter && typeof adapter.hasConsent === 'function') {
        return !!adapter.hasConsent(category);
    }
    if (sessionConsents.has(category)) return true;
    if (consentProvider === 'privacywire') {
        return document.body.classList.contains(`consent-${category}`);
    }
    return false;
}

function hasEmbedConsent(root) {
    const category = root.dataset.consentCategory;
    return !category || hasConsent(category, root.dataset.consentProvider);
}

function updateThumbnail(root, consentGranted) {
    const thumbnail = root.querySelector('.embed__thumbnail-img');
    if (!thumbnail) return;

    if (consentGranted && thumbnail.dataset.src) {
        thumbnail.src = thumbnail.dataset.src;
        thumbnail.removeAttribute('data-src');
    } else if (!consentGranted && thumbnail.hasAttribute('src')) {
        thumbnail.dataset.src = thumbnail.src;
        thumbnail.removeAttribute('src');
    }
}

function grantPrivacyWireConsent(category, button) {
    const privacyWire = window.PrivacyWire;
    if (!privacyWire || hasConsent(category, 'privacywire')) return;
    if (
        !privacyWire.userConsent?.cookieGroups ||
        typeof privacyWire.syncConsentToCheckboxes !== 'function' ||
        typeof privacyWire.saveConsent !== 'function'
    ) {
        console.error('YmEmbed: PrivacyWire is loaded without its expected consent API.');
        return;
    }

    privacyWire.userConsent.cookieGroups[category] = true;
    privacyWire.syncConsentToCheckboxes();
    privacyWire.saveConsent();
    button.closest('.embed__consent')?.remove();
}

function syncConsentStates(scope = document) {
    scope.querySelectorAll('.embed[data-consent-category]').forEach((root) => {
        const consentGranted = hasEmbedConsent(root);
        updateThumbnail(root, consentGranted);
        if (!consentGranted && root.dataset.state === 'playing') {
            const iframe = root.querySelector('.embed__iframe');
            if (iframe) iframe.removeAttribute('src');
        }
        if (consentGranted && root.dataset.state === 'requires-consent') {
            if (root.dataset.autoload === 'true') {
                const iframe = root.querySelector('.embed__iframe[data-src]');
                if (iframe) {
                    iframe.src = iframe.dataset.src;
                    root.dataset.state = 'playing';
                }
            } else {
                root.dataset.state = 'placeholder';
            }
        } else if (!consentGranted && root.dataset.state !== 'requires-consent') {
            root.dataset.state = 'requires-consent';
        }
    });
}

function handleClick(event) {
    const playBtn = event.target.closest('.embed__button--play');
    const consentBtn = event.target.closest('.embed__button--consent');
    if (!playBtn && !consentBtn) return;

    const root = event.target.closest('.embed');
    if (!root) return;

    if (consentBtn) {
        const category = root.dataset.consentCategory || consentBtn.dataset.consent;
        if (root.dataset.consentProvider === 'privacywire') {
            grantPrivacyWireConsent(category, consentBtn);
            syncConsentStates();
            return;
        }
        const adapter = window.YmEmbedConsent;
        const requestConsent = adapter && (adapter.requestConsent || adapter.grant);
        if (typeof requestConsent === 'function') {
            const result = requestConsent.call(adapter, category);
            if (result && typeof result.then === 'function') result.then(() => syncConsentStates());
        } else if (category) {
            sessionConsents.add(category);
            syncConsentStates();
        }
    } else {
        unlock(root);
    }
}

export function init(scope = document) {
    document.documentElement.classList.add('ymembed-js');
    syncConsentStates(scope);
    if (document.body && !document.body.__ymEmbedConsentObserver) {
        const observer = new MutationObserver(() => syncConsentStates());
        observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
        document.body.__ymEmbedConsentObserver = observer;
    }
    if (!document.__ymEmbedConsentListener) {
        document.addEventListener('YmEmbedConsentChanged', () => syncConsentStates());
        document.__ymEmbedConsentListener = true;
    }
    if (!scope.__ymEmbedDelegated) {
        scope.addEventListener('click', handleClick);
        scope.__ymEmbedDelegated = true;
    }
}

export { unlock, hasConsent, syncConsentStates };