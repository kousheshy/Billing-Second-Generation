# PWA Setup for ShowBox Billing Panel

## Overview
The ShowBox Billing Panel is now a Progressive Web App (PWA), allowing users to install it on their devices and use it like a native app.

## Features Added

### ✅ Installability
- Users can install the app on mobile devices (iOS, Android)
- Users can install on desktop (Chrome, Edge, Safari)
- Appears in app drawer and home screen

### ✅ Offline Support
- Service worker caches static assets
- App shell loads even when offline
- Graceful fallback for API requests

### ✅ App-like Experience
- Runs in standalone mode (no browser UI)
- Custom splash screen
- Theme color integration

## Files Added

### 1. `manifest.json`
Defines app metadata:
- App name: "ShowBox Billing Panel"
- Short name: "Billing Panel"
- Theme color: #6366f1
- Icons: 72x72 to 512x512
- Display mode: standalone

### 2. `service-worker.js`
Handles caching and offline functionality:
- Caches app shell (HTML, CSS, JS)
- Network-first for API requests
- Automatic cache updates
- Offline error handling

### 3. PWA Meta Tags
Added to `dashboard.html` and `index.html`:
- Manifest link
- Theme color
- Apple touch icons
- iOS web app capabilities

### 4. Service Worker Registration
Added to `dashboard.js`:
- Automatic registration on page load
- Update detection and prompt
- Install prompt handling

## Icon Setup

### ✅ Icons Already Generated!
All PWA icons have been generated from `video-play-icon-20.png`:

**PWA Icons (icons/ folder):**
- ✅ icon-72x72.png
- ✅ icon-96x96.png
- ✅ icon-128x128.png
- ✅ icon-144x144.png
- ✅ icon-152x152.png
- ✅ icon-192x192.png
- ✅ icon-384x384.png
- ✅ icon-512x512.png

**Favicons (root folder):**
- ✅ favicon-16x16.png
- ✅ favicon-32x32.png
- ✅ favicon.png

See [ICONS-GENERATED.md](ICONS-GENERATED.md) for details.

### Regenerate Icons (Optional)
If you need to regenerate icons from the source image:
```bash
./generate-pwa-icons.sh
```

This will recreate all icons from `video-play-icon-20.png`.

## Testing the PWA

### Desktop (Chrome/Edge)
1. Open the site in Chrome/Edge
2. Look for the install icon (⊕) in the address bar
3. Click it to install
4. App will open in a new window

### iOS (Safari)
1. Open the site in Safari
2. Tap the Share button
3. Select "Add to Home Screen"
4. Confirm installation

### Android (Chrome)
1. Open the site in Chrome
2. Tap the menu (⋮)
3. Select "Add to Home Screen" or "Install App"
4. Confirm installation

## Deployment Checklist

- [x] Generate or customize app icons ✅
- [x] Place icons in `icons/` folder ✅
- [ ] Update `manifest.json` with your domain/URL
- [ ] Update `start_url` in manifest if needed
- [ ] Test on HTTPS (PWA requires HTTPS)
- [ ] Test installation on mobile devices
- [ ] Test offline functionality
- [ ] Verify service worker registration

## HTTPS Requirement

**IMPORTANT**: PWAs require HTTPS to work. Ensure your server has SSL/TLS certificate installed.

For local testing, you can use:
- `localhost` (works without HTTPS)
- Self-signed certificate
- ngrok or similar tunneling service

## Updating the App

When you make changes to the app:

1. Update version in `service-worker.js`:
   ```javascript
   const CACHE_NAME = 'showbox-billing-v1.0.1'; // Increment version
   ```

2. Service worker will automatically detect changes
3. Users will be prompted to reload for updates

## Troubleshooting

### Icons not showing
- Check that icon files exist in `icons/` folder
- Verify icon paths in `manifest.json`
- Clear browser cache and reinstall

### Service worker not registering
- Verify HTTPS is enabled
- Check browser console for errors
- Ensure `service-worker.js` is in root directory

### App not installable
- Verify manifest.json is accessible
- Check that icons are valid PNG files
- Ensure all required manifest fields are present

## Browser Support

- ✅ Chrome (Desktop & Android)
- ✅ Edge (Desktop)
- ✅ Safari (iOS 11.3+)
- ✅ Firefox (Desktop & Android)
- ✅ Samsung Internet
- ⚠️ IE11: Not supported

## Performance Benefits

- **Faster load times**: Cached assets load instantly
- **Reduced bandwidth**: Less data usage after first visit
- **Offline access**: View cached content offline
- **Native feel**: App-like experience

## Security Considerations

- Service workers only work over HTTPS
- All API requests remain secure
- No additional security risks introduced
- Session management unchanged

## Further Customization

### Change App Colors
Edit `manifest.json`:
```json
{
  "theme_color": "#6366f1",
  "background_color": "#0a0e27"
}
```

### Modify Cache Strategy
Edit `service-worker.js`:
- Network-first vs Cache-first
- Cache duration
- Cached resources list

### Custom Install Prompt
Add UI button in dashboard to trigger install:
```javascript
installButton.addEventListener('click', () => {
  if (deferredPrompt) {
    deferredPrompt.prompt();
  }
});
```

## Resources

- [MDN PWA Guide](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps)
- [Web.dev PWA](https://web.dev/progressive-web-apps/)
- [PWA Builder](https://www.pwabuilder.com/)

---

**Version**: 1.0.0
**Last Updated**: 2025-01-21
