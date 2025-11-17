-- Add Hydrapad products to the products table
-- Run this script to add missing Hydrapad Adherent and Non-Adherent products

-- Hydrapad Adherent products
INSERT INTO products (name, pieces_per_box, price_wholesale, active, created_at) VALUES
('Hydrapad Adherent 2x2', 10, 25.00, TRUE, NOW()),
('Hydrapad Adherent 4x4', 10, 45.00, TRUE, NOW()),
('Hydrapad Adherent 6x6', 5, 55.00, TRUE, NOW())
ON CONFLICT (name) DO NOTHING;

-- Hydrapad Non-Adherent products
INSERT INTO products (name, pieces_per_box, price_wholesale, active, created_at) VALUES
('Hydrapad Non-Adherent 2x2', 10, 25.00, TRUE, NOW()),
('Hydrapad Non-Adherent 4x4', 10, 45.00, TRUE, NOW()),
('Hydrapad Non-Adherent 6x6', 5, 55.00, TRUE, NOW())
ON CONFLICT (name) DO NOTHING;

-- Note: Adjust pieces_per_box and price_wholesale values as needed for your actual products
