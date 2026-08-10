USE qr_attendance;

ALTER TABLE qr_codes
  ADD COLUMN token_hash VARCHAR(255) NULL AFTER session_id;

ALTER TABLE qr_codes
  ADD UNIQUE KEY unique_qr_token_hash (token_hash);

ALTER TABLE qr_codes
  MODIFY COLUMN token VARCHAR(100) NULL;

-- Existing plaintext QR tokens are intentionally not copied. Regenerate active QR sessions after applying this migration.