# Troubleshooting .well-known Directory Access on AWS

If you can access the domain association file on localhost but not on your AWS-hosted site, check the following:

## Quick Diagnostic

1. **Upload the diagnostic script** (`check-domain-association-file.php`) to your WordPress root directory
2. **Access it via browser**: `https://yourdomain.com/check-domain-association-file.php`
3. **Review the diagnostic results** to identify the issue

## Common Issues & Solutions

### 1. File Doesn't Exist
**Symptom**: 404 error when accessing the file

**Solution**:
- Re-upload the domain association file via WordPress admin
- Go to: WooCommerce → Settings → Payments → Apple Pay → Domain Association Setup
- Upload the `.txt` file downloaded from Apple Developer

### 2. WordPress Rewrite Rules Blocking Access
**Symptom**: File exists but returns 404, or redirects to homepage

**Solution**:
The plugin now automatically adds `.htaccess` rules when uploading the file. If issues persist:

**Manual Fix**:
1. Edit your `.htaccess` file in the WordPress root directory
2. Add this rule **BEFORE** the `# BEGIN WordPress` section:

```apache
# BEGIN Allow .well-known
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^\.well-known/ - [L]
</IfModule>
# END Allow .well-known
```

3. Save the file
4. Flush rewrite rules: WordPress Admin → Settings → Permalinks → Save Changes

### 3. File Permissions
**Symptom**: 403 Forbidden error

**Solution**:
Check and fix file permissions via SSH/FTP:

```bash
# Set directory permissions
chmod 755 /path/to/wordpress/.well-known

# Set file permissions
chmod 644 /path/to/wordpress/.well-known/apple-developer-merchantid-domain-association
```

### 4. Security Plugin Blocking Access
**Symptom**: 403 Forbidden or blocked request

**Solution**:
- Check your security plugin (Wordfence, iThemes Security, etc.)
- Whitelist `.well-known` directory in security plugin settings
- Add exception for `.well-known/apple-developer-merchantid-domain-association`

### 5. Web Server Configuration (Nginx)
**Symptom**: File exists but not accessible (if using Nginx)

**Solution**:
Add this to your Nginx server block:

```nginx
location /.well-known/ {
    allow all;
    try_files $uri =404;
}
```

### 6. CloudFront/CDN (AWS)
**Symptom**: File accessible but returns wrong content or 404

**Solution**:
- Ensure CloudFront is configured to forward `.well-known` requests to origin
- Add cache behavior for `/.well-known/*` path pattern
- Set cache policy to "Managed-CachingDisabled" or "CachingOptimized" with no query strings
- Invalidate CloudFront cache after uploading the file

### 7. File Path Issues
**Symptom**: File exists but wrong path

**Check**:
- File should be at: `{WordPress Root}/.well-known/apple-developer-merchantid-domain-association`
- NOT in: `wp-content/uploads/.well-known/...`
- Use the diagnostic script to verify the exact path

### 8. SSL/HTTPS Certificate Issues
**Symptom**: Works on HTTP but not HTTPS

**Solution**:
- Ensure your SSL certificate is valid
- Check that HTTPS is properly configured
- Verify the file is accessible via both HTTP and HTTPS

## Testing

After making changes, test the file access:

1. **Direct URL**: `https://yourdomain.com/.well-known/apple-developer-merchantid-domain-association`
2. **Should return**: Plain text content (not HTML, not redirect)
3. **Content-Type**: Should be `text/plain` (check browser developer tools → Network tab)

## AWS-Specific Considerations

### ELB (Elastic Load Balancer)
- Ensure health checks are not interfering
- Check that the load balancer forwards requests correctly

### S3 Static Hosting (if applicable)
- If using S3 for static files, ensure `.well-known` is in the correct bucket
- Set proper CORS headers if needed

### Route 53 / DNS
- Verify DNS is resolving correctly
- Check for any DNS-level blocking

## Still Not Working?

1. **Check server logs**: Review Apache/Nginx error logs
2. **Check WordPress debug log**: Enable `WP_DEBUG` and check `wp-content/debug.log`
3. **Test with curl**: `curl -I https://yourdomain.com/.well-known/apple-developer-merchantid-domain-association`
4. **Check file ownership**: Ensure web server user owns the file
5. **Contact hosting support**: Your AWS hosting provider may have specific restrictions

## Plugin Updates

The plugin now automatically:
- Creates `.htaccess` file in `.well-known` directory
- Adds rewrite rules to main `.htaccess` file
- Sets proper file permissions

If you've already uploaded the file, you may need to:
1. Re-upload the domain association file (this will trigger the automatic fixes)
2. Or manually add the `.htaccess` rules as described above





