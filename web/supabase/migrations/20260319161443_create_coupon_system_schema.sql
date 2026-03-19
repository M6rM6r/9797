/*
  # Coupon Aggregator System Schema

  1. New Tables
    - `categories`
      - `id` (uuid, primary key)
      - `name` (text) - English name
      - `name_ar` (text) - Arabic name
      - `icon` (text) - Icon identifier
      - `order` (integer) - Display order
      - `is_active` (boolean) - Active status
      - `created_at` (timestamptz)
      - `updated_at` (timestamptz)
    
    - `stores`
      - `id` (uuid, primary key)
      - `name` (text) - Store name
      - `logo` (text) - Logo URL
      - `url` (text) - Store website URL
      - `cashback_percent` (numeric) - Cashback percentage
      - `category` (text) - Store category
      - `is_active` (boolean) - Active status
      - `coupon_count` (integer) - Number of coupons
      - `created_at` (timestamptz)
      - `updated_at` (timestamptz)
    
    - `coupons`
      - `id` (uuid, primary key)
      - `code` (text) - Coupon code
      - `store_id` (uuid, foreign key) - Reference to stores
      - `store_name` (text) - Store name (denormalized)
      - `store_logo` (text) - Store logo URL (denormalized)
      - `discount_percent` (numeric) - Discount percentage
      - `description` (text) - English description
      - `description_ar` (text) - Arabic description
      - `expires_at` (timestamptz) - Expiration date
      - `usage_count` (integer) - Number of times used
      - `category` (text) - Coupon category
      - `is_verified` (boolean) - Verification status
      - `affiliate_link` (text) - Affiliate redirect URL
      - `is_active` (boolean) - Active status
      - `image_url` (text) - Coupon image URL
      - `created_at` (timestamptz)
      - `updated_at` (timestamptz)
    
    - `analytics`
      - `id` (uuid, primary key)
      - `coupon_id` (uuid, foreign key) - Reference to coupons
      - `event` (text) - Event type (view, copy, click)
      - `platform` (text) - Platform (web, mobile)
      - `timestamp` (timestamptz)

  2. Security
    - Enable RLS on all tables
    - Add public read policies for coupons, stores, and categories
    - Add insert policy for analytics (public can track)
    - Restrict write operations to authenticated admin users
*/

-- Create categories table
CREATE TABLE IF NOT EXISTS categories (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text NOT NULL,
  name_ar text NOT NULL,
  icon text NOT NULL DEFAULT 'tag',
  "order" integer NOT NULL DEFAULT 0,
  is_active boolean DEFAULT true,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

-- Create stores table
CREATE TABLE IF NOT EXISTS stores (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text NOT NULL,
  logo text NOT NULL,
  url text NOT NULL,
  cashback_percent numeric(5,2) DEFAULT 0,
  category text DEFAULT '',
  is_active boolean DEFAULT true,
  coupon_count integer DEFAULT 0,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

-- Create coupons table
CREATE TABLE IF NOT EXISTS coupons (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  code text NOT NULL,
  store_id uuid REFERENCES stores(id) ON DELETE CASCADE,
  store_name text NOT NULL,
  store_logo text NOT NULL,
  discount_percent numeric(5,2) DEFAULT 0,
  description text DEFAULT '',
  description_ar text DEFAULT '',
  expires_at timestamptz,
  usage_count integer DEFAULT 0,
  category text DEFAULT '',
  is_verified boolean DEFAULT false,
  affiliate_link text DEFAULT '',
  is_active boolean DEFAULT true,
  image_url text DEFAULT '',
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

-- Create analytics table
CREATE TABLE IF NOT EXISTS analytics (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  coupon_id uuid REFERENCES coupons(id) ON DELETE CASCADE,
  event text NOT NULL,
  platform text DEFAULT 'web',
  timestamp timestamptz DEFAULT now()
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_coupons_store_id ON coupons(store_id);
CREATE INDEX IF NOT EXISTS idx_coupons_category ON coupons(category);
CREATE INDEX IF NOT EXISTS idx_coupons_is_active ON coupons(is_active);
CREATE INDEX IF NOT EXISTS idx_coupons_expires_at ON coupons(expires_at);
CREATE INDEX IF NOT EXISTS idx_stores_is_active ON stores(is_active);
CREATE INDEX IF NOT EXISTS idx_categories_order ON categories("order");
CREATE INDEX IF NOT EXISTS idx_analytics_coupon_id ON analytics(coupon_id);
CREATE INDEX IF NOT EXISTS idx_analytics_timestamp ON analytics(timestamp);

-- Enable Row Level Security
ALTER TABLE categories ENABLE ROW LEVEL SECURITY;
ALTER TABLE stores ENABLE ROW LEVEL SECURITY;
ALTER TABLE coupons ENABLE ROW LEVEL SECURITY;
ALTER TABLE analytics ENABLE ROW LEVEL SECURITY;

-- Public read policies for categories
CREATE POLICY "Public can view active categories"
  ON categories FOR SELECT
  USING (is_active = true);

-- Public read policies for stores
CREATE POLICY "Public can view active stores"
  ON stores FOR SELECT
  USING (is_active = true);

-- Public read policies for coupons
CREATE POLICY "Public can view active coupons"
  ON coupons FOR SELECT
  USING (is_active = true);

-- Public insert policy for analytics
CREATE POLICY "Public can insert analytics"
  ON analytics FOR INSERT
  WITH CHECK (true);

-- Public read policy for analytics
CREATE POLICY "Public can view analytics"
  ON analytics FOR SELECT
  USING (true);

-- Function to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$ language 'plpgsql';

-- Triggers for updated_at
CREATE TRIGGER update_categories_updated_at BEFORE UPDATE ON categories
  FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_stores_updated_at BEFORE UPDATE ON stores
  FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_coupons_updated_at BEFORE UPDATE ON coupons
  FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Function to update store coupon count
CREATE OR REPLACE FUNCTION update_store_coupon_count()
RETURNS TRIGGER AS $$
BEGIN
  IF TG_OP = 'INSERT' THEN
    UPDATE stores SET coupon_count = coupon_count + 1 WHERE id = NEW.store_id;
  ELSIF TG_OP = 'DELETE' THEN
    UPDATE stores SET coupon_count = coupon_count - 1 WHERE id = OLD.store_id;
  END IF;
  RETURN NULL;
END;
$$ language 'plpgsql';

-- Trigger to automatically update store coupon count
CREATE TRIGGER update_store_coupon_count_trigger
  AFTER INSERT OR DELETE ON coupons
  FOR EACH ROW EXECUTE FUNCTION update_store_coupon_count();