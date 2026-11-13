# YmEmbed

Renders embeds with optional consent and lazy-loaded iframes.

## PrivacyWire

Enable **Automatically integrate with PrivacyWire** in FieldtypeEmbed settings
to gate embeds behind the `external_media` consent category. Disabled by default.

## Custom consent managers

Provide `window.YmEmbedConsent.hasConsent(category)` and
`requestConsent(category)`. Dispatch `YmEmbedConsentChanged` on `document`
when consent changes.
