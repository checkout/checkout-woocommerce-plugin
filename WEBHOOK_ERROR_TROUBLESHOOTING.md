# Webhook Configuration Error - Troubleshooting Guide

## Error Message
```
⚠ Webhook is not configured with the current site or there is some issue with connection, Please check logs or try again.
```

## What This Error Means

This error appears when the webhook check cannot find any webhooks registered in your Checkout.com account that match your current site's webhook URL.

## Common Causes & Solutions

### 1. **No Webhook Registered**
**Problem:** No webhook has been registered for your site URL in Checkout.com.

**Solution:**
- Click the "Register Webhook" button in the Checkout.com settings page
- This will automatically create a webhook for your site URL

### 2. **API Connection Issues**
**Problem:** The plugin cannot connect to Checkout.com API to retrieve webhook list.

**Check:**
- Verify your **Secret Key** is correctly entered in WooCommerce → Settings → Checkout.com
- Ensure your **Environment** (Sandbox/Live) matches your API key type
- Check that your server can make outbound HTTPS connections to `api.checkout.com` or `api.sandbox.checkout.com`
- Verify firewall/security plugins aren't blocking API requests

**Solution:**
- Double-check your API credentials
- Test API connectivity from your server
- Check server error logs for connection errors

### 3. **Invalid API Credentials**
**Problem:** The secret key is incorrect, expired, or doesn't match the environment.

**Solution:**
- Verify your secret key in the Checkout.com dashboard
- Ensure you're using the correct key for Sandbox vs Live environment
- Regenerate keys if needed from Checkout.com dashboard

### 4. **Account Type Mismatch**
**Problem:** Your account type (ABC vs NAS) might be incorrectly detected.

**Check:**
- ABC accounts: Use `/webhooks` endpoint
- NAS accounts: Use `/workflows` endpoint

**Solution:**
- The plugin auto-detects account type based on your API key format
- If detection fails, check your API key format matches your account type

### 5. **URL Mismatch**
**Problem:** The webhook URL registered in Checkout.com doesn't exactly match your site URL.

**Check:**
- Your site URL might have changed (domain, protocol, www/non-www)
- The webhook might be registered with a different URL format

**Solution:**
- Check registered webhooks in Checkout.com dashboard
- Delete old webhooks and register a new one
- Ensure your WordPress site URL matches the registered webhook URL

### 6. **Settings Not Saved**
**Problem:** Gateway settings might not be properly saved.

**Solution:**
- Go to WooCommerce → Settings → Checkout.com
- Verify all settings are saved
- Try saving settings again

## How to Enable Debug Logging

To get more detailed error information:

1. Go to **WooCommerce → Settings → Checkout.com**
2. Find the **"Gateway Responses"** setting
3. Enable it (set to "Yes")
4. Check your WordPress debug log (usually in `wp-content/debug.log`)
5. Run the webhook check again
6. Review the log for specific error messages

## Manual Webhook Registration

If automatic registration fails, you can manually register a webhook:

### For ABC Accounts:
1. Log into Checkout.com Dashboard
2. Go to Settings → Webhooks
3. Click "Add Webhook"
4. Enter your webhook URL: `https://your-site.com/?wc-api=wc_checkoutcom_webhook`
5. Select all payment events
6. Save

### For NAS Accounts:
1. Log into Checkout.com Dashboard
2. Go to Settings → Workflows
3. Create a new workflow
4. Add webhook action with your URL: `https://your-site.com/?wc-api=wc_checkoutcom_webhook`
5. Configure events
6. Save

## Getting Your Webhook URL

Your webhook URL is automatically generated as:
```
https://your-site.com/?wc-api=wc_checkoutcom_webhook
```

You can verify this URL in the Checkout.com settings page.

## Still Having Issues?

If you've tried all the above and still see the error:

1. **Enable debug logging** (see above)
2. **Check WordPress debug log** for specific error messages
3. **Verify API connectivity** by testing a simple API call
4. **Contact Checkout.com support** with:
   - Your account type (ABC/NAS)
   - Environment (Sandbox/Live)
   - Error messages from logs
   - Your webhook URL

## Related Files

- `includes/settings/class-wc-checkoutcom-webhook.php` - Webhook management
- `includes/settings/class-wc-checkoutcom-workflows.php` - Workflow management (NAS accounts)
- `includes/settings/class-wc-checkoutcom-cards-settings.php` - Settings page
