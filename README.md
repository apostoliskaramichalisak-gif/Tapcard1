# TapCard Pro v1.4.0

Created by **Apostolis Karamichalis**.

TapCard Pro is a WordPress-based NFC + QR digital profile platform.

## Version 1.2 features

- Mobile-first User Dashboard and card preview.
- QR and direct card-link tools.
- Configurable customer-card field visibility and drag-and-drop order.
- Photo and logo support.
- Customer Save Contact and Share Card actions.

## Version 1.1 features

- NFC Tag inventory custom post type.
- NFC UID field and Available / Assigned / Disabled status.
- Assign an NFC tag to a TapCard profile.
- Display the exact NDEF URL to write to the assigned tag.
- Admin tag columns for UID, status and assigned profile.
- User app shows the generated QR after saving.
- QR/API foundation is now internal to the plugin.

## Version 1 features

- WordPress custom post type for profiles.
- Unique profile token.
- Public customer profile route: `/tapcard/{token}/`
- User mobile app: `/tapcard-user/`
- Customer mobile app: `/tapcard-customer/`
- REST API under `/wp-json/tapcard/v1/`
- Profile fields for contact and social information.
- QR generation in the WordPress profile editor.
- NFC-ready URL: write the profile URL to an NDEF URL record.
- Customer actions: phone, email, website, maps and social links.
- vCard contact export.
- Web Share support.
- Mobile-first PWA shell.

## Installation

1. Upload `tapcard-pro.zip` through WordPress Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Go to Settings > Permalinks and press Save once to refresh rewrite rules if needed.
4. Create a `TapCard Profile`.
5. Publish it.
6. Open its Profile URL.
7. Write the Profile URL to an NFC tag as an NDEF URL record.
8. Use the same URL as the QR destination.

## Important v1 architecture

The NFC tag and QR code contain only the profile URL/token. Personal data remains in WordPress. Updating a profile therefore does not require rewriting the NFC tag or QR code.

## Next development stage

The next build should add:

- proper administrator/user profile management UI;
- NFC tag inventory and UID assignment;
- internal QR generator without an external QR service;
- media uploads;
- configurable fields;
- profile themes;
- analytics;
- installable PWA manifests per app;
- stronger REST authentication and capability checks;
- Apple/Google Wallet integration;
- optional push notifications.

All source files in this package identify **Apostolis Karamichalis** as creator.


## v1.4.0
Mobile, Google service/reviews, eFood, Wolt and BOX fields; icon action buttons; requested Greek captions; hidden TapCard Pro branding; tighter logo/name spacing. Created by Apostolis Karamichalis.

### v1.5.0
- WooCommerce completed-order customer onboarding.
- Automatic customer account/profile creation.
- PWA setup/login email and password setup link.
- Admin Customers section with resend action.
- Upload logo/photo through WordPress Media.
- App/username based automatic link generation.
- Per-application icon selection.
- User-selectable card/button/text colors.
- Tight logo/name spacing.
- Created by Apostolis Karamichalis.

## v1.5.4 FINAL
- Settings app has real create/save flow and is login-protected.
- Classic WordPress Media Library modal for logo, photo and custom application icons.
- Profile name is stored as the TapCard title.
- Visibility checkboxes and drag-and-drop display order.
- Password show/hide on login and activation, plus forgot-password link.
- Standalone Settings PWA manifest scope fixed to /tapcard-settings/.
- Reduced logo-to-name spacing.
- Created by Apostolis Karamichalis.


## v1.5.5 FINAL
- Settings PWA uses authenticated, user-linked profile save flow.
- Custom password recovery and reset remain inside TapCard.
- PWA install UX follows the Branch Manager pattern: native prompt when available, clear Android/iOS fallback otherwise.
- Real WordPress Media Library modal for logo/photo/custom icons.
- Share Card modal with QR, URL copy, QR save, Web Share, Email, SMS and WhatsApp.
- Display visibility/order is preserved.
- Created by Apostolis Karamichalis.

## v1.5.9
Exact Brunch Customer PWA installation flow and two-button activation email; restored Customers profile selector. Created by Apostolis Karamichalis.
