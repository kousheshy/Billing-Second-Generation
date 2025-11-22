# Persian RTL Support & Typography - Implementation Guide

**ShowBox Billing Panel v1.9.1**

---

## Overview

This document describes the implementation of comprehensive Persian language support with automatic Right-to-Left (RTL) text direction and professional BYekan+ font typography for all SMS-related features.

---

## Features Implemented

### 1. Automatic RTL Text Direction

The system now automatically detects Persian text and displays it right-to-left, while keeping English text left-to-right.

**How It Works:**
- Uses HTML5 `dir="auto"` attribute
- Browser detects text direction based on Unicode character properties
- Persian characters (Arabic script: U+0600–U+06FF) trigger RTL
- Latin characters trigger LTR
- No JavaScript required - fully native browser feature
- Works in all modern browsers (Chrome, Firefox, Safari, Edge)

**Benefits:**
- ✅ Natural reading direction for Persian users
- ✅ Automatic detection - no manual switching needed
- ✅ Mixed language support (Persian + English in same text)
- ✅ Improved user experience for Persian content
- ✅ Zero performance impact

---

### 2. BYekan+ Professional Typography

Integrated BYekan+ font for beautiful, readable Persian text throughout the SMS system.

**Font Details:**
- **Font Name**: BYekan+ (BYekan Plus)
- **File**: `BYekan+.ttf` (located in project root)
- **Type**: TrueType Font (.ttf)
- **Format**: Unicode-compliant Persian font
- **Loading Strategy**: `font-display: swap`
- **Fallback Chain**: System fonts for English text

**Applied To:**
- SMS message templates (create/edit modal)
- Template preview area
- Template cards display
- Manual SMS compose textarea
- Bulk SMS compose textarea
- SMS history table message column
- All other SMS-related text displays

---

## Technical Implementation

### File Changes

#### 1. dashboard.css

**Lines 1-8: Font Declaration**
```css
/* Persian Font */
@font-face {
    font-family: 'BYekan';
    src: url('BYekan+.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
    font-display: swap;
}
```

**Purpose:**
- Loads BYekan+ font from project root
- `font-display: swap` ensures text is visible immediately while font loads
- Prevents Flash of Invisible Text (FOIT)

---

**Lines 3048-3061: Template Card Message Styling**
```css
.template-card-message {
    font-family: 'BYekan', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 13px;
    line-height: 1.5;
    color: var(--text-secondary);
    white-space: pre-wrap;
    word-break: break-word;
    max-height: 100px;
    overflow: hidden;
    margin: 0;
    position: relative;
    direction: auto;
    text-align: start;
}
```

**Purpose:**
- BYekan font as first choice for Persian text
- Fallback to system fonts for English
- `direction: auto` for automatic RTL/LTR
- `text-align: start` respects text direction

---

**Lines 3097-3106: Global Persian Font Support**
```css
/* Persian Font Support for SMS-related elements */
#template-message,
#template-preview,
#sms-manual-message,
#sms-accounts-message,
.reminder-textarea,
.template-card-message,
.sms-history-message {
    font-family: 'BYekan', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
```

**Purpose:**
- Applies BYekan font to all SMS input/output areas
- Ensures consistent typography across entire SMS system

---

**Lines 651-657: Sortable Header Fix**
```css
table th.sortable {
    cursor: pointer;
    user-select: none;
    transition: all var(--transition-fast);
    position: relative;
    white-space: nowrap;
}
```

**Purpose:**
- `white-space: nowrap` prevents header text and sort icon from wrapping
- Keeps "Full Name ▲" on same line

---

**Lines 663-669: Sort Icon Alignment**
```css
table th.sortable .sort-icon {
    display: inline-block;
    margin-left: 6px;
    opacity: 0.3;
    font-size: 10px;
    vertical-align: middle;
}
```

**Purpose:**
- `vertical-align: middle` aligns icon with header text
- `margin-left: 6px` adds proper spacing
- Icons now display inline instead of below

---

#### 2. dashboard.html

**Line 1427: Template Modal Textarea**
```html
<textarea id="template-message" rows="6" required placeholder="Type your message here..."
          oninput="updateTemplatePreview()" dir="auto"></textarea>
```

**Line 1436: Template Preview**
```html
<div id="template-preview" style="white-space: pre-wrap; color: var(--text-secondary);
     font-size: 14px; font-family: monospace;" dir="auto">
    Enter a message to see preview...
</div>
```

**Line 709: Manual SMS Message**
```html
<textarea id="sms-manual-message" rows="4" class="reminder-textarea" maxlength="500"
          placeholder="Type your SMS message here..." dir="auto"></textarea>
```

**Line 757: Accounts SMS Message**
```html
<textarea id="sms-accounts-message" rows="4" class="reminder-textarea" maxlength="500"
          placeholder="Type your SMS message. Use {name}, {mac}, {expiry_date} for personalization..."
          dir="auto"></textarea>
```

**Purpose:**
- `dir="auto"` enables automatic RTL/LTR detection
- Applied to all user-facing text input areas

---

#### 3. sms-functions.js

**Line 633: Template Card Rendering**
```javascript
<div class="template-card-message" dir="auto">${template.template}</div>
```

**Purpose:**
- Displays saved templates with automatic direction detection
- Works for both Persian and English templates

---

**Line 475: SMS History Table**
```javascript
<td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;"
    title="${log.message}" dir="auto">${log.message}</td>
```

**Purpose:**
- SMS history messages display with correct direction
- Tooltip also respects text direction

---

## Usage Examples

### Example 1: Creating a Persian Template

1. Navigate to **Messaging → SMS Messages**
2. Click **"+ Add Template"**
3. Type Persian text in the message field:
   ```
   {name} عزیز، سرویس شما ۷ روز دیگر منقضی می‌شود.
   ```
4. Text automatically aligns right-to-left
5. BYekan font renders Persian characters beautifully
6. Preview shows exactly how it will appear

### Example 2: Mixed Language Message

1. Type both Persian and English in same message:
   ```
   Hello {name} عزیز، your MAC is {mac}
   ```
2. Text direction follows the first strong directional character
3. If starts with Persian: RTL
4. If starts with English: LTR

### Example 3: SMS History Display

1. Go to **Messaging → SMS Messages → History**
2. View sent messages
3. Persian messages display RTL with BYekan font
4. English messages display LTR with system font
5. Mixed messages follow primary text direction

---

## Browser Compatibility

| Feature | Chrome | Firefox | Safari | Edge |
|---------|--------|---------|--------|------|
| `dir="auto"` | ✅ 26+ | ✅ 17+ | ✅ 6.1+ | ✅ 79+ |
| `@font-face` | ✅ All | ✅ All | ✅ All | ✅ All |
| `font-display: swap` | ✅ 60+ | ✅ 58+ | ✅ 11.1+ | ✅ 79+ |

**Result:** Works in all modern browsers (2018+)

---

## Font Loading Performance

### Strategy: `font-display: swap`

**Timeline:**
1. **0ms - 100ms (Block Period)**: Text invisible, waiting for font
2. **100ms - 3000ms (Swap Period)**: Text visible in fallback font, swaps to BYekan when loaded
3. **3000ms+ (Failure Period)**: Uses fallback font permanently if BYekan fails to load

**Benefits:**
- ✅ Text always visible (no blank screen)
- ✅ Fast perceived load time
- ✅ Graceful degradation if font fails
- ✅ Optimal user experience

---

## Troubleshooting

### Issue: Font Not Loading

**Symptoms:** Persian text displays in system font instead of BYekan

**Causes & Solutions:**

1. **Font file missing or wrong path**
   - Verify `BYekan+.ttf` exists in project root
   - Check console for 404 errors
   - Ensure file permissions allow reading

2. **Browser cache**
   - Hard refresh: `Cmd+Shift+R` (Mac) or `Ctrl+Shift+F5` (Windows)
   - Clear browser cache
   - Try incognito/private mode

3. **File format issue**
   - Verify .ttf file is valid (not corrupted)
   - Try opening font file directly in Font Book (Mac) or Font Viewer (Windows)

4. **CORS or security restrictions**
   - If serving from different domain, ensure CORS headers allow font loading
   - Check browser console for security errors

---

### Issue: Text Direction Not Switching

**Symptoms:** Persian text displays LTR or English displays RTL

**Causes & Solutions:**

1. **Missing `dir="auto"` attribute**
   - Check HTML element has `dir="auto"`
   - Verify not overridden by parent element

2. **Browser doesn't support `dir="auto"`**
   - Update to modern browser version
   - Fallback: Use `dir="rtl"` explicitly for Persian fields

3. **Invisible characters affecting detection**
   - Zero-width characters or formatting marks can confuse direction
   - Clean text input before saving

---

### Issue: Sort Icons Below Headers

