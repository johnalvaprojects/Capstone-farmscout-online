SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE categories;

INSERT INTO categories (name, filipino_name, description, icon_path, price_range, sort_order) VALUES
('Vegetables', 'Gulay', 'Fresh vegetables and leafy greens from local farms', 'M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z', '₱25-₱120/kg', 1),
('Fruits', 'Prutas', 'Fresh fruits and seasonal produce', 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.293l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13a1 1 0 102 0V9.414l1.293 1.293a1 1 0 001.414-1.414z', '₱40-₱200/kg', 2),
('Meat', 'Karne', 'Fresh meat products from local butchers', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', '₱280-₱450/kg', 3),
('Fish', 'Isda', 'Fresh fish and seafood from La Union waters', 'M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z', '₱150-₱350/kg', 4),
('Processed Goods', 'Processed', 'Packaged and processed food items', 'M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z', '₱35-₱180/pack', 5);
SET FOREIGN_KEY_CHECKS = 1;

