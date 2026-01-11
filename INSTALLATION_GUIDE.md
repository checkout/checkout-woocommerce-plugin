# Checkout.com Flow Integration - Installation & Upgrade Guide

## Version: 5.0.0-beta (E2E Ready)

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Prerequisites - Merchant Onboarding](#prerequisites---merchant-onboarding)
3. [Upgrading from Existing Plugin](#upgrading-from-existing-plugin)
4. [Quick Start Configuration](#quick-start-configuration)
5. [Detailed Configuration](#detailed-configuration)
6. [Testing Your Setup](#testing-your-setup)
7. [Troubleshooting](#troubleshooting)

---

## Overview

This guide will help you upgrade from the existing Checkout.com WooCommerce plugin to the new **Flow Integration** version. 

### ✅ Important: No Data Migration Needed

**Your existing saved cards will continue to work without any migration!** The new Flow gateway is fully compatible with saved payment methods from the classic Cards gateway. Customers will see all their previously saved cards when they checkout.

---

## Prerequisites - Merchant Onboarding

### Step 1: Internal Team Onboarding (Required Before Installation)

Before installing the plugin, the merchant must be onboarded by the Checkout.com internal team. Please provide:

#### Required Information:
1. **Entity Details:**
   - Business legal name
   - Business registration number
   - Business address
   - Tax identification number
   - Contact details

2. **Payment Methods List:**
   - Which payment methods the merchant wants to enable
   - Examples: Cards, Apple Pay, Google Pay, Klarna, PayPal, etc.
   - Specific countries/currencies needed

3. **Processing Details:**
   - Expected monthly volume
   - Average transaction value
   - Business category/MCC code

#### Onboarding Output:
Once onboarded, you will receive:
- ✅ **Sandbox API Keys** (Public Key & Secret Key)
- ✅ **Production API Keys** (Public Key & Secret Key)
- ✅ **Enabled Payment Methods**
- ✅ **3DS Configuration**

⚠️ **Do not proceed with installation until onboarding is complete.**

---

## Upgrading from Existing Plugin

### Step 1: Backup Your Site
```bash
# Backup your WordPress database
# Backup your wp-content/plugins directory
```

### Step 2: Download the Latest Version
- Download: `checkout-com-flow-v5.0.0-beta-e2e-ready.zip`
- Or clone from: `feature/flow-integration-v5.0.0-beta` branch

### Step 3: Install the Plugin

#### Option A: Via WordPress Admin (Recommended)
1. Go to **WordPress Admin → Plugins → Add New**
2. Click **Upload Plugin**
3. Choose `checkout-com-flow-v5.0.0-beta-e2e-ready.zip`
4. Click **Install Now**
5. When prompted about existing plugin:
   - Select **Replace existing plugin**
   - Click **Replace Plugin File**
6. Click **Activate Plugin**

#### Option B: Via FTP/SSH
1. Deactivate the existing plugin (don't delete)
2. Upload new plugin files to `wp-content/plugins/checkout-com-unified-payments-api/`
3. Reactivate the plugin

### Step 4: Verify Existing Settings
Your previous settings will be preserved:
- ✅ API Keys remain configured
- ✅ Payment methods stay enabled
- ✅ All gateway settings are retained
- ✅ **Saved customer cards are still available**

---

## Quick Start Configuration

### Overview
For a quick start, configure these essential settings. You can fine-tune other options later.

### 1. Core Settings
Navigate to: **WooCommerce → Settings → Payments → Checkout.com**

#### Essential Configuration:
```
Environment: Sandbox (for testing) / Production (for live)
Public Key: [Your Public Key from onboarding]
Secret Key: [Your Secret Key from onboarding]
Checkout Mode: Flow ⭐ (Select this for new Flow integration)
```

### 2. Card Settings
Navigate to: **Card Settings** tab

#### Quick Start Settings:
```
✅ Use 3D Secure: Enable
   - Provides strong customer authentication (SCA compliant)
   
✅ Enable Save Cards: Yes
   - Allows customers to save cards for future purchases
   - No migration needed - old saved cards work automatically!
```

### 3. Flow Settings
Navigate to: **Flow Settings** tab

#### Quick Start Settings:
```
Saved Payment Display Order: Saved Cards First
   - Options: "Saved Cards First" or "New Payment First"
   - Recommended: "Saved Cards First" for returning customers
   
✅ Enable Payment Methods: Select the methods you want to offer
   - Cards (Visa, Mastercard, Amex, etc.) ⭐ Essential
   - Apple Pay (if onboarded)
   - Google Pay (if onboarded)
   - Alternative Payment Methods (Klarna, PayPal, etc.)
   - Only enable methods you've been onboarded for!
```

### 4. Test the Checkout
1. Add a product to cart
2. Go to checkout
3. You should see:
   - ✅ Flow payment component loading
   - ✅ Saved cards displayed (if customer has any)
   - ✅ Option to save new cards
   - ✅ Smooth payment experience

🎉 **Your quick start setup is complete!**

---

## Detailed Configuration

### Core Settings (Advanced)

#### Account Settings
```
Account Type: NAS (Default for most merchants)
Environment: 
  - Sandbox: For testing with test cards
  - Production: For real transactions
  
Region: 
  - Automatic (Default)
  - EU
  - US
  - Custom URL
```

#### API Credentials
```
Public Key: pk_sbox_... (Sandbox) or pk_... (Production)
Secret Key: sk_sbox_... (Sandbox) or sk_... (Production)

⚠️ Keep secret keys secure - never share or commit to version control
```

#### Fallback Account (Optional)
```
Enable Fallback Account: No (Default)
  - Use only if you have a backup Checkout.com account
  - Provides redundancy for high-volume merchants
```

---

### Card Settings (Detailed)

#### 3D Secure Configuration
```
Use 3D Secure: Enable ⭐ Recommended for fraud protection

Attempt Non-3D: Disabled (Default)
  - Only enable if specifically configured with Checkout.com
  - May increase fraud risk

3DS Downgrading: Disabled (Default)
  - Advanced feature for specific use cases
  - Consult with Checkout.com before enabling
```

#### Payment Capture
```
Payment Action: 
  - Authorize and Capture (Default) - Immediate payment
  - Authorize Only - Manual capture later
  
Auto-capture Time: 0 (Immediate) or hours to delay capture
```

#### Card Saving
```
Enable Save Cards: Yes ⭐
  - Customers can save cards for future purchases
  - Increases conversion on repeat orders
  - No migration needed for existing saved cards!

Card Verification (CVC): 
  - Required on Saved Cards: Yes (Recommended for security)
  - This adds an extra verification step for saved cards
```

#### Order Status Mapping
```
Authorized Status: On Hold (Default)
  - Order status when payment is authorized but not captured
  
Captured Status: Processing (Default)
  - Order status when payment is captured
  
Flagged Status: Flagged (Default)
  - Order status when payment is flagged by risk system
```

---

### Flow Settings (Detailed)

#### UI/UX Configuration
```
Saved Payment Display Order:
  - Saved Cards First: Shows saved cards at top (Recommended for returning customers)
  - New Payment First: Shows new payment form first (Better for first-time buyers)

Flow Component Theme:
  - Light (Default)
  - Dark
  - Custom (Advanced CSS customization)
```

#### Payment Methods Selection
Enable the payment methods you've been onboarded for:

**Card Networks:**
- ✅ Visa
- ✅ Mastercard
- ✅ American Express
- ✅ Discover
- ✅ Diners Club
- ✅ JCB

**Digital Wallets:**
- ✅ Apple Pay (Requires domain verification)
- ✅ Google Pay

**Alternative Payment Methods:**
- ✅ Klarna (if onboarded)
- ✅ PayPal (if onboarded)
- ✅ iDEAL (Netherlands)
- ✅ Bancontact (Belgium)
- ✅ Giropay (Germany)
- ✅ SEPA Direct Debit
- ✅ Sofort
- ✅ And many more...

⚠️ **Important:** Only enable payment methods you've been onboarded for. Enabling methods you're not set up for will result in payment failures.

#### Advanced Flow Settings
```
Debug Logging: Disabled (Production) / Enabled (Development)
  - When enabled, logs detailed information to browser console
  - ⚠️ Disable in production to reduce console output
  - Always enabled logs: Errors, Warnings, Webhooks, 3DS, Version info

Flow Container Styling:
  - Auto (Default) - Matches your theme
  - Custom CSS - Advanced customization
```

---

### Webhook Configuration (Critical for Production)

#### Step 1: Configure Webhook URL in Checkout.com Hub
1. Log into Checkout.com Hub
2. Go to **Settings → Webhooks**
3. Add webhook URL: `https://yourdomain.com/?wc-api=wc_checkoutcom_webhook`
4. Select events to subscribe:
   - ✅ `payment_approved`
   - ✅ `payment_captured`
   - ✅ `payment_declined`
   - ✅ `payment_refunded`
   - ✅ `payment_voided`

#### Step 2: Test Webhook Delivery
```
1. Make a test payment in sandbox
2. Check WooCommerce logs for webhook events
3. Verify order status updates correctly
```

---

### Payment Method Labels (Optional Customization)

You can customize how payment method names appear on the checkout page:

```
Navigate to: Card Settings → Payment Method Labels

Default: "Credit/Debit Card"
Custom Examples:
  - "Pay with Card"
  - "Card Payment"
  - "Credit Card, Debit Card"
  - Or leave blank to use "Checkout.com"
```

---

## Testing Your Setup

### Test Cards (Sandbox Environment Only)

#### Successful Payments:
```
Card Number: 4242 4242 4242 4242
Expiry: Any future date (e.g., 12/30)
CVV: Any 3 digits (e.g., 100)
```

#### 3DS Authentication:
```
Card Number: 4242 4242 4242 4242
When prompted for 3DS, use password: Checkout1!
```

#### Declined Payment:
```
Card Number: 4000 0000 0000 0002
```

### Testing Checklist:

#### 1. New Card Payment
- [ ] Card input appears correctly
- [ ] 3DS authentication works (if enabled)
- [ ] Payment processes successfully
- [ ] Order status updates to "Processing"
- [ ] Customer receives order confirmation email

#### 2. Saved Card Payment
- [ ] Existing saved cards appear in list
- [ ] Can select saved card
- [ ] CVC verification works (if required)
- [ ] Payment processes successfully
- [ ] No errors in console

#### 3. Card Saving
- [ ] "Save card for future purchases" checkbox appears
- [ ] Card saves after successful payment
- [ ] Saved card appears on next checkout
- [ ] Card saves correctly with 3DS authentication

#### 4. Alternative Payment Methods (if enabled)
- [ ] Apple Pay button appears (Safari on Apple device)
- [ ] Google Pay button appears (Chrome/supported browsers)
- [ ] APM buttons appear for enabled methods
- [ ] APM payments complete successfully

#### 5. Webhook Processing
- [ ] Check WooCommerce logs for webhook events
- [ ] Order statuses update via webhooks
- [ ] No "Order not found" errors
- [ ] Custom order numbers work (if using Sequential Order Numbers plugin)

---

## Troubleshooting

### Issue: "Flow component not loading"

**Solution:**
1. Check browser console for errors
2. Verify API keys are correct
3. Ensure "Checkout Mode" is set to "Flow"
4. Clear browser cache and cookies
5. Check that your domain is whitelisted in Checkout.com Hub

---

### Issue: "Saved cards not showing"

**Solution:**
1. ✅ **No migration needed** - this should work automatically
2. Verify "Enable Save Cards" is set to "Yes"
3. Check that customer is logged in
4. Look for saved cards in: WooCommerce → Customers → [Customer] → Payment Methods
5. Check browser console for errors

---

### Issue: "Payment declined - Invalid API key"

**Solution:**
1. Verify you're using correct keys for environment (Sandbox vs Production)
2. Check for extra spaces in API key fields
3. Confirm keys are active in Checkout.com Hub
4. Try regenerating keys in Hub

---

### Issue: "3DS authentication not working"

**Solution:**
1. Verify "Use 3D Secure" is enabled
2. In sandbox, use test card: 4242 4242 4242 4242
3. Use 3DS password: Checkout1!
4. Check that redirect URLs are configured correctly
5. Ensure SSL certificate is valid on your site

---

### Issue: "Webhooks failing with 'Order not found'"

**Solution:**
1. ✅ This is fixed in v5.0.0-beta!
2. Update to latest version: `checkout-com-flow-v5.0.0-beta-e2e-ready.zip`
3. Webhook now supports custom order numbers (Sequential Order Numbers plugin)
4. Check webhook URL is correct: `https://yourdomain.com/?wc-api=wc_checkoutcom_webhook`
5. Verify order meta `_cko_order_reference` is being saved

---

### Issue: "Payment methods not appearing"

**Solution:**
1. Verify payment methods are enabled in Flow Settings
2. Confirm you've been onboarded for those payment methods
3. Check currency is supported for the payment method
4. For Apple Pay/Google Pay:
   - Ensure SSL is enabled (HTTPS required)
   - Domain must be verified in Checkout.com Hub
   - Test on supported devices/browsers

---

### Issue: "Order stuck in 'Pending Payment' status"

**Solution:**
1. Check webhook configuration in Checkout.com Hub
2. Verify webhook URL is accessible (not blocked by firewall)
3. Look in WooCommerce logs for webhook errors
4. Check payment status in Checkout.com Hub dashboard
5. Manually update order if payment was successful in Hub

---

## Getting Help

### Support Resources:

📧 **Email Support:** support@checkout.com

📚 **Documentation:** https://docs.checkout.com/

💬 **Developer Community:** https://github.com/checkout/checkout-woocommerce-plugin

🔍 **Logs Location:**
- WordPress: `wp-content/uploads/wc-logs/`
- Look for files: `checkout-com-*.log`

### Before Contacting Support:

Please have ready:
1. Plugin version: `5.0.0-beta (E2E Ready)`
2. WordPress version
3. WooCommerce version
4. PHP version
5. Error messages from logs
6. Steps to reproduce the issue
7. Browser console errors (if applicable)

---

## Feature Highlights - What's New in Flow

### ✨ Enhanced User Experience
- Modern, responsive payment UI
- Better mobile experience
- Smooth animations and loading states
- Improved error handling

### 🔒 Security & Compliance
- PCI DSS Level 1 compliant
- Strong Customer Authentication (SCA/3DS2)
- Tokenized card storage
- Fraud detection integration

### 💳 Saved Cards Made Easy
- ✅ **No migration needed** from classic Cards gateway
- Automatic display of saved cards
- CVC verification for added security
- Clean, organized card management

### 🌐 More Payment Options
- Card payments (all major networks)
- Digital wallets (Apple Pay, Google Pay)
- Alternative payment methods (Klarna, PayPal, etc.)
- Local payment methods by region

### 🛠️ Developer-Friendly
- Clean, maintainable code
- Detailed logging (production-safe)
- Webhook reliability improvements
- Custom order number support

---

## Production Checklist

Before going live, ensure:

### Security
- [ ] SSL certificate is valid and active (HTTPS)
- [ ] Production API keys are configured (not sandbox)
- [ ] Debug logging is disabled
- [ ] Secret keys are not exposed in code/logs

### Configuration
- [ ] Environment set to "Production"
- [ ] Correct payment methods enabled
- [ ] 3D Secure is enabled (recommended)
- [ ] Order status mapping configured
- [ ] Webhook URL configured in Checkout.com Hub

### Testing
- [ ] Successful payment flow tested
- [ ] 3DS authentication tested
- [ ] Saved card payment tested
- [ ] Card saving tested
- [ ] Refund process tested
- [ ] Webhook delivery verified

### Monitoring
- [ ] WooCommerce logging enabled
- [ ] Webhook events being received
- [ ] Order status updates working
- [ ] Customer notifications sending
- [ ] Payment confirmation emails working

---

## Rollback Procedure (If Needed)

If you need to rollback to the previous version:

### Step 1: Deactivate Current Plugin
```
WordPress Admin → Plugins → Checkout.com → Deactivate
```

### Step 2: Restore Previous Version
```
1. Upload previous plugin version
2. Or restore from backup
```

### Step 3: Reactivate
```
Activate the restored plugin version
```

### Step 4: Verify
```
1. Check settings are intact
2. Test a payment
3. Verify saved cards still work
```

⚠️ **Note:** Your settings and saved cards will remain intact during rollback.

---

## Version Information

**Current Version:** 5.0.0-beta (E2E Ready)  
**Release Date:** October 14, 2025  
**Branch:** `feature/flow-integration-v5.0.0-beta`  
**Package:** `checkout-com-flow-v5.0.0-beta-e2e-ready.zip`

### Recent Changes:
- ✅ Removed legacy "Show Saved Payment Methods" toggle button
- ✅ Fixed 3DS return path for immediate order meta saving
- ✅ Enhanced webhook order lookup for custom order numbers
- ✅ Implemented production-ready logging strategy
- ✅ Added virtual product support
- ✅ Fixed infinite recursion in debug logger
- ✅ Improved UI/UX consistency

---

## Appendix: Advanced Topics

### Custom CSS Styling

Add custom styling to Flow component:
```css
/* In your theme's style.css or custom CSS */
#flow-container {
    /* Your custom styles */
}
```

### API Integration

For advanced integrations, refer to:
- [Checkout.com API Documentation](https://api-reference.checkout.com/)
- Plugin hooks and filters in code

### Multi-Currency Support

Flow supports multi-currency:
1. Install WooCommerce Multi-Currency plugin
2. Configure currencies
3. Flow will automatically adapt

---

**End of Installation Guide**

For questions or issues not covered here, contact Checkout.com support.

