<?php
/**
 * Migrate existing orders that have secondary/additional products in wounds_data JSON
 * but no separate order records (created before the multi-product fix)
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

function guid(): string { return bin2hex(random_bytes(16)); }

echo "=== Migrating Multi-Product Orders ===\n\n";

try {
    // Find orders with secondary/additional products in wounds_data but no order_group_id
    $stmt = $pdo->query("
        SELECT id, patient_id, user_id, product_type, wounds_data,
               status, frequency, delivery_mode, payment_type,
               insurer_name, member_id, group_id, payer_phone, prior_auth,
               wound_location, wound_laterality, wound_notes, exudate_level,
               last_eval_date, start_date, qty_per_change, duration_days,
               additional_instructions, secondary_dressing, notes_text,
               shipping_name, shipping_phone, shipping_address, shipping_city, shipping_state, shipping_zip,
               e_sign_user_id, e_sign_name, e_sign_title, e_sign_at, e_sign_ip,
               review_status, created_at
        FROM orders
        WHERE order_group_id IS NULL
          AND wounds_data IS NOT NULL
          AND wounds_data::text LIKE '%secondary_product_id%'
        ORDER BY created_at DESC
    ");

    $orders_to_migrate = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($orders_to_migrate) . " orders to migrate\n\n";

    $migrated_count = 0;
    $skipped_count = 0;

    foreach ($orders_to_migrate as $order) {
        $order_id = $order['id'];
        $wounds_data = json_decode($order['wounds_data'], true);

        if (!is_array($wounds_data) || empty($wounds_data)) {
            echo "  Skipping order $order_id - invalid wounds_data\n";
            $skipped_count++;
            continue;
        }

        $has_multi_product = false;
        foreach ($wounds_data as $wound) {
            if (!empty($wound['secondary_product_id']) || !empty($wound['additional_product_id'])) {
                $has_multi_product = true;
                break;
            }
        }

        if (!$has_multi_product) {
            $skipped_count++;
            continue;
        }

        echo "Migrating order " . substr($order_id, 0, 8) . "...\n";

        // Create order group
        $order_group_id = guid();

        $pdo->prepare("INSERT INTO order_groups (id, patient_id, user_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW())")
           ->execute([$order_group_id, $order['patient_id'], $order['user_id'], $order['status'], $order['created_at']]);

        // Update primary order with order_group_id
        $pdo->prepare("UPDATE orders SET order_group_id = ?, product_type = 'primary', wound_index = 0 WHERE id = ?")
           ->execute([$order_group_id, $order_id]);

        echo "  Created order group: " . substr($order_group_id, 0, 8) . "\n";

        // Create orders for secondary and additional products
        $products_created = 0;
        foreach ($wounds_data as $wound_index => $wound) {
            $products = [];

            // Secondary product
            if (!empty($wound['secondary_product_id']) && $wound['secondary_product_id'] !== '') {
                $products[] = [
                    'type' => 'secondary',
                    'id' => (int)$wound['secondary_product_id'],
                    'name' => $wound['secondary_product_name'] ?? '',
                    'cpt' => $wound['secondary_product_cpt'] ?? null,
                    'price' => floatval($wound['secondary_product_price'] ?? 0)
                ];
            }

            // Additional product
            if (!empty($wound['additional_product_id']) && $wound['additional_product_id'] !== '') {
                $products[] = [
                    'type' => 'additional',
                    'id' => (int)$wound['additional_product_id'],
                    'name' => $wound['additional_product_name'] ?? '',
                    'cpt' => $wound['additional_product_cpt'] ?? null,
                    'price' => floatval($wound['additional_product_price'] ?? 0)
                ];
            }

            foreach ($products as $product) {
                $new_order_id = guid();

                $pdo->prepare("INSERT INTO orders
                    (id, patient_id, user_id, product, product_id, product_price, cpt,
                     order_group_id, product_type, wound_index,
                     status, frequency, delivery_mode, shipments_remaining, created_at, updated_at,
                     insurer_name, member_id, group_id, payer_phone, prior_auth, payment_type,
                     wound_location, wound_laterality, wound_notes, exudate_level, wounds_data,
                     last_eval_date, start_date, qty_per_change, duration_days,
                     additional_instructions, secondary_dressing, notes_text,
                     shipping_name, shipping_phone, shipping_address, shipping_city, shipping_state, shipping_zip,
                     e_sign_user_id, e_sign_name, e_sign_title, e_sign_at, e_sign_ip,
                     review_status)
                    VALUES
                    (?,?,?,?,?,?,?,
                     ?,?,?,
                     ?,?,?,0,?,NOW(),
                     ?,?,?,?,?,?,
                     ?,?,?,?,?,
                     ?,?,?,?,
                     ?,?,?,
                     ?,?,?,?,?,?,
                     ?,?,?,?,?,
                     ?)")
                    ->execute([
                        $new_order_id, $order['patient_id'], $order['user_id'],
                        $product['name'], $product['id'], $product['price'], $product['cpt'],
                        $order_group_id, $product['type'], $wound_index,
                        $order['status'], $order['frequency'], $order['delivery_mode'], $order['created_at'],
                        $order['insurer_name'], $order['member_id'], $order['group_id'],
                        $order['payer_phone'], $order['prior_auth'], $order['payment_type'],
                        $order['wound_location'], $order['wound_laterality'], $order['wound_notes'],
                        $order['exudate_level'], $order['wounds_data'],
                        $order['last_eval_date'], $order['start_date'], $order['qty_per_change'], $order['duration_days'],
                        $order['additional_instructions'], $order['secondary_dressing'], $order['notes_text'],
                        $order['shipping_name'], $order['shipping_phone'], $order['shipping_address'],
                        $order['shipping_city'], $order['shipping_state'], $order['shipping_zip'],
                        $order['e_sign_user_id'], $order['e_sign_name'], $order['e_sign_title'],
                        $order['e_sign_at'], $order['e_sign_ip'],
                        $order['review_status']
                    ]);

                echo "    Created {$product['type']} order: {$product['name']}\n";
                $products_created++;
            }
        }

        echo "  Total products created: $products_created\n\n";
        $migrated_count++;
    }

    echo "\n=== Migration Complete ===\n";
    echo "Orders migrated: $migrated_count\n";
    echo "Orders skipped: $skipped_count\n";
    echo "\nAll existing multi-product orders have been split into separate records.\n";

} catch (PDOException $e) {
    echo "\n✗ Database error:\n";
    echo "  Message: " . $e->getMessage() . "\n";
    echo "  Code: " . $e->getCode() . "\n";
    exit(1);
}
