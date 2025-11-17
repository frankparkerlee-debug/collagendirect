# Google Maps API Setup Guide

This application uses Google Maps Places API for address autocomplete and standardization.

## Setup Steps

### 1. Create a Google Cloud Project
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable billing for the project (required for API usage)

### 2. Enable the Places API
1. In the Google Cloud Console, go to "APIs & Services" > "Library"
2. Search for "Places API"
3. Click "Enable"

### 3. Create an API Key
1. Go to "APIs & Services" > "Credentials"
2. Click "Create Credentials" > "API Key"
3. Copy the generated API key

### 4. Restrict the API Key (Recommended for Security)
1. Click on the newly created API key to edit it
2. Under "Application restrictions":
   - Select "HTTP referrers (web sites)"
   - Add your domain: `https://collagendirect.health/*`
   - For development: `http://localhost/*`
3. Under "API restrictions":
   - Select "Restrict key"
   - Check only "Places API"
4. Click "Save"

### 5. Set the Environment Variable

#### For Render.com deployment:
1. Go to your Render dashboard
2. Select your web service
3. Go to "Environment" tab
4. Add a new environment variable:
   - Key: `GOOGLE_PLACES_API_KEY`
   - Value: `[your-api-key-here]`
5. Click "Save Changes"
6. Your service will automatically redeploy

#### For local development:
Add to your environment or `.env` file:
```bash
export GOOGLE_PLACES_API_KEY="your-api-key-here"
```

## Pricing

Google Maps Platform offers a generous free tier:
- **$200 free credit per month**
- Places Autocomplete: $2.83 per 1000 requests (first $200 free)
- With $200/month free credit = ~70,000 free autocomplete requests per month

For most small to medium applications, you'll stay within the free tier.

## Testing

After setting up the API key:
1. Go to `/admin/users.php`
2. Try creating a new practice or physician
3. Start typing an address in the "Address" field
4. You should see Google's address suggestions appear
5. Select an address from the dropdown
6. City, State, and Zip should auto-populate

## Troubleshooting

### "InvalidKeyMapError" in browser console
- The API key is invalid or not set
- Check that `GOOGLE_PLACES_API_KEY` environment variable is set correctly
- Verify the API key in Google Cloud Console

### No autocomplete suggestions appearing
- Places API might not be enabled in Google Cloud Console
- Check browser console for errors
- Verify domain restrictions on the API key

### "This API project is not authorized to use this API"
- Places API is not enabled for your project
- Go to Google Cloud Console > APIs & Services > Library
- Search for "Places API" and enable it

## Security Best Practices

1. **Never commit API keys to version control**
2. **Always use environment variables**
3. **Restrict API keys** to specific domains and APIs
4. **Monitor usage** in Google Cloud Console to detect abuse
5. **Set up billing alerts** to avoid unexpected charges
