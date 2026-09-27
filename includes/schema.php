<?php
/**
 * Database schema and starter data for Vivora Healthcare.
 * Used by install.php. All statements are idempotent (IF NOT EXISTS / only
 * seed empty tables) so the installer can safely be re-run.
 */

declare(strict_types=1);

function vivora_schema(): array
{
    return [
        "CREATE TABLE IF NOT EXISTS admins (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(60) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(120) NOT NULL DEFAULT '',
            email VARCHAR(160) NOT NULL DEFAULT '',
            last_login_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            username VARCHAR(60) NOT NULL DEFAULT '',
            attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_time (ip, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS settings (
            skey VARCHAR(60) NOT NULL PRIMARY KEY,
            svalue TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(140) NOT NULL UNIQUE,
            description TEXT NULL,
            icon VARCHAR(40) NOT NULL DEFAULT 'package',
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS products (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category_id INT UNSIGNED NULL,
            name VARCHAR(200) NOT NULL,
            slug VARCHAR(220) NOT NULL UNIQUE,
            sku VARCHAR(60) NOT NULL DEFAULT '',
            brand VARCHAR(120) NOT NULL DEFAULT '',
            model VARCHAR(120) NOT NULL DEFAULT '',
            short_description VARCHAR(300) NOT NULL DEFAULT '',
            description TEXT NULL,
            specifications TEXT NULL,
            price DECIMAL(12,2) NULL,
            gst_rate DECIMAL(5,2) NOT NULL DEFAULT 12.00,
            unit VARCHAR(30) NOT NULL DEFAULT 'Unit',
            stock_qty INT NOT NULL DEFAULT 0,
            min_stock INT NOT NULL DEFAULT 0,
            warranty VARCHAR(120) NOT NULL DEFAULT '',
            image VARCHAR(120) NULL,
            brochure VARCHAR(120) NULL,
            show_price TINYINT(1) NOT NULL DEFAULT 0,
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            views INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_category (category_id),
            INDEX idx_active_featured (is_active, is_featured),
            CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS customers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            organization VARCHAR(200) NOT NULL DEFAULT '',
            type ENUM('hospital','clinic','diagnostic_centre','nursing_home','dealer','doctor','other') NOT NULL DEFAULT 'hospital',
            email VARCHAR(160) NOT NULL DEFAULT '',
            phone VARCHAR(30) NOT NULL DEFAULT '',
            gstin VARCHAR(20) NOT NULL DEFAULT '',
            address VARCHAR(400) NOT NULL DEFAULT '',
            city VARCHAR(80) NOT NULL DEFAULT '',
            state VARCHAR(80) NOT NULL DEFAULT '',
            pincode VARCHAR(10) NOT NULL DEFAULT '',
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS enquiries (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id INT UNSIGNED NULL,
            customer_id INT UNSIGNED NULL,
            source ENUM('contact','quote','product') NOT NULL DEFAULT 'contact',
            name VARCHAR(160) NOT NULL,
            organization VARCHAR(200) NOT NULL DEFAULT '',
            email VARCHAR(160) NOT NULL DEFAULT '',
            phone VARCHAR(30) NOT NULL DEFAULT '',
            city VARCHAR(80) NOT NULL DEFAULT '',
            quantity INT NULL,
            subject VARCHAR(200) NOT NULL DEFAULT '',
            message TEXT NULL,
            status ENUM('new','contacted','quoted','won','lost') NOT NULL DEFAULT 'new',
            notes TEXT NULL,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_created (created_at),
            CONSTRAINT fk_enquiries_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
            CONSTRAINT fk_enquiries_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS service_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ticket_no VARCHAR(30) NOT NULL DEFAULT '',
            name VARCHAR(160) NOT NULL,
            organization VARCHAR(200) NOT NULL DEFAULT '',
            email VARCHAR(160) NOT NULL DEFAULT '',
            phone VARCHAR(30) NOT NULL DEFAULT '',
            city VARCHAR(80) NOT NULL DEFAULT '',
            equipment VARCHAR(200) NOT NULL DEFAULT '',
            brand_model VARCHAR(200) NOT NULL DEFAULT '',
            serial_no VARCHAR(100) NOT NULL DEFAULT '',
            request_type ENUM('installation','repair','amc','calibration','training','other') NOT NULL DEFAULT 'repair',
            priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
            description TEXT NULL,
            preferred_date DATE NULL,
            status ENUM('open','scheduled','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
            engineer VARCHAR(120) NOT NULL DEFAULT '',
            notes TEXT NULL,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS quotations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quote_no VARCHAR(40) NOT NULL UNIQUE,
            customer_id INT UNSIGNED NULL,
            enquiry_id INT UNSIGNED NULL,
            customer_name VARCHAR(160) NOT NULL DEFAULT '',
            customer_org VARCHAR(200) NOT NULL DEFAULT '',
            customer_email VARCHAR(160) NOT NULL DEFAULT '',
            customer_phone VARCHAR(30) NOT NULL DEFAULT '',
            customer_address VARCHAR(400) NOT NULL DEFAULT '',
            customer_gstin VARCHAR(20) NOT NULL DEFAULT '',
            quote_date DATE NOT NULL,
            valid_until DATE NULL,
            status ENUM('draft','sent','accepted','rejected') NOT NULL DEFAULT 'draft',
            subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
            discount DECIMAL(14,2) NOT NULL DEFAULT 0,
            tax_total DECIMAL(14,2) NOT NULL DEFAULT 0,
            grand_total DECIMAL(14,2) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            terms TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            CONSTRAINT fk_quotations_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            CONSTRAINT fk_quotations_enquiry FOREIGN KEY (enquiry_id) REFERENCES enquiries(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS quotation_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quotation_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NULL,
            description VARCHAR(300) NOT NULL,
            qty DECIMAL(10,2) NOT NULL DEFAULT 1,
            unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(14,2) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            CONSTRAINT fk_items_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE,
            CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS stock_movements (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id INT UNSIGNED NOT NULL,
            change_qty INT NOT NULL,
            balance_after INT NOT NULL,
            reason VARCHAR(200) NOT NULL DEFAULT '',
            admin_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_product (product_id),
            CONSTRAINT fk_moves_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS activity_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_id INT UNSIGNED NULL,
            action VARCHAR(60) NOT NULL,
            details VARCHAR(400) NOT NULL DEFAULT '',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
}

/** Product categories Vivora Healthcare deals in, with a Feather icon each. */
function vivora_seed_categories(): array
{
    return [
        ['ECG Machines', 'activity', 'Single, 3, 6 and 12-channel electrocardiographs for clinics, hospitals and diagnostic centres.'],
        ['ECG Diagnostic Papers', 'file-text', 'Thermal chart paper rolls and Z-fold paper compatible with leading ECG machine models.'],
        ['Patient Monitors', 'monitor', 'Multi-parameter bedside monitors for ICU, OT, emergency and ward use.'],
        ['Defibrillators', 'zap', 'Biphasic manual defibrillators and AEDs for emergency cardiac care.'],
        ['Ultrasound Systems', 'radio', 'Portable and trolley-based ultrasound systems for general and specialised imaging.'],
        ['Surgical Instruments', 'scissors', 'Stainless-steel surgical instruments and sets for general and speciality surgery.'],
        ['Medical Equipment', 'heart', 'Essential hospital and clinic equipment for everyday patient care.'],
        ['Diagnostic Machines', 'cpu', 'Diagnostic devices and analysers for accurate, fast clinical results.'],
        ['Surgical Accessories', 'tool', 'Cables, probes, sensors, electrodes and accessories for surgical and monitoring equipment.'],
        ['Healthcare Consumables', 'droplet', 'Everyday consumables for hospitals, clinics and laboratories.'],
        ['Hospital Accessories', 'grid', 'Furniture, trolleys, stands and accessories for wards, OTs and clinics.'],
    ];
}

/** Starter catalogue — generic listings the admin can edit, price or delete. */
function vivora_seed_products(): array
{
    return [
        'ECG Machines' => [
            ['12-Channel ECG Machine', 'Interpretive 12-channel ECG with large display and thermal printer.', "Channels: 12\nLeads: Standard 12-lead\nDisplay: Colour LCD touch screen\nRecording: Auto / Manual / Rhythm\nPrinter: Built-in thermal printer\nConnectivity: USB / LAN\nPower: AC mains with rechargeable battery", 1],
            ['6-Channel ECG Machine', 'Compact 6-channel electrocardiograph for clinics and nursing homes.', "Channels: 6\nLeads: Standard 12-lead\nDisplay: LCD\nPrinter: Built-in thermal printer\nPower: AC mains with rechargeable battery", 0],
            ['3-Channel ECG Machine', 'Portable 3-channel ECG for OPDs and field use.', "Channels: 3\nLeads: Standard 12-lead\nPrinter: Built-in thermal printer\nPower: Rechargeable battery", 0],
        ],
        'ECG Diagnostic Papers' => [
            ['ECG Thermal Paper Roll (Single Channel)', 'Grid-printed thermal chart paper roll for single-channel ECG machines.', "Type: Thermal roll\nPrint: Pre-printed grid\nPack: Box of rolls", 0],
            ['ECG Z-Fold Paper (12-Channel)', 'Z-fold thermal paper pads for 12-channel ECG recorders.', "Type: Z-fold pad\nPrint: Pre-printed grid\nCompatibility: Common 12-channel models", 0],
        ],
        'Patient Monitors' => [
            ['Multi-Parameter Patient Monitor', 'Bedside monitor for ECG, SpO2, NIBP, temperature and respiration.', "Parameters: ECG, SpO2, NIBP, Temp, Resp\nDisplay: 12.1\" colour TFT\nAlarms: Audio-visual, 3 levels\nStorage: Trend and event memory\nPower: AC mains with battery backup", 1],
            ['ICU Patient Monitor with EtCO2', 'Advanced monitor with optional EtCO2 and IBP modules for critical care.', "Parameters: ECG, SpO2, NIBP, Temp, Resp, EtCO2 (optional), IBP (optional)\nDisplay: 15\" touch screen\nNetworking: Central station ready", 0],
        ],
        'Defibrillators' => [
            ['Biphasic Defibrillator with Monitor', 'Manual biphasic defibrillator with ECG monitoring and printer.', "Waveform: Biphasic\nModes: Manual, Synchronised cardioversion\nMonitoring: ECG\nPrinter: Built-in thermal\nPower: AC mains with rechargeable battery", 1],
            ['Automated External Defibrillator (AED)', 'Easy-to-use AED with voice prompts for public-access and emergency use.', "Waveform: Biphasic\nGuidance: Voice and visual prompts\nPads: Adult (paediatric optional)\nPower: Long-life battery", 0],
        ],
        'Ultrasound Systems' => [
            ['Portable Colour Doppler Ultrasound', 'Laptop-style colour Doppler ultrasound for multi-speciality imaging.', "Modes: B, M, Colour Doppler, PW\nProbes: Convex, Linear (others optional)\nDisplay: 15\" LED\nStorage: Internal with USB export", 1],
            ['Trolley-Based Ultrasound System', 'Full-size ultrasound with multiple probe ports for hospitals and imaging centres.', "Modes: B, M, Colour, PW, CW\nProbe ports: Multiple\nDisplay: 21\" monitor with touch panel", 0],
        ],
        'Surgical Instruments' => [
            ['General Surgery Instrument Set', 'Stainless-steel general surgery set with forceps, scissors, holders and retractors.', "Material: Surgical-grade stainless steel\nFinish: Satin\nSterilisation: Autoclavable", 0],
            ['Artery Forceps (Straight & Curved)', 'Haemostatic artery forceps in multiple sizes.', "Material: Stainless steel\nTypes: Straight, Curved\nSterilisation: Autoclavable", 0],
        ],
        'Medical Equipment' => [
            ['Syringe Infusion Pump', 'Accurate syringe pump for controlled drug delivery.', "Syringe sizes: 10 / 20 / 50 ml\nModes: Rate, Time, Body weight\nAlarms: Occlusion, near-empty, low battery", 0],
            ['Suction Machine', 'Electric suction apparatus for OT, ICU and emergency use.', "Type: Electric\nJars: Twin collection jars\nVacuum: Adjustable with gauge", 0],
        ],
        'Diagnostic Machines' => [
            ['Pulse Oximeter (Tabletop)', 'Tabletop SpO2 and pulse-rate monitor with alarms.', "Parameters: SpO2, Pulse rate\nDisplay: LED/LCD\nAlarms: High/Low limits", 0],
            ['Digital BP Monitor (Clinical)', 'Clinical-grade automatic blood pressure monitor.', "Measurement: Oscillometric\nCuff: Adult (other sizes optional)\nMemory: Multi-user", 0],
        ],
        'Surgical Accessories' => [
            ['ECG Patient Cable (10-Lead)', 'Replacement 10-lead patient cable for 12-channel ECG machines.', "Leads: 10\nConnector: Model-specific\nTermination: Banana / Snap", 0],
            ['Reusable SpO2 Sensor', 'Adult finger-clip SpO2 probe compatible with common monitors.', "Type: Finger clip\nPatient: Adult\nCompatibility: Model-specific connectors", 0],
        ],
        'Healthcare Consumables' => [
            ['Disposable ECG Electrodes', 'Foam-backed pre-gelled ECG electrodes for monitoring and diagnostics.', "Type: Pre-gelled, foam backing\nPack: 50 pcs", 0],
            ['Ultrasound Gel', 'Water-based, non-irritant ultrasound transmission gel.', "Base: Water-soluble\nPack: 250 ml / 5 L", 0],
        ],
        'Hospital Accessories' => [
            ['Stainless Steel Instrument Trolley', 'Two-shelf instrument trolley with castors for OT and wards.', "Material: Stainless steel\nShelves: 2\nCastors: Swivel with brakes", 0],
            ['IV Stand (Adjustable)', 'Height-adjustable IV stand with four hooks and castor base.', "Hooks: 4\nHeight: Adjustable\nBase: 5-leg castor base", 0],
        ],
    ];
}

function vivora_default_settings(): array
{
    return [
        'company_name'   => 'Vivora Healthcare',
        'tagline'        => 'Better Equipment. Healthier Tomorrows.',
        'phone'          => '',
        'phone2'         => '',
        'whatsapp'       => '',
        'email'          => '',
        'address'        => '',
        'city'           => '',
        'gstin'          => '',
        'business_hours' => 'Mon – Sat: 10:00 AM – 7:00 PM',
        'facebook'       => '',
        'instagram'      => '',
        'linkedin'       => '',
        'youtube'        => '',
        'map_embed_url'  => '',
        'about_text'     => "Vivora Healthcare supplies reliable medical equipment, diagnostic devices, surgical instruments and consumables to hospitals, clinics, nursing homes and diagnostic centres.\n\nFrom ECG machines and patient monitors to defibrillators, ultrasound systems and everyday consumables, we help healthcare providers source the right equipment — and support it with installation, training and after-sales service.",
        'hero_title'     => 'Trusted Medical Equipment for Better Patient Care',
        'hero_subtitle'  => 'ECG machines, patient monitors, defibrillators, ultrasound systems, surgical instruments and hospital consumables — sourced, supplied and supported by Vivora Healthcare.',
        'quote_prefix'   => 'VH-Q-',
        'quote_validity_days' => '15',
        'quote_terms'    => "1. Prices are in INR and GST is charged as applicable.\n2. Delivery within 7–15 working days from confirmed order, subject to stock.\n3. Payment: 100% advance unless otherwise agreed.\n4. Warranty as per manufacturer terms.\n5. Installation and demo included where applicable.",
        'bank_details'   => '',
        'notify_email'   => '',
        'meta_description' => 'Vivora Healthcare – supplier of ECG machines, ECG papers, patient monitors, defibrillators, ultrasound systems, surgical instruments, diagnostic machines and healthcare consumables.',
    ];
}

/**
 * Create tables and seed starter data. Returns a list of log lines.
 */
function vivora_install(PDO $pdo, string $adminUser, string $adminPass): array
{
    $log = [];
    foreach (vivora_schema() as $sql) {
        $pdo->exec($sql);
    }
    $log[] = 'Database tables are ready.';

    // Settings: insert missing keys only.
    $st = $pdo->prepare('INSERT IGNORE INTO settings (skey, svalue) VALUES (?, ?)');
    foreach (vivora_default_settings() as $k => $v) {
        $st->execute([$k, $v]);
    }
    $log[] = 'Default company settings added.';

    // Categories & products only when the catalogue is empty.
    if ((int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() === 0) {
        $catIds = [];
        $st = $pdo->prepare('INSERT INTO categories (name, slug, description, icon, sort_order) VALUES (?, ?, ?, ?, ?)');
        foreach (vivora_seed_categories() as $i => [$name, $icon, $desc]) {
            $st->execute([$name, slugify($name), $desc, $icon, $i + 1]);
            $catIds[$name] = (int) $pdo->lastInsertId();
        }
        $log[] = count($catIds) . ' product categories created.';

        $st = $pdo->prepare('INSERT INTO products (category_id, name, slug, sku, short_description, description, specifications, stock_qty, min_stock, is_featured)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $n = 0;
        foreach (vivora_seed_products() as $cat => $items) {
            foreach ($items as [$name, $short, $specs, $featured]) {
                $n++;
                $st->execute([
                    $catIds[$cat] ?? null, $name, slugify($name), sprintf('VH-%04d', $n), $short,
                    $short . "\n\nContact Vivora Healthcare for pricing, available brands and models, installation and after-sales support.",
                    $specs, 0, 0, $featured,
                ]);
            }
        }
        $log[] = $n . ' starter products added (edit prices, stock and images from the dashboard).';
    } else {
        $log[] = 'Existing catalogue found — categories and products left unchanged.';
    }

    // Admin account.
    $exists = $pdo->prepare('SELECT id FROM admins WHERE username = ?');
    $exists->execute([$adminUser]);
    if (!$exists->fetchColumn()) {
        $pdo->prepare('INSERT INTO admins (username, password_hash, full_name) VALUES (?, ?, ?)')
            ->execute([$adminUser, password_hash($adminPass, PASSWORD_DEFAULT), 'Administrator']);
        $log[] = 'Admin user "' . $adminUser . '" created.';
    } else {
        $log[] = 'Admin user "' . $adminUser . '" already exists — password not changed.';
    }

    return $log;
}
