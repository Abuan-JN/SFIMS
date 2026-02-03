CREATE TABLE IF NOT EXISTS ref_main_categories (
    main_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    code INT NOT NULL UNIQUE
);
CREATE TABLE IF NOT EXISTS ref_subcategories (
    sub_id INT AUTO_INCREMENT PRIMARY KEY,
    main_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    code INT NOT NULL,
    FOREIGN KEY (main_id) REFERENCES ref_main_categories(main_id),
    UNIQUE(main_id, code)
);
INSERT INTO ref_main_categories (name, code)
VALUES ('Appliances', 0),
    ('PC Parts', 1),
    ('Electrical', 2),
    ('Modules', 3),
    ('Scantron', 4),
    ('School Supplies', 5) ON DUPLICATE KEY
UPDATE name = name;
SET @cat_appliances = (
        SELECT main_id
        FROM ref_main_categories
        WHERE code = 0
    );
SET @cat_pc = (
        SELECT main_id
        FROM ref_main_categories
        WHERE code = 1
    );
SET @cat_elec = (
        SELECT main_id
        FROM ref_main_categories
        WHERE code = 2
    );
SET @cat_modules = (
        SELECT main_id
        FROM ref_main_categories
        WHERE code = 3
    );
SET @cat_supplies = (
        SELECT main_id
        FROM ref_main_categories
        WHERE code = 5
    );
INSERT INTO ref_subcategories (main_id, name, code)
VALUES (@cat_appliances, 'Air Con', 0),
    (@cat_appliances, 'Fan', 1),
    (@cat_appliances, 'Projector', 2),
    (@cat_appliances, 'TV', 3),
    (@cat_pc, 'Monitor', 0),
    (@cat_pc, 'CPU', 1),
    (@cat_pc, 'LAN Cable', 2),
    (@cat_pc, 'Keyboard', 3),
    (@cat_pc, 'Mouse', 4),
    (@cat_elec, 'Light', 0),
    (@cat_elec, 'Wires', 1),
    (@cat_elec, 'Outlet', 2),
    (@cat_elec, 'Extension Cable', 3),
    (@cat_modules, 'CS', 0),
    (@cat_modules, 'IT', 1),
    (@cat_modules, 'Masscom', 2),
    (@cat_modules, 'Crim', 3),
    (@cat_supplies, 'Marker', 0),
    (@cat_supplies, 'Bondpaper', 1),
    (@cat_supplies, 'Pen', 2),
    (@cat_supplies, 'Pencil', 3) ON DUPLICATE KEY
UPDATE name = name;