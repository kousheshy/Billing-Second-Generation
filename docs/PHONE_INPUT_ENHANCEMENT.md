# Phone Number Input Enhancement

**Version:** 1.11.0
**Date:** November 23, 2025
**Status:** Implemented (Local Only)
**Last Updated:** November 23, 2025 (Phone parsing bug fix)

---

## Overview

Enhanced phone number input system with intelligent country code selection, automatic validation, and format correction for Add Account and Edit/Renew Account modals.

---

## Features

### 1. Smart Country Code Selector
- **Default**: Iran (+98) pre-selected
- **Top 10 Countries**: Quick access to most-used country codes
  - 🇮🇷 Iran (+98)
  - 🇺🇸 USA (+1)
  - 🇬🇧 UK (+44)
  - 🇨🇳 China (+86)
  - 🇮🇳 India (+91)
  - 🇯🇵 Japan (+81)
  - 🇩🇪 Germany (+49)
  - 🇫🇷 France (+33)
  - 🇷🇺 Russia (+7)
  - 🇰🇷 South Korea (+82)
  - 🇮🇹 Italy (+39)
- **Custom Option**: Enter any custom country code (e.g., +971 for UAE)

### 2. Automatic Number Formatting
- **Leading Zero Removal**: Automatically removes leading zero if entered
  - User types: `09121234567` → System converts to: `9121234567`
- **Smart Parsing**: When editing existing accounts, automatically splits stored number (+989121234567) into:
  - Country Code: +98
  - Phone Number: 9121234567

