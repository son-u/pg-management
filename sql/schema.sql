-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Buildings Table
CREATE TABLE buildings (
    id SERIAL PRIMARY KEY,
    building_code VARCHAR(10) UNIQUE NOT NULL, -- B1, B2, B3
    building_name VARCHAR(50) NOT NULL,
    building_address TEXT,
    total_rooms INTEGER DEFAULT 0,
    occupied_rooms INTEGER DEFAULT 0,
    total_capacity INTEGER DEFAULT 0,
    current_occupancy INTEGER DEFAULT 0,
    contact_person VARCHAR(100),
    contact_phone VARCHAR(15),
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Insert default buildings
INSERT INTO buildings (building_code, building_name, total_rooms, total_capacity) VALUES
('B1', 'Building 1', 20, 40),
('B2', 'Building 2', 20, 40),
('B3', 'Building 3', 20, 40);

-- Rooms Table
CREATE TABLE rooms (
    id SERIAL PRIMARY KEY,
    building_code VARCHAR(10) REFERENCES buildings(building_code),
    room_number VARCHAR(10) NOT NULL,
    capacity INTEGER NOT NULL DEFAULT 2,
    current_occupancy INTEGER DEFAULT 0,
    monthly_rent DECIMAL(10,2) NOT NULL,
    room_type VARCHAR(50) DEFAULT 'shared',
    facilities TEXT,
    status VARCHAR(20) DEFAULT 'available',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(building_code, room_number)
);

-- Generate rooms for all buildings
DO $$
DECLARE
    building_codes TEXT[] := ARRAY['B1', 'B2', 'B3'];
    base_room_nums INTEGER[] := ARRAY[101, 201, 301];
    building_code TEXT;
    base_num INTEGER;
    room_num INTEGER;
    i INTEGER;
BEGIN
    FOR i IN 1..3 LOOP
        building_code := building_codes[i];
        base_num := base_room_nums[i];
        
        FOR room_num IN base_num..(base_num + 19) LOOP
            INSERT INTO rooms (building_code, room_number, capacity, monthly_rent)
            VALUES (building_code, room_num::TEXT, 2, 5000.00);
        END LOOP;
    END LOOP;
END $$;

-- Students Table
CREATE TABLE students (
    id SERIAL PRIMARY KEY,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    building_code VARCHAR(10) REFERENCES buildings(building_code),
    room_number VARCHAR(10),
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    email VARCHAR(100) UNIQUE,
    date_of_birth DATE,
    parent_phone VARCHAR(15) NOT NULL,
    department VARCHAR(50) NOT NULL,
    college_name VARCHAR(100),
    year_of_study VARCHAR(20),
    admission_date DATE NOT NULL DEFAULT CURRENT_DATE,
    emergency_contact VARCHAR(15),
    permanent_address TEXT,
    profile_photo_url TEXT,
    monthly_rent DECIMAL(10,2) DEFAULT 5000.00,
    security_deposit DECIMAL(10,2) DEFAULT 10000.00,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (building_code, room_number) REFERENCES rooms(building_code, room_number)
);

-- Payments Table
CREATE TABLE payments (
    id SERIAL PRIMARY KEY,
    payment_id VARCHAR(20) UNIQUE NOT NULL,
    student_id VARCHAR(20) REFERENCES students(student_id),
    building_code VARCHAR(10) REFERENCES buildings(building_code),
    month_year VARCHAR(10) NOT NULL, -- Format: YYYY-MM
    amount_due DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(20) NOT NULL DEFAULT 'cash',
    receipt_number VARCHAR(50),
    payment_status VARCHAR(20) DEFAULT 'paid',
    late_fee DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    created_by VARCHAR(50) DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Building Monthly Summary Table
CREATE TABLE building_monthly_summary (
    id SERIAL PRIMARY KEY,
    building_code VARCHAR(10) REFERENCES buildings(building_code),
    month_year VARCHAR(10) NOT NULL,
    total_expected DECIMAL(12,2) DEFAULT 0,
    total_collected DECIMAL(12,2) DEFAULT 0,
    total_pending DECIMAL(12,2) DEFAULT 0,
    students_count INTEGER DEFAULT 0,
    occupancy_rate DECIMAL(5,2) DEFAULT 0,
    revenue_contribution_percentage DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(building_code, month_year)
);

-- Overall Monthly Summary Table
CREATE TABLE overall_monthly_summary (
    id SERIAL PRIMARY KEY,
    month_year VARCHAR(10) UNIQUE NOT NULL,
    total_revenue_collected DECIMAL(15,2) DEFAULT 0,
    total_revenue_expected DECIMAL(15,2) DEFAULT 0,
    total_pending_amount DECIMAL(15,2) DEFAULT 0,
    total_students_count INTEGER DEFAULT 0,
    total_occupancy_rate DECIMAL(5,2) DEFAULT 0,
    b1_revenue DECIMAL(12,2) DEFAULT 0,
    b2_revenue DECIMAL(12,2) DEFAULT 0,
    b3_revenue DECIMAL(12,2) DEFAULT 0,
    growth_percentage DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Admin Users Table (for authentication)
CREATE TABLE admin_users (
    id SERIAL PRIMARY KEY,
    user_id UUID DEFAULT uuid_generate_v4(),
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash TEXT,
    full_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) DEFAULT 'admin',
    last_login TIMESTAMP,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Create indexes for better performance
CREATE INDEX idx_students_building ON students(building_code);
CREATE INDEX idx_students_status ON students(status);
CREATE INDEX idx_payments_student ON payments(student_id);
CREATE INDEX idx_payments_building ON payments(building_code);
CREATE INDEX idx_payments_month ON payments(month_year);
CREATE INDEX idx_rooms_building ON rooms(building_code);
