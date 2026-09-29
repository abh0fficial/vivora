# Vivora Healthcare — Business Dashboard

**Better Equipment. Healthier Tomorrows.**

This is the admin dashboard for Vivora Healthcare, a medical equipment supplier. It is written in plain PHP 8 with MySQL and needs no Composer or Node.js, so it runs on standard Hostinger shared hosting.

## What's included

This is a private dashboard for your own day-to-day office work: GST billing, stock, CRM and service. There is no public website, and everything is entered by hand. It works on desktop and mobile. The dashboard is built on the Duralux admin theme, recoloured to Vivora navy and teal.

### Billing (GST)
- **Tax invoices**
  - CGST + SGST for customers in your own state, IGST for other states. The customer's state is detected from their GSTIN, or you pick the place of supply.
  - HSN/SAC codes, units, a flat discount spread across lines, and round-off to the nearest rupee.
  - Invoice numbers restart each financial year: `VH-INV-2026-27/0001`.
  - Stock is deducted automatically, and editing, cancelling or deleting the invoice puts it back.
  - The printable tax invoice shows both GSTINs, a per-HSN tax breakdown, amount in words, bank details, UPI ID and signature, with a PAID / CANCELLED stamp.
  - Share on WhatsApp or by email, duplicate, cancel, delete (administrators only).
- **Convert quotation → invoice** in one click.
- **Payments received:** full or partial, by cash, UPI, bank transfer, cheque or card. You can record a payment against an invoice or as an advance, and each one gets a printable receipt (`VH-RCPT-…`).
- **Customer ledger / statement of account:** opening balance, invoices, payments and running balance, printable.
- **Outstanding and overdue tracking**, with overdue alerts in the notification bell.

### Dashboard (like a mobile billing app)
- **Tiles:** To Collect, To Pay, Stock Value, This week's sale, Total Balance (Cash + Bank), and a Reports shortcut.
- **Transactions feed:** switch between this and the previous financial year; Send Receipt / Share goes out over WhatsApp.
- **On phones:** Received Payment, a **+** quick menu and **+ Bill / Invoice** buttons, plus a bottom bar with Dashboard · Parties · Items · Reports · More.

### Cash & Bank
- Cash in hand plus any number of bank accounts, each with an opening balance.
- Every payment and expense is linked to an account. Cash picks your cash account, while UPI, bank and cheque pick your default bank account; you can change it on each form.
- Add money, withdraw money, or transfer between accounts; each account has a statement with a running balance.

### Parties
- Customers and suppliers in one list, showing **To collect** / **To pay** and advances.
- Statement / ledger for both customers and suppliers, plus WhatsApp payment reminders.

### Purchases & expenses
- **Suppliers**, with how much you've bought from each and how much you still owe.
- **Purchase bills**
  - Stock goes up automatically and the product's cost price is updated.
  - GST on purchases is tracked as input tax credit.
  - Record full or partial supplier payments.
- **Expenses** by category (rent, salaries, transport, …), with a summary by category.

### Reports (all exportable to CSV for your CA)
One Reports page lists them all:
- **Popular:** Bill-wise profit, Sales summary, Daybook, Profit & loss, Party statement, Stock summary, Balance sheet, Cash & bank (all payments)
- **More:** Party reports, Item reports, GST reports (GSTR-3B summary, **GSTR-1** B2B / B2C, HSN summary), Transaction reports (filter by type and party)

- Sales register
- GST summary: output tax by rate, B2B/B2C split, input credit and net GST payable
- HSN summary
- Purchase register
- Profit & loss: sales − cost of goods − expenses
- Receivables ageing (0–30 / 31–60 / 61–90 / 90+ days) and supplier payables
- Day book of every transaction
- Business reports: enquiries, quotations and service

### Stock, CRM & service
- **Products and categories:** HSN code, cost price, selling price, GST, stock, images, PDF brochures
- **Inventory:** stock in/out with a full movement log and low-stock alerts
- **Enquiries:** log them from phone, WhatsApp, email or walk-in and track them New → Won
- **Quotations** with print, WhatsApp share and duplicate
- **Customers**, with billed and outstanding amounts
- **Service requests:** installation, repair, AMC, calibration and training tickets

### Administration
- **Users & roles**
  - **Administrator:** full access.
  - **Staff:** day-to-day work, but no settings, users, backup or invoice deletion.
- **Backup:** one-click download of the whole database as an `.sql` file. Restore it in phpMyAdmin.
- **System Check:** every table with its row count, plus a one-click Repair database button.
- **Other:** Company Settings, Activity Log, notification bell, CSV export on every list, and delete buttons with confirmation.

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

### Updating an existing installation
Deploy the new code the same way (Hostinger Git → **Deploy**). The dashboard upgrades the database by itself the first time it opens: it adds missing tables and columns and updates changed fields. You don't need to re-run the installer. Open **System Check** afterwards to confirm that all tables show **OK**.

### 3. Log in and finish setup
- Dashboard: `https://your-domain/`, which redirects to `/admin/`
- Username **`admin`**, password **`admin123`**. Change this right away in **My Profile**.
- Check **Company Settings**. Your GST registration details are filled in already: GSTIN 33CLWPM5315D1Z1, Vivora Healthcare, Manivasagam (Proprietor), Villivakkam, Chennai, **Tamil Nadu**. Add your phone, bank details and UPI ID.
- In **Cash & Bank**, add your bank account(s) with opening balances.
- Add **HSN codes** and **cost prices** to your products so invoices and the profit report are complete.
- Add staff logins under **Users & Roles** if other people will use the dashboard.
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
