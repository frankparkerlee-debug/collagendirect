-- Add proper tracking and carrier columns to orders table
-- Previously tracking was incorrectly stored in rx_note_name/rx_note_mime

-- Add the columns
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS tracking_number VARCHAR(100),
  ADD COLUMN IF NOT EXISTS carrier VARCHAR(50);

-- Migrate existing data: if rx_note_name looks like a tracking number, move it
-- Tracking numbers are typically alphanumeric, no file extensions
UPDATE orders
SET
  tracking_number = rx_note_name,
  carrier = rx_note_mime,
  rx_note_name = NULL,
  rx_note_mime = NULL
WHERE
  rx_note_name IS NOT NULL
  AND rx_note_name NOT LIKE '%.pdf'
  AND rx_note_name NOT LIKE '%.jpg'
  AND rx_note_name NOT LIKE '%.png'
  AND rx_note_name NOT LIKE '%.txt';

-- Add index for tracking lookups
CREATE INDEX IF NOT EXISTS idx_orders_tracking ON orders(tracking_number);

-- Update the notification trigger to use the correct columns
CREATE OR REPLACE FUNCTION log_order_status_change()
RETURNS TRIGGER AS $$
BEGIN
  IF (OLD.status IS DISTINCT FROM NEW.status) THEN
    INSERT INTO order_status_changes (
      order_id,
      old_status,
      new_status,
      changed_by,
      tracking_code,
      carrier
    ) VALUES (
      NEW.id,
      OLD.status,
      NEW.status,
      NEW.reviewed_by,
      NEW.tracking_number,  -- use correct column
      NEW.carrier          -- use correct column
    );
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Comments for documentation
COMMENT ON COLUMN orders.tracking_number IS 'Shipping tracking number (e.g. 1Z999AA10123456784)';
COMMENT ON COLUMN orders.carrier IS 'Shipping carrier name (e.g. UPS, FedEx, USPS)';
