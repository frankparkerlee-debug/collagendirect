<?php
/**
 * Migration: Pricing templates ("House Pricing")
 * Lets admins save a set of practice prices as a named template and apply it
 * to a practice (populating the pricing form, which is then saved as normal).
 */
require_once __DIR__ . '/db.php';

$pdo->exec("
  CREATE TABLE IF NOT EXISTS pricing_templates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    notes TEXT,
    created_by VARCHAR(64),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
  )
");

$pdo->exec("
  CREATE TABLE IF NOT EXISTS pricing_template_items (
    id SERIAL PRIMARY KEY,
    template_id INTEGER NOT NULL REFERENCES pricing_templates(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL,
    custom_price NUMERIC(10,2) NOT NULL,
    discount_percentage NUMERIC(6,2),
    UNIQUE(template_id, product_id)
  )
");

$pdo->exec("CREATE INDEX IF NOT EXISTS idx_pricing_template_items_tpl ON pricing_template_items(template_id)");

echo "OK: pricing_templates + pricing_template_items ready.\n";
