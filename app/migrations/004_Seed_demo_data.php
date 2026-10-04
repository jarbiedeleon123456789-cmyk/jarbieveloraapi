<?php

class Seed_demo_data {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
    }

    public function up()
    {
        $db = $this->_lava->db;

        // Demo admin account. Override with ADMIN_EMAIL / ADMIN_PASSWORD env vars.
        $email = getenv('ADMIN_EMAIL') ?: 'admin@velora.com';
        $pass  = getenv('ADMIN_PASSWORD') ?: 'Admin@12345';

        $exists = $db->raw('SELECT id FROM users WHERE email = ? LIMIT 1', [$email])->fetch(PDO::FETCH_ASSOC);
        if (!$exists) {
            $db->raw(
                'INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)',
                ['admin', $email, password_hash($pass, PASSWORD_DEFAULT), 'admin']
            );
        }

        $count = (int) $db->raw('SELECT COUNT(*) FROM products')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $parts = [
            ['Ceramic Brake Pad Set', 'Low-dust ceramic pads with quiet, progressive stopping power. Fits most compact and midsize sedans.', 89.99, 24, 'Brakes', '/parts/brake-pads.svg'],
            ['Slotted Brake Rotor', 'Vented, slotted rotor that sheds heat and gas for consistent bite on spirited drives.', 129.50, 12, 'Brakes', '/parts/brake-rotor.svg'],
            ['Turbocharger Kit', 'Ball-bearing turbo kit with intercooler piping for a clean, reliable power gain.', 1249.00, 4, 'Engine', '/parts/turbo.svg'],
            ['Iridium Spark Plug (4 pack)', 'Fine-wire iridium plugs for crisp ignition, better fuel economy and long service life.', 54.00, 60, 'Engine', '/parts/spark-plug.svg'],
            ['High-Flow Oil Filter', 'Synthetic-media oil filter with anti-drain valve and a high capacity for longer intervals.', 16.75, 120, 'Engine', '/parts/oil-filter.svg'],
            ['Coilover Suspension Kit', 'Height and damping adjustable coilovers that balance daily comfort and track grip.', 899.00, 6, 'Suspension', '/parts/coilover.svg'],
            ['LED Headlight Set', 'Plug-and-play 6000K LED headlight conversion with built-in cooling and sharp beam cutoff.', 149.99, 30, 'Lighting', '/parts/headlight.svg'],
            ['Forged Alloy Wheel 19"', 'Lightweight forged 19-inch alloy wheel in gloss graphite. Sold individually.', 389.00, 16, 'Wheels', '/parts/wheel.svg'],
            ['AGM Car Battery 70Ah', 'Maintenance-free AGM battery with strong cold-cranking power and start-stop support.', 219.00, 18, 'Electrical', '/parts/battery.svg'],
        ];

        foreach ($parts as $p) {
            $db->raw(
                'INSERT INTO products (product_name, description, price, quantity, category, image_url) VALUES (?, ?, ?, ?, ?, ?)',
                $p
            );
        }
    }

    public function down()
    {
        $db = $this->_lava->db;
        $db->raw("DELETE FROM products WHERE image_url LIKE '/parts/%'");
        $db->raw("DELETE FROM users WHERE email = ?", [getenv('ADMIN_EMAIL') ?: 'admin@velora.com']);
    }
}
