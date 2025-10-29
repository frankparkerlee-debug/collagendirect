# Google Search Console Setup Guide

This guide will help you submit your CollagenDirect sitemap to Google Search Console to accelerate indexing and track SEO performance.

## Step 1: Access Google Search Console

1. Go to [Google Search Console](https://search.google.com/search-console)
2. Sign in with your Google account
3. Click "Add Property"

## Step 2: Add Your Website

### Option A: Domain Property (Recommended)
1. Select "Domain" option
2. Enter: `collagendirect.health`
3. Click "Continue"
4. Follow DNS verification instructions (you'll need to add a TXT record to your domain DNS)

### Option B: URL Prefix
1. Select "URL prefix" option
2. Enter: `https://collagendirect.health`
3. Click "Continue"
4. Verify ownership using one of these methods:
   - HTML file upload (upload verification file to your website root)
   - HTML tag (add meta tag to your homepage `<head>`)
   - Google Analytics (if you already have GA installed)
   - Google Tag Manager (if you already have GTM installed)

## Step 3: Submit Your Sitemap

Once verified:

1. In Google Search Console, go to **Sitemaps** (in the left sidebar)
2. Under "Add a new sitemap", enter: `sitemap.xml`
3. Click **Submit**

Your sitemap URL will be: `https://collagendirect.health/sitemap.xml`

## Step 4: Request Indexing for Key Pages

To speed up indexing of your most important pages:

1. Go to **URL Inspection** tool (top search bar)
2. Enter each of these URLs one at a time:
   - `https://collagendirect.health/`
   - `https://collagendirect.health/for-healthcare-professionals/`
   - `https://collagendirect.health/clinical-evidence/`
   - `https://collagendirect.health/products/`
   - `https://collagendirect.health/resources/`
3. Click "Request Indexing" for each URL

## Step 5: Monitor Performance

After 1-2 weeks, check these reports in Google Search Console:

### Performance Report
- Track which keywords are bringing doctors to your site
- See click-through rates for different search queries
- Identify top-performing pages

### Coverage Report
- Verify all pages are indexed
- Fix any indexing errors
- Monitor newly discovered pages

### Enhancements
- Check for mobile usability issues
- Review page experience metrics
- Address any Core Web Vitals issues

## Expected Timeline

- **24-48 hours:** Google discovers your sitemap
- **1-2 weeks:** Main pages start appearing in search results
- **4-8 weeks:** Full SEO impact visible with improved rankings
- **8-12 weeks:** Rich snippets and featured snippets may appear

## Key Metrics to Track

1. **Impressions** - How many times your site appears in search results
2. **Clicks** - How many people click through to your site
3. **CTR (Click-Through Rate)** - Percentage of impressions that result in clicks
4. **Average Position** - Where you rank for various keywords

## Target Keywords to Monitor

Track your rankings for these high-value searches:

- "collagen wound dressing"
- "HCPCS A6210" (and other codes)
- "wound care products for physicians"
- "diabetic ulcer treatment"
- "chronic wound care suppliers"
- "medical grade collagen therapy"

## Troubleshooting

### Sitemap not found
- Verify https://collagendirect.health/sitemap.xml loads in your browser
- Check that the file was deployed to production

### Pages not indexing
- Ensure robots.txt allows crawling: https://collagendirect.health/robots.txt
- Check that meta robots tags are set to "index, follow"
- Wait at least 2 weeks before being concerned

### Need Help?
Contact your web developer or refer to [Google's Search Console documentation](https://support.google.com/webmasters/answer/9128668).

---

## Quick Reference: Your SEO Setup

✅ **Sitemap URL:** `https://collagendirect.health/sitemap.xml`
✅ **Robots.txt:** `https://collagendirect.health/robots.txt`
✅ **Key Pages:**
- Homepage: `/`
- For Professionals: `/for-healthcare-professionals/`
- Clinical Evidence: `/clinical-evidence/`
- Products: `/products/`
- Resources: `/resources/`

All pages include:
- Schema.org structured data
- Open Graph meta tags
- Medical/healthcare keywords
- Mobile-responsive design
- Fast loading times
