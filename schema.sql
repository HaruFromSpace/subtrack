CREATE TABLE IF NOT EXISTS subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    cycle ENUM('weekly', 'monthly', 'yearly') NOT NULL DEFAULT 'monthly',
    next_renewal DATE NOT NULL,
    category VARCHAR(40) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
