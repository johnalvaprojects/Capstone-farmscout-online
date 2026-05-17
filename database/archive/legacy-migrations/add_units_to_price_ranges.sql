-- Add unit suffixes back to price ranges
UPDATE categories SET price_range = '₱25-₱120/kg' WHERE id = 1;
UPDATE categories SET price_range = '₱40-₱200/kg' WHERE id = 2;
UPDATE categories SET price_range = '₱280-₱450/kg' WHERE id = 3;
UPDATE categories SET price_range = '₱150-₱350/kg' WHERE id = 4;
UPDATE categories SET price_range = '₱35-₱180/pack' WHERE id = 5;

