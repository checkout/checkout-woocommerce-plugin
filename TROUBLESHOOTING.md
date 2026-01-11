# Troubleshooting Guide

## Table of Contents
1. [AWS .well-known Directory Issues](#aws-well-known-directory-issues)
2. [Flow Disappearing Issues](#flow-disappearing-issues)
3. [Payment Gateway Availability Issues](#payment-gateway-availability-issues)
4. [Deployment Verification](#deployment-verification)
5. [Common Issues & Solutions](#common-issues--solutions)

---

## AWS .well-known Directory Issues

### Quick Fix: Re-upload Plugin (Recommended)

1. **Download the Latest Build**
   - Get the latest plugin zip file
   - Ensure it contains the automatic `.htaccess` rules fix

2. **Upload to AWS Site**
   - Log in to WordPress admin: `https://yourdomain.com/wp-admin`
   - Go to: **Plugins → Add New → Upload Plugin**
   - Click **Choose File** and select the ZIP file
   - Click **Install Now**
   - When asked to replace existing plugin, click **Yes, replace the current version**
   - Click **Activate Plugin**

3. **Re-upload Domain Association File**
   - Go to: **WooCommerce → Settings → Payments → Apple Pay**
   - Scroll to **Domain Association Setup** section
   - Click **Choose File** and select your domain association `.txt` file
   - Click **Upload Domain Association File**
   - The plugin will automatically:
     - Save the file
     - Create `.htaccess` in `.well-known` directory
     - Add rewrite rules to main `.htaccess`

4. **Test Access**
   - Open: `https://yourdomain.com/.well-known/apple-developer-merchantid-domain-association`
   - You should see the file content (not 404)

### Manual Fix (Faster - No Plugin Re-upload)

1. **Access Your WordPress Root Directory**
   - Via FTP/SSH or cPanel File Manager
   - Navigate to WordPress root (where `wp-config.php` is located)

2. **Edit `.htaccess` File**
   - Open `.htaccess` file
   - Find the line: `# BEGIN WordPress`
   - **BEFORE** that line, add this code:

```apache
# BEGIN Allow .well-known
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^\.well-known/ - [L]
</IfModule>
# END Allow .well-known
```

3. **Verify File Exists**
   - Check: `{WordPress Root}/.well-known/apple-developer-merchantid-domain-association`
   - If missing, upload via WordPress admin

4. **Flush Rewrite Rules**
   - Go to: **WordPress Admin → Settings → Permalinks**
   - Click **Save Changes** (don't need to change anything)

5. **Test Access**
   - Open: `https://yourdomain.com/.well-known/apple-developer-merchantid-domain-association`
   - Should see file content (not 404)

### Common Issues & Solutions

#### 1. File Doesn't Exist
**Symptom**: 404 error when accessing the file

**Solution**: Re-upload the domain association file via WordPress admin

#### 2. WordPress Rewrite Rules Blocking Access
**Symptom**: File exists but returns 404, or redirects to homepage

**Solution**: Add `.htaccess` rules as shown in Manual Fix above

#### 3. File Permissions
**Symptom**: 403 Forbidden error

**Solution**:
```bash
chmod 755 /path/to/wordpress/.well-known
chmod 644 /path/to/wordpress/.well-known/apple-developer-merchantid-domain-association
```

#### 4. Security Plugin Blocking Access
**Symptom**: 403 Forbidden or blocked request

**Solution**: Whitelist `.well-known` directory in security plugin settings

#### 5. CloudFront/CDN (AWS)
**Symptom**: File accessible but returns wrong content or 404

**Solution**:
- Ensure CloudFront forwards `.well-known` requests to origin
- Add cache behavior for `/.well-known/*` path pattern
- Set cache policy to "Managed-CachingDisabled"
- Invalidate CloudFront cache after uploading the file

---

## Flow Disappearing Issues

### Problem: Flow Component Disappears After Page Update

**Symptoms:**
- Flow loads initially
- Disappears when user types in checkout fields
- Flow container not found after `updated_checkout` event

**Root Cause:**
WooCommerce's `updated_checkout` replaces the entire checkout HTML, which destroys the Flow container and component.

**Solution:**
The code now includes automatic re-initialization:
- Flow container is recreated after `updated_checkout`
- Flow component is re-mounted if destroyed
- Retry mechanism ensures container is found

**Verification:**
Check browser console for:
- `[FLOW CONTAINER] Script loaded`
- `[FLOW CONTAINER] ✅ Created flow-container id on payment_box div`
- `🔍 DIAGNOSTIC: Flow component was destroyed by updated_checkout, re-initializing...`

---

## Payment Gateway Availability Issues

### Problem: "No Payment Methods Available" Error

**Symptoms:**
- Flow gateway not appearing in checkout
- Error: "Sorry, it seems that there are no available payment methods"
- Works in test environments but not production

**Root Cause:**
Other plugins or themes filter payment gateways at different priorities, removing Flow before it can be added.

**Solution:**
The plugin now uses a dual filter approach:
- **Priority 1**: Adds Flow gateway first (before other filters)
- **Priority 999**: Backup filter (re-adds if removed)

**How to Identify Conflicting Plugins:**
1. Deactivate all plugins except WooCommerce and Checkout.com
2. Test checkout - if it works, a plugin is causing the issue
3. Activate plugins one by one to find the culprit

**Common Culprits:**
- Subscription plugins (WooCommerce Subscriptions)
- Multi-currency plugins
- Country restriction plugins
- Membership plugins
- Custom checkout plugins

**Check Theme:**
1. Switch to default theme (Twenty Twenty-Four)
2. Test checkout - if it works, theme has conflicting filter

---

## Deployment Verification

### Verify JavaScript Files Are Deployed

1. **Check File Directly**
   - Open: `https://yourdomain.com/wp-content/plugins/checkout-com-unified-payments-api/flow-integration/assets/js/payment-session.js`
   - Search for: `🔍 DIAGNOSTIC:` or `initializeFlowIfNeeded`
   - If found, file is deployed

2. **Check Browser Console**
   - Open checkout page
   - Open browser console (F12)
   - Look for: `[FLOW CONTAINER] Script loaded`
   - Look for: `🔍 DIAGNOSTIC:` logs

3. **Check Version String**
   - In browser console, check script URL
   - Should include version: `?ver=5.0.0_beta`
   - If old version, clear cache

### Verify PHP Changes Are Deployed

1. **Check Gateway Availability**
   - Go to: WooCommerce → Settings → Payments
   - Verify "Checkout.com" is listed
   - Check "Checkout Mode" is set to "Flow"

2. **Check Logs**
   - Enable debug logging in plugin settings
   - Look for: `[FLOW DEBUG]` entries
   - Should see gateway availability logs

---

## Common Issues & Solutions

### Issue: Flow Not Loading for Guest Users

**Solution:**
- Ensure email field is filled before Flow initializes
- Check browser console for errors
- Verify cart info is available

### Issue: Syntax Errors in JavaScript

**Solution:**
- Clear browser cache completely
- Check file version in URL
- Verify latest plugin version is installed

### Issue: 422 API Errors

**Solution:**
- Check payment session request data
- Verify `unit_price` includes tax
- Ensure `amount` matches sum of items

### Issue: Duplicate Orders

**Solution:**
- Check for multiple form submissions
- Verify AJAX handlers aren't firing twice
- Review order creation logs

---

## Still Not Working?

1. **Check Server Logs**: Review Apache/Nginx error logs
2. **Check WordPress Debug Log**: Enable `WP_DEBUG` and check `wp-content/debug.log`
3. **Check Plugin Logs**: Enable debug logging in plugin settings
4. **Test with curl**: `curl -I https://yourdomain.com/checkout/`
5. **Contact Support**: Provide logs and error messages

