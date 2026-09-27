# Vivora Healthcare — Website & Business Dashboard

**Better Equipment. Healthier Tomorrows.**

This is the company website and admin dashboard for Vivora Healthcare, a medical equipment supplier. It is written in plain PHP 8 with MySQL and needs no Composer or Node.js, so it runs on standard Hostinger shared hosting.

## What's included

### Public website
- **Home page** with the Vivora branding, product categories, featured equipment and a "Who we serve" section
- **Product catalogue** with category filters, search, sorting and pagination
- **Product pages** with specifications table, brochure download, WhatsApp/Call buttons and a quote form
- **Request a Quote** and **Contact** forms. Submissions are saved as enquiries in the dashboard.
- **Service & Support**: customers raise installation, repair, AMC, calibration or training tickets and get a ticket number
- Floating WhatsApp button, SEO meta tags, `sitemap.php` and `robots.txt`
- Spam protection on every form: CSRF token, honeypot field, time check and per-IP rate limit

The product categories are ECG Machines, ECG Diagnostic Papers, Patient Monitors, Defibrillators, Ultrasound Systems, Surgical Instruments, Medical Equipment, Diagnostic Machines, Surgical Accessories, Healthcare Consumables and Hospital Accessories. You can add more from the dashboard.

### Dashboard (`/admin`)
The dashboard is built on the Duralux admin theme, recoloured to Vivora navy and teal.
- **Secure login**: hashed passwords, session timeout, and a 15-minute lockout after 5 failed attempts
- **Dashboard**: live stats, a 12-month enquiries/service/sales chart, enquiries by category, pipeline, low-stock alerts, service desk and recent activity
- **Products**: add, edit or delete products, with image and PDF brochure upload, price, GST, SKU, brand/model, specifications, featured flag and show/hide price
- **Categories**: add, edit, delete, reorder, and pick an icon for each
- **Inventory**: stock in/out/set with a movement log and stock value. Alerts only fire for products with a minimum stock above 0.
- **Enquiries**: status pipeline (New → Contacted → Quoted → Won/Lost), notes, Call/WhatsApp/Email buttons, and one-click "Save as customer"
- **Quotations**: GST-aware quote builder with discount, amount in words, printable/PDF layout, WhatsApp/email share and duplicate. Accepting a quote marks its enquiry as Won.
- **Customers**: records for hospitals, clinics, diagnostic centres and dealers, with their quote and enquiry history
- **Service requests**: ticketing with priority, assigned engineer, visit date and notes
- **Reports**: sales, enquiries and service reports for any date range
- **Company settings**: phone, WhatsApp, email, address, GSTIN, social links, home-page text, quotation terms, bank details and email notifications
- **CSV export** for Excel, **Activity log**, **My Profile** (change username or password), and dark mode

## Deploy on Hostinger

### 1. Get the code into `public_html`
**Option A — Hostinger Git (recommended, lets you redeploy with one click)**
1. In hPanel go to **Websites → Manage → Advanced → Git**.
2. Repository: `https://github.com/abh0fficial/vivora.git`. Branch: `main`, or whichever branch holds this code. Directory: leave empty so it deploys into `public_html`.
3. Click **Create**, then **Deploy**. `public_html` must be empty the first time.
4. Optional: turn on **Auto Deployment** and add the webhook URL it shows to GitHub → Settings → Webhooks. Every push will then update the site.

**Option B — Upload**
Download the repository as a ZIP from GitHub, upload it in **File Manager → public_html**, and extract it.

### 2. Run the installer
1. Visit `https://your-domain/install.php`.
2. The database name and user are pre-filled: `u734104989_mshealthcares`. Host is `localhost`. Enter your MySQL password.
3. Click **Install**. The installer creates `config.php` on the server, sets up all tables, and adds the 11 categories, 23 starter products and the admin user. After that it locks itself.
4. For extra safety, delete `install.php` afterwards in File Manager.

> 🔒 `config.php`, which holds the database password, is listed in `.gitignore`, so the password is never pushed to GitHub. Keep it that way, because this repository is public.

### 3. Log in and finish setup
- Dashboard: `https://your-domain/admin/`
- Username **`admin`**, password **`admin123`**. Change this right away in **My Profile**.
- Fill in **Company Settings** (phone, WhatsApp, email, address, GSTIN). They appear across the website and on quotations.
- Edit the starter products: add prices, stock, images and brochures.
- Turn on SSL in hPanel, then uncomment the HTTPS redirect lines in `.htaccess`.

## Why not GitHub Pages?
GitHub Pages only serves static HTML. This site needs PHP and MySQL for the login, dashboard and forms. GitHub holds the code, and Hostinger runs it.

## Folder structure
```
index.php, products.php, product.php, service.php, contact.php, about.php   ← public website
admin/            ← dashboard (login protected)
includes/         ← bootstrap, DB, helpers, auth, schema (web access blocked)
partials/         ← website header/footer/product card (web access blocked)
assets/           ← CSS, JS, fonts, logo (Duralux theme recoloured to Vivora brand)
uploads/          ← product images & brochures (script execution disabled)
install.php       ← one-time installer
config.sample.php ← template for config.php
```