### 3. Real-time Validation
- **Iran-Specific Rules**:
  - Must be exactly 10 digits
  - Must start with 9 (mobile numbers)
  - Example: `9121234567` ✅
  - Invalid: `8121234567` ❌ (doesn't start with 9)
  - Invalid: `912123456` ❌ (only 9 digits)

- **International Rules**:
  - Must be between 7-15 digits
  - Only digits allowed (no letters or special characters)

### 4. User-Friendly Interface
- **Placeholder Text**: Gray hint showing correct format (`9121234567`)
- **Helper Text**: "Enter number without leading zero (e.g., 9121234567 for Iran)"
- **Instant Feedback**: Validation error shown immediately when user leaves field
- **Custom Code Input**: Appears only when "Custom" is selected

---

## Implementation Details

### HTML Changes

**Add Account Modal** ([dashboard.html:915-936](dashboard.html#L915-L936))
```html
<div class="form-group">
    <label>Phone Number</label>
    <div class="phone-input-container">
        <select name="country_code" id="add-country-code" class="country-code-select">
            <option value="+98" selected>🇮🇷 +98 (Iran)</option>
            <!-- ... other countries ... -->
            <option value="custom">✏️ Custom</option>
        </select>
        <input type="text" id="add-custom-code" class="custom-code-input"
               placeholder="+XX" style="display: none;" maxlength="5">
        <input type="text" name="phone_number" id="add-phone-number"
               class="phone-number-input" placeholder="9121234567">
    </div>
    <small class="phone-hint">Enter number without leading zero (e.g., 9121234567 for Iran)</small>
</div>
```

**Edit Account Modal** ([dashboard.html:1405-1426](dashboard.html#L1405-L1426))
- Same structure as Add Account modal
- IDs changed to `edit-country-code`, `edit-custom-code`, `edit-phone`

### JavaScript Functions

**Location**: [dashboard.js:720-871](dashboard.js#L720-L871)

#### Key Functions

1. **normalizePhoneNumber(phoneNumber)**
   - Removes all non-digit characters
   - Removes leading zero if present
   - Returns clean digit string

2. **validatePhoneNumber(phoneNumber, countryCode)**
   - Iran: Validates 10 digits starting with 9
   - Other countries: Validates 7-15 digits
   - Returns: `{valid: boolean, error: string}`

3. **getFullPhoneNumber(countryCodeSelect, customCode, phoneNumber)**
   - Combines country code + normalized number
   - Example: `+98` + `9121234567` = `+989121234567`

4. **parsePhoneNumber(fullPhone)** - [dashboard.js:801-838](dashboard.js#L801-L838)
   - Intelligently splits stored number into country code and number
   - Uses known country code list for accurate parsing
   - Fallback logic for unknown country codes
   - Example: `+989122268577` → `{countryCode: '+98', phoneNumber: '9122268577'}`

   **Parsing Algorithm**:
   - Step 1: Check against known country codes (+98, +1, +44, etc.)
   - Step 2: If no match, try 1-digit, 2-digit, 3-digit, 4-digit codes
   - Step 3: Validate remaining part is 7-15 digits
   - Step 4: Fallback to Iran (+98) if parsing fails

   **Known Country Codes**: `+98, +1, +44, +86, +91, +81, +49, +33, +7, +82, +39, +971, +966, +90, +93`

5. **initPhoneInput(countryCodeId, customCodeId, phoneNumberId)**
   - Initializes event listeners
   - Handles country code dropdown changes
   - Auto-removes leading zero on input
   - Validates on blur

### Form Submission Updates

**Add Account** ([dashboard.js:1901-1964](dashboard.js#L1901-L1964))
```javascript
// Validate phone before submission
const validation = validatePhoneNumber(phoneNumber, countryCode);
if (!validation.valid) {
    showAlert(validation.error, 'error');
    return;
}

// Format and set full phone number
const fullPhone = getFullPhoneNumber(countryCodeSelect, customCode, phoneNumber);
formData.set('phone_number', fullPhone);
```

**Edit Account** ([dashboard.js:2539-2663](dashboard.js#L2539-L2663))
- Same validation and formatting logic
- Parses existing phone on modal open ([dashboard.js:2413-2416](dashboard.js#L2413-L2416))

### CSS Styling

**Location**: [dashboard.css:4351-4485](dashboard.css#L4351-L4485)

**Key Classes**:
- `.phone-input-container` - Flexbox layout for inputs
- `.country-code-select` - Country dropdown styling
- `.custom-code-input` - Custom code input (hidden by default)
- `.phone-number-input` - Main phone number input
- `.phone-hint` - Helper text styling

**Features**:
- Responsive design (stacks on mobile)
- Dark mode support
- Hover and focus states
- Smooth transitions

---

## Usage Examples

### Example 1: Add Account with Iranian Number

**User Input**:
1. Country Code: 🇮🇷 +98 (Iran) [default]
2. Phone Number: `09121234567`

**System Processing**:
1. Auto-removes leading zero → `9121234567`
2. Validates: 10 digits ✅, starts with 9 ✅
3. Combines: `+989121234567`
4. Stores in database: `+989121234567`

### Example 2: Add Account with Custom Country Code

**User Input**:
1. Country Code: ✏️ Custom
2. Custom Code Input appears → User enters `+971` (UAE)
3. Phone Number: `501234567`

**System Processing**:
1. Validates custom code has `+` prefix ✅
2. Validates number: 9 digits ✅
3. Combines: `+971501234567`
4. Stores in database: `+971501234567`

### Example 3: Edit Existing Account

**Stored Data**: `+989122268577`

**System Processing on Modal Open**:
1. Calls `parsePhoneNumber('+989122268577')`
2. Parsing algorithm:
   - Checks known codes: Matches `+98` ✅
   - Extracts remainder: `9122268577`
3. Result: `{countryCode: '+98', phoneNumber: '9122268577'}`
4. Selects Iran (+98) in dropdown
5. Fills phone input: `9122268577`

**User Can**:
- Change country code
- Modify phone number
- System validates and re-combines on save

**Note**: Previous bug where `+989122268577` was incorrectly parsed as `+9891` + `22268577` has been fixed.

### Example 4: Validation Errors

**Scenario 1**: Iranian number without 9 prefix
- Input: `8121234567`
- Error: ❌ "Iranian mobile number must start with 9"

**Scenario 2**: Iranian number with wrong length
- Input: `912123456` (9 digits)
- Error: ❌ "Iranian phone number must be 10 digits (e.g., 9121234567)"

**Scenario 3**: Custom code without phone
- Country: ✏️ Custom
- Custom Code: (empty)
- Error: ❌ "Please enter a custom country code"

---

## Testing Instructions

### Test 1: Add Account - Iranian Number
1. Open Add Account modal
2. Default country code should be Iran (+98)
3. Enter phone: `09121234567`
4. Click outside field → Leading zero should auto-remove
5. Submit form
6. **Expected**: Number saved as `+989121234567`

### Test 2: Add Account - Custom Country Code
1. Open Add Account modal
2. Select "✏️ Custom" from dropdown
3. Custom code input should appear
4. Enter `+1` (USA)
5. Enter phone: `2025551234`
6. Submit form
7. **Expected**: Number saved as `+12025551234`

### Test 3: Edit Account - Parse Existing Number
1. Add account with phone `+989121234567`
2. Click Edit on that account
3. **Expected**:
   - Country code dropdown shows Iran (+98)
   - Phone input shows `9121234567` (without country code)

### Test 4: Validation - Iranian Number
1. Open Add Account modal
2. Enter phone: `8121234567` (doesn't start with 9)
3. Click outside field
4. **Expected**: Error alert "Iranian mobile number must start with 9"

### Test 5: Validation - Wrong Length
1. Open Add Account modal
2. Enter phone: `912123456` (9 digits)
3. Submit form
4. **Expected**: Error alert "Iranian phone number must be 10 digits"

### Test 6: Auto Remove Leading Zero
1. Open Add Account modal
2. Type `0` as first character
3. **Expected**: Zero immediately removed, cursor stays in position

### Test 7: Custom Code Validation
1. Select "Custom" from country dropdown
2. Custom input appears
3. Type `971` (without +)
4. **Expected**: Auto-adds `+` → becomes `+971`

---

## Database Storage

### Format
All phone numbers stored in E.164 format:
- `+[country code][subscriber number]`
- Examples:
  - Iran: `+989121234567`
  - USA: `+12025551234`
  - UK: `+447911123456`

### Benefits of E.164 Format
- ✅ Internationally recognized standard
- ✅ Works with SMS APIs (Twilio, etc.)
- ✅ Can be parsed/validated easily
- ✅ Supports international customers

---

## Browser Compatibility

- ✅ Chrome/Edge (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

---

## Accessibility

- ✅ Keyboard navigation (Tab through fields)
- ✅ Screen reader friendly (proper labels)
- ✅ Error messages announced to screen readers
- ✅ Placeholder text for guidance
- ✅ Focus indicators visible

---

## Known Limitations

1. **No Phone Number Formatting Display**
   - Numbers displayed without spaces/dashes
   - Future: Could add display formatting (e.g., `912 123 4567`)

2. **No Real-time Country Detection**
   - System doesn't auto-detect country from number
   - Future: Could implement smart detection

3. **Limited Country List**
   - Only top 10 + custom
   - Not a full world database
   - Intentional design per requirements

---

## Future Enhancements

### Possible Improvements

1. **Phone Number Formatting Display**
   ```javascript
   // Format for display: 912 123 4567
   function formatPhoneDisplay(number, country) {
       // Country-specific formatting rules
   }
   ```

2. **Auto-detect Country from Number**
   ```javascript
   // Detect country code from full number
   function detectCountryCode(fullNumber) {
       if (fullNumber.startsWith('+98')) return '+98';
       // ... other countries
   }
   ```

3. **Click-to-Call Integration**
   - Add tel: links in account listings
   - One-click to call from desktop

4. **SMS Verification**
   - Send verification code to entered number
   - Confirm number is valid before account creation

---

## Files Modified

| File | Lines | Changes |
|------|-------|---------|
| dashboard.html | 915-936 | Add Account modal phone input |
| dashboard.html | 1405-1426 | Edit Account modal phone input |
| dashboard.js | 720-871 | Phone validation & formatting functions |
| dashboard.js | 887-907 | Initialize phone inputs on modal open |
| dashboard.js | 1901-1964 | Add Account form submission with phone validation |
| dashboard.js | 2413-2416 | Parse phone on Edit Account modal open |
| dashboard.js | 2539-2663 | Edit Account form submission with phone validation |
| dashboard.css | 4351-4485 | Phone input styling & responsive design |

---

## Deployment Checklist

When ready for production:

- [ ] Test all validation scenarios
- [ ] Test with existing accounts (parse existing numbers)
- [ ] Test custom country codes
- [ ] Test on mobile devices
- [ ] Test in dark mode
- [ ] Verify database stores in E.164 format
- [ ] Upload modified dashboard.html
- [ ] Upload modified dashboard.js
- [ ] Upload modified dashboard.css
- [ ] Clear browser cache
- [ ] Test with real phone numbers

---

## Bug Fixes

### Phone Parsing Bug (November 23, 2025)

**Issue**: When editing accounts, phone numbers were incorrectly parsed due to greedy regex pattern.

**Symptoms**:
- Stored: `+989122268577`
- Displayed in edit modal: `22268577` (missing first digit `9`) ❌

**Root Cause**: Regex `/^(\+\d{1,4})(\d+)$/` greedily matched up to 4 digits for country code, resulting in `+9891` instead of `+98`.

**Fix**: Replaced regex with intelligent parsing algorithm:
1. Checks known country codes first (+98, +1, +44, etc.)
2. Falls back to shortest-match algorithm for unknown codes
3. Validates remaining digits are valid phone length (7-15 digits)

**Status**: ✅ Fixed
**Details**: See [PHONE_PARSING_BUG_FIX.md](PHONE_PARSING_BUG_FIX.md)

---

## Support Notes

### Common User Questions

**Q: Why can't I enter the leading zero?**
A: For Iranian numbers, we automatically remove it. Just enter the 10 digits starting with 9.

**Q: My country isn't in the list**
A: Select "Custom" and enter your country code (e.g., +971 for UAE).

**Q: What format should I use?**
A: Just enter the digits without spaces or dashes. Example: 9121234567 for Iran.

**Q: Can I save without a phone number?**
A: Yes, phone number is optional. Leave it blank if not needed.

---

**Developer:** Claude & Kambiz
**Repository:** Billing-Second-Generation
**Status:** Ready for Testing (Local Only)
