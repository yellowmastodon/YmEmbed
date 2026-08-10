function unlock(root) {
    if (!root || root.dataset.state === 'unlocked') return;
    const iframe = root.querySelector('.embed__iframe[data-src]');
    if (!iframe) return;
    iframe.src = iframe.dataset.src;
    iframe.removeAttribute('data-src');
    root.dataset.state = 'unlocked';
}

function hasConsent(category) {
    const provider = window.YmEmbedConsent;
    if (provider && typeof provider.hasConsent === 'function') {
        return !!provider.hasConsent(category);
    }
    return false;
}

function handleClick(event) {
    const playBtn = event.target.closest('.embed__button--play');
    const consentBtn = event.target.closest('.embed__button--consent');
    if (!playBtn && !consentBtn) return;

    const root = event.target.closest('.embed');
    if (!root) return;

    if (consentBtn) {
        const category = consentBtn.dataset.consent;
        if (window.YmEmbedConsent && typeof window.YmEmbedConsent.grant === 'function') {
            window.YmEmbedConsent.grant(category);
        }
    }
    unlock(root);
}

export function init(scope = document) {
    scope.querySelectorAll('.embed[data-state="locked"]').forEach((root) => {
        const consentBtn = root.querySelector('.embed__button--consent');
        const category = consentBtn ? consentBtn.dataset.consent : null;
        if (!category || hasConsent(category)) unlock(root);
    });
    if (!scope.__ymEmbedDelegated) {
        scope.addEventListener('click', handleClick);
        scope.__ymEmbedDelegated = true;
    }
}

export { unlock, hasConsent };