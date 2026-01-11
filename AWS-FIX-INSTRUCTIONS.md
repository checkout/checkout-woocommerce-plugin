# Clear Instructions: Fix .well-known Access on AWS

## Option 1: Re-upload Plugin (Recommended - Gets All Fixes)

### Step 1: Download the Latest Build
1. Go to your local project: `/Users/lalit.swain/Documents/Projects/Woocommerce-Flow-Express-Checkout/build/`
2. Find the latest build: `checkout-com-unified-payments-api-e2e-test-v-20251104-200507.zip`
3. This contains the fix that automatically adds `.htaccess` rules

### Step 2: Upload to AWS Site
1. Log in to your AWS WordPress admin: `https://yourdomain.com/wp-admin`
2. Go to: **Plugins → Add New → Upload Plugin**
3. Click **Choose File** and select the ZIP file
4. Click **Install Now**
5. When asked to replace existing plugin, click **Yes, replace the current version**
6. Click **Activate Plugin**

### Step 3: Re-upload Domain Association File
1. Go to: **WooCommerce → Settings → Payments → Apple Pay**
2. Scroll to **Domain Association Setup** section
3. Click **Choose File** and select your domain association `.txt` file (from Apple Developer)
4. Click **Upload Domain Association File**
5. The plugin will now automatically:
   - Save the file
   - Create `.htaccess` in `.well-known` directory
   - Add rewrite rules to main `.htaccess`

### Step 4: Test Access
1. Open in browser: `https://yourdomain.com/.well-known/apple-developer-merchantid-domain-association`
2. You should see the file content (not 404)

---

## Option 2: Manual Fix (Faster - No Plugin Re-upload)

If you don't want to re-upload the plugin, you can manually fix the `.htaccess` file:

### Step 1: Access Your WordPress Root Directory
Via FTP/SSH or cPanel File Manager, navigate to your WordPress root directory (where `wp-config.php` is located)

### Step 2: Edit `.htaccess` File
1. Open `.htaccess` file
2. Find the line: `# BEGIN WordPress`
3. **BEFORE** that line, add this code:

```apache
# BEGIN Allow .well-known
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^\.well-known/ - [L]
</IfModule>
# END Allow .well-known
```

4. Save the file

### Step 3: Verify File Exists
1. Check that this file exists: `{WordPress Root}/.well-known/apple-developer-merchantid-domain-association`
2. If it doesn't exist, upload it via WordPress admin (WooCommerce → Settings → Payments → Apple Pay → Domain Association Setup)

### Step 4: Flush Rewrite Rules
1. Go to: **WordPress Admin → Settings → Permalinks**
2. Click **Save Changes** (don't need to change anything, just click save)
3. This flushes the rewrite rules

### Step 5: Test Access
1. Open in browser: `https://yourdomain.com/.well-known/apple-developer-merchantid-domain-association`
2. You should see the file content (not 404)

---

## Quick Diagnostic (Optional)

If you want to check what's wrong first:

1. Upload `check-domain-association-file.php` to your WordPress root directory
2. Access it: `https://yourdomain.com/check-domain-association-file.php`
3. It will show you exactly what's wrong and what to fix
4. **Remove this file after troubleshooting** (security)

---

## Which Option Should You Choose?

- **Option 1 (Re-upload plugin)**: If you want the automatic fix and all future uploads will work automatically
- **Option 2 (Manual fix)**: If you want a quick fix right now without re-uploading the plugin

Both will work! Option 1 is better long-term, Option 2 is faster if you just need it working now.