**Symptoms:** Sort arrows appear on line below "Full Name" or "Reseller"

**Causes & Solutions:**

1. **CSS not applied**
   - Hard refresh browser
   - Check `white-space: nowrap` is present on `th.sortable`

2. **Column too narrow**
   - Increase column width
   - Icon should have `display: inline-block`

3. **CSS override**
   - Check for conflicting styles in browser inspector
   - Ensure `vertical-align: middle` on `.sort-icon`

---

## Testing Checklist

Use this checklist to verify Persian RTL support is working correctly:

### Template Management
- [ ] Create template with Persian text → displays RTL
- [ ] Create template with English text → displays LTR
- [ ] Template preview shows correct direction
- [ ] Template cards display with BYekan font
- [ ] Template cards show correct text direction

### SMS Sending
- [ ] Manual SMS with Persian text → RTL input
- [ ] Bulk SMS with Persian text → RTL input
- [ ] Mixed language input works correctly
- [ ] Character counter works with Persian

### SMS History
- [ ] History table shows Persian messages RTL
- [ ] History table shows English messages LTR
- [ ] Message tooltips respect direction
- [ ] BYekan font applied to message column

### Table Sorting
- [ ] "Full Name" header has inline sort icon
- [ ] "Reseller" header has inline sort icon
- [ ] Sort icons don't wrap to new line
- [ ] Sort icons change on click (⬍ → ▲ → ▼)

---

## Future Enhancements

Potential improvements for future versions:

1. **Extended Font Support**
   - Add WOFF2 format for better compression
   - Include multiple weights (light, regular, bold)
   - Add italic variant

2. **Language Detection Improvements**
   - Auto-detect language from first characters
   - Provide manual RTL/LTR toggle button
   - Remember user's language preference

3. **Typography Enhancements**
   - Adjust line-height for Persian text specifically
   - Optimize letter-spacing for better readability
   - Add Persian-specific text rendering hints

4. **Accessibility**
   - Add `lang` attribute for screen readers
   - Ensure ARIA labels work with RTL
   - Test with Persian screen readers

5. **Performance**
   - Preload font for faster initial render
   - Subset font to only include needed characters
   - Consider variable font format

---

## File Structure

```
ShowBox Billing Panel/
├── BYekan+.ttf                          # Persian font file
├── dashboard.css                        # Contains @font-face and RTL styles
├── dashboard.html                       # SMS UI with dir="auto" attributes
├── sms-functions.js                     # SMS rendering with RTL support
├── CHANGELOG.md                         # Version history (updated)
├── PERSIAN_RTL_TYPOGRAPHY.md            # This document
└── README.md                            # Main documentation (to be updated)
```

---

## CSS Reference

### Classes with Persian Font Support

| Class/ID | Purpose | File |
|----------|---------|------|
| `#template-message` | Template edit textarea | dashboard.html |
| `#template-preview` | Template preview area | dashboard.html |
| `#sms-manual-message` | Manual SMS textarea | dashboard.html |
| `#sms-accounts-message` | Bulk SMS textarea | dashboard.html |
| `.reminder-textarea` | Generic reminder textareas | dashboard.css |
| `.template-card-message` | Template card display | dashboard.css |
| `.sms-history-message` | SMS history message cell | sms-functions.js |

### RTL-Enabled Elements

All elements with `dir="auto"` attribute:
- Template message textarea
- Template preview div
- Manual SMS textarea
- Bulk SMS textarea
- Template card message divs
- SMS history table cells

---

## Version History

### v1.9.1 (2025-11-23)
- Initial Persian RTL support implementation
- BYekan+ font integration
- Automatic text direction detection
- Sort icon alignment fixes

---

## Support & Maintenance

### Updating the Font

To update to a newer version of BYekan:

1. Download new `BYekan+.ttf` file
2. Replace existing file in project root
3. Hard refresh all browsers to clear cache
4. Test on all SMS-related pages

### Adding Additional Fonts

To add more Persian fonts:

1. Add font file to project root
2. Add @font-face declaration in dashboard.css:
   ```css
   @font-face {
       font-family: 'NewFont';
       src: url('NewFont.ttf') format('truetype');
       font-display: swap;
   }
   ```
3. Update font-family in CSS rules
4. Test thoroughly

---

## Credits

- **Font**: BYekan+ by Persian Type Foundry
- **Implementation**: ShowBox Billing Panel Development Team
- **Version**: 1.9.1
- **Date**: November 23, 2025

---

**End of Documentation**
