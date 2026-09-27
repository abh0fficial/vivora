# Vivora Healthcare — Business Dashboard

**Better Equipment. Healthier Tomorrows.**

This is the admin dashboard for Vivora Healthcare, a medical equipment supplier. It is written in plain PHP 8 with MySQL and needs no Composer or Node.js, so it runs on standard Hostinger shared hosting.

## What's included

This is a private business dashboard only. There is no public website: opening your domain takes you straight to the dashboard login. The dashboard is built on the Duralux admin theme, recoloured to Vivora navy and teal.

- **Secure login**: hashed passwords, session timeout, and a 15-minute lockout after 5 failed attempts. Search engines are told not to index the dashboard.
- **Dashboard**: live stats, a 12-month enquiries/service/sales chart, enquiries by category, pipeline, low-stock alerts, service desk and recent activity
- **Products**: add, edit or delete products, with image and PDF brochure upload, price, GST, SKU, brand/model, specifications and active/inactive status
- **Categories**: seeded with ECG Machines, ECG Diagnostic Papers, Patient Monitors, Defibrillators, Ultrasound Systems, Surgical Instruments, Medical Equipment, Diagnostic Machines, Surgical Accessories, Healthcare Consumables and Hospital Accessories. You can add more.
- **Inventory**: stock in/out/set with a movement log and stock value. Alerts only fire for products with a minimum stock above 0.
- **Enquiries**: log enquiries received by phone, WhatsApp, email, walk-in, referral, tender or dealer. Track them through the pipeline (New → Contacted → Quoted → Won/Lost), add notes, use the Call/WhatsApp/Email buttons, and save the contact as a customer with one click.
- **Quotations**: GST-aware quote builder with discount, amount in words, printable/PDF layout, WhatsApp/email share and duplicate. Accepting a quote marks its enquiry as Won.
- **Customers**: records for hospitals, clinics, diagnostic centres and dealers, with their quote and enquiry history
- **Service requests**: installation, repair, AMC, calibration and training tickets, with priority, assigned engineer, visit date and notes
- **Reports**: sales, enquiries and service reports for any date range
- **Company settings**: phone, email, address, GSTIN, quotation terms and bank details (printed on quotations)
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
- Dashboard: `https://your-domain/`, which redirects to `/admin/`
- Username **`admin`**, password **`admin123`**. Change this right away in **My Profile**.
- Fill in **Company Settings** (phone, email, address, GSTIN, bank details). They are printed on your quotations.
- Edit the starter products: add prices, stock, images and brochures.
- Turn on SSL in hPanel, then uncomment the HTTPS redirect lines in `.htaccess`.

## Why not GitHub Pages?
GitHub Pages only serves static HTML. The dashboard needs PHP and MySQL. GitHub holds the code, and Hostinger runs it.

## Folder structure
```
index.php         ← redirects to the dashboard
admin/            ← dashboard pages (login protected)
includes/         ← bootstrap, DB, helpers, auth, schema (web access blocked)
assets/           ← CSS, JS, fonts, logo (Duralux theme recoloured to Vivora brand)
uploads/          ← product images & brochures (script execution disabled)
install.php       ← one-time installer
config.sample.php ← template for config.php
```
