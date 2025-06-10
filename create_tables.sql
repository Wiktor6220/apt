CREATE TABLE medicines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    box_id INT NOT NULL,
    drug_id INT NOT NULL,
    package_count INT NOT NULL,
    units_per_package INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    expiration_date DATE NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (box_id) REFERENCES medicine_boxes(id) ON DELETE CASCADE,
    FOREIGN KEY (drug_id) REFERENCES drug_dictionary(id)
); 

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS disposals;
DROP TABLE IF EXISTS intakes;
DROP TABLE IF EXISTS medicines;
DROP TABLE IF EXISTS drug_dictionary;
DROP TABLE IF EXISTS medicine_boxes;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS box_users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE medicine_boxes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE box_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    box_id INT NOT NULL,
    user_id INT NOT NULL,
    access_level ENUM('view', 'edit') NOT NULL DEFAULT 'view',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (box_id) REFERENCES medicine_boxes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_box_user (box_id, user_id)
);

CREATE TABLE drug_dictionary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT
);

CREATE TABLE medicines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    box_id INT NOT NULL,
    drug_id INT NOT NULL,
    package_count INT NOT NULL,
    units_per_package INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    expiration_date DATE NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (box_id) REFERENCES medicine_boxes(id) ON DELETE CASCADE,
    FOREIGN KEY (drug_id) REFERENCES drug_dictionary(id)
); 

CREATE TABLE intakes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    user_id INT NOT NULL,
    dosage_time DATETIME NOT NULL,
    amount VARCHAR(100),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE disposals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    user_id INT NOT NULL,
    dispose_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    quantity INT NOT NULL,
    reason ENUM('expired', 'manual') NOT NULL,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    user_id INT NOT NULL,
    type ENUM('add', 'intake', 'dispose') NOT NULL,
    quantity INT NOT NULL,
    movement_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

SET FOREIGN_KEY_CHECKS = 1;