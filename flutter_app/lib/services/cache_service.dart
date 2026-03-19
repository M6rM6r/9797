import 'package:hive_flutter/hive_flutter.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import '../models/coupon_model.dart';

class CacheService {
  static const String _couponsBoxName = 'coupons_cache';
  static const String _categoriesBoxName = 'categories_cache';
  static const String _storesBoxName = 'stores_cache';
  static const String _userActivityBoxName = 'user_activity';
  static const String _settingsBoxName = 'app_settings';
  static const int _maxCacheSize = 50;

  static late Box<Coupon> _couponsBox;
  static late Box _categoriesBox;
  static late Box _storesBox;
  static late Box _userActivityBox;
  static late Box _settingsBox;

  static Future<void> initialize() async {
    await Hive.initFlutter();
    
    // Register adapters
    Hive.registerAdapter(CouponAdapter());
    
    // Open boxes
    _couponsBox = await Hive.openBox<Coupon>(_couponsBoxName);
    _categoriesBox = await Hive.openBox(_categoriesBoxName);
    _storesBox = await Hive.openBox(_storesBoxName);
    _userActivityBox = await Hive.openBox(_userActivityBoxName);
    _settingsBox = await Hive.openBox(_settingsBoxName);
  }

  // Coupons caching
  static Future<void> cacheCoupons(List<Coupon> coupons) async {
    try {
      // Clear old cache
      await _couponsBox.clear();
      
      // Add new coupons with timestamp
      final now = DateTime.now().millisecondsSinceEpoch;
      for (int i = 0; i < coupons.length && i < _maxCacheSize; i++) {
        final coupon = coupons[i];
        // Add cache metadata
        await _couponsBox.put(coupon.id ?? i.toString(), coupon);
      }
      
      // Store cache timestamp
      await _settingsBox.put('coupons_cache_time', now);
    } catch (e) {
      print('Error caching coupons: $e');
    }
  }

  static Future<List<Coupon>> getCachedCoupons() async {
    try {
      final coupons = _couponsBox.values.toList();
      return coupons;
    } catch (e) {
      print('Error getting cached coupons: $e');
      return [];
    }
  }

  static Future<bool> isCacheValid() async {
    try {
      final cacheTime = _settingsBox.get('coupons_cache_time');
      if (cacheTime == null) return false;
      
      final now = DateTime.now().millisecondsSinceEpoch;
      final cacheAge = now - cacheTime;
      
      // Cache is valid for 30 minutes
      return cacheAge < 30 * 60 * 1000;
    } catch (e) {
      print('Error checking cache validity: $e');
      return false;
    }
  }

  static Future<void> addCouponToCache(Coupon coupon) async {
    try {
      await _couponsBox.put(coupon.id ?? DateTime.now().toString(), coupon);
      
      // Limit cache size
      if (_couponsBox.length > _maxCacheSize) {
        final keys = _couponsBox.keys.toList();
        final oldestKey = keys.first;
        await _couponsBox.delete(oldestKey);
      }
    } catch (e) {
      print('Error adding coupon to cache: $e');
    }
  }

  static Future<void> removeCouponFromCache(String couponId) async {
    try {
      await _couponsBox.delete(couponId);
    } catch (e) {
      print('Error removing coupon from cache: $e');
    }
  }

  // Categories caching
  static Future<void> cacheCategories(List<Map<String, dynamic>> categories) async {
    try {
      await _categoriesBox.clear();
      for (final category in categories) {
        await _categoriesBox.put(category['id'], category);
      }
      await _settingsBox.put('categories_cache_time', DateTime.now().millisecondsSinceEpoch);
    } catch (e) {
      print('Error caching categories: $e');
    }
  }

  static Future<List<Map<String, dynamic>>> getCachedCategories() async {
    try {
      final categories = _categoriesBox.values.map((e) => Map<String, dynamic>.from(e)).toList();
      return categories;
    } catch (e) {
      print('Error getting cached categories: $e');
      return [];
    }
  }

  // Stores caching
  static Future<void> cacheStores(List<Map<String, dynamic>> stores) async {
    try {
      await _storesBox.clear();
      for (final store in stores) {
        await _storesBox.put(store['id'], store);
      }
      await _settingsBox.put('stores_cache_time', DateTime.now().millisecondsSinceEpoch);
    } catch (e) {
      print('Error caching stores: $e');
    }
  }

  static Future<List<Map<String, dynamic>>> getCachedStores() async {
    try {
      final stores = _storesBox.values.map((e) => Map<String, dynamic>.from(e)).toList();
      return stores;
    } catch (e) {
      print('Error getting cached stores: $e');
      return [];
    }
  }

  // User activity tracking
  static Future<void> trackUserActivity(String activity, Map<String, dynamic> data) async {
    try {
      final timestamp = DateTime.now().millisecondsSinceEpoch;
      await _userActivityBox.put(timestamp, {
        'activity': activity,
        'data': data,
        'timestamp': timestamp,
      });
      
      // Keep only last 100 activities
      if (_userActivityBox.length > 100) {
        final keys = _userActivityBox.keys.toList()..sort();
        final oldestKeys = keys.take(_userActivityBox.length - 100);
        for (final key in oldestKeys) {
          await _userActivityBox.delete(key);
        }
      }
    } catch (e) {
      print('Error tracking user activity: $e');
    }
  }

  static Future<List<Map<String, dynamic>>> getUserActivities() async {
    try {
      final activities = _userActivityBox.values
          .map((e) => Map<String, dynamic>.from(e))
          .toList()
          ..sort((a, b) => (b['timestamp'] as int).compareTo(a['timestamp'] as int));
      return activities;
    } catch (e) {
      print('Error getting user activities: $e');
      return [];
    }
  }

  // App settings
  static Future<void> saveSetting(String key, dynamic value) async {
    try {
      await _settingsBox.put(key, value);
    } catch (e) {
      print('Error saving setting: $e');
    }
  }

  static Future<T?> getSetting<T>(String key) async {
    try {
      return _settingsBox.get(key);
    } catch (e) {
      print('Error getting setting: $e');
      return null;
    }
  }

  // Streak tracking
  static Future<void> updateStreak() async {
    try {
      final today = DateTime.now();
      final todayKey = '${today.year}-${today.month}-${today.day}';
      
      final lastActivity = await _settingsBox.get('last_activity_date');
      final currentStreak = await _settingsBox.get('current_streak', defaultValue: 0);
      
      if (lastActivity != null) {
        final lastDate = DateTime.fromMillisecondsSinceEpoch(lastActivity);
        final difference = today.difference(lastDate).inDays;
        
        if (difference == 1) {
          // Continue streak
          await _settingsBox.put('current_streak', currentStreak + 1);
        } else if (difference > 1) {
          // Reset streak
          await _settingsBox.put('current_streak', 1);
        }
        // If difference is 0, same day, don't update streak
      } else {
        // First activity
        await _settingsBox.put('current_streak', 1);
      }
      
      await _settingsBox.put('last_activity_date', today.millisecondsSinceEpoch);
      await _settingsBox.put(todayKey, true);
    } catch (e) {
      print('Error updating streak: $e');
    }
  }

  static Future<int> getCurrentStreak() async {
    try {
      return await _settingsBox.get('current_streak', defaultValue: 0);
    } catch (e) {
      print('Error getting current streak: $e');
      return 0;
    }
  }

  // Preferred categories
  static Future<void> savePreferredCategories(List<String> categories) async {
    try {
      await _settingsBox.put('preferred_categories', categories);
    } catch (e) {
      print('Error saving preferred categories: $e');
    }
  }

  static Future<List<String>> getPreferredCategories() async {
    try {
      final categories = await _settingsBox.get('preferred_categories');
      return List<String>.from(categories ?? []);
    } catch (e) {
      print('Error getting preferred categories: $e');
      return [];
    }
  }

  // Cache management
  static Future<void> clearCache() async {
    try {
      await _couponsBox.clear();
      await _categoriesBox.clear();
      await _storesBox.clear();
      await _settingsBox.delete('coupons_cache_time');
      await _settingsBox.delete('categories_cache_time');
      await _settingsBox.delete('stores_cache_time');
    } catch (e) {
      print('Error clearing cache: $e');
    }
  }

  static Future<int> getCacheSize() async {
    try {
      return await _couponsBox.length;
    } catch (e) {
      print('Error getting cache size: $e');
      return 0;
    }
  }

  static Future<void> cleanupOldCache() async {
    try {
      final cacheTime = _settingsBox.get('coupons_cache_time');
      if (cacheTime == null) return;
      
      final now = DateTime.now().millisecondsSinceEpoch;
      final cacheAge = now - cacheTime;
      
      // Clear cache if older than 24 hours
      if (cacheAge > 24 * 60 * 60 * 1000) {
        await _couponsBox.clear();
        await _settingsBox.delete('coupons_cache_time');
      }
    } catch (e) {
      print('Error cleaning up old cache: $e');
    }
  }

  // Statistics
  static Future<Map<String, dynamic>> getCacheStats() async {
    try {
      return {
        'coupons_count': await _couponsBox.length,
        'categories_count': await _categoriesBox.length,
        'stores_count': await _storesBox.length,
        'activities_count': await _userActivityBox.length,
        'cache_time': _settingsBox.get('coupons_cache_time'),
        'current_streak': await getCurrentStreak(),
      };
    } catch (e) {
      print('Error getting cache stats: $e');
      return {};
    }
  }

  static Future<void> close() async {
    try {
      await _couponsBox.close();
      await _categoriesBox.close();
      await _storesBox.close();
      await _userActivityBox.close();
      await _settingsBox.close();
    } catch (e) {
      print('Error closing cache boxes: $e');
    }
  }
}

// Hive Type Adapter for Coupon
class CouponAdapter extends TypeAdapter<Coupon> {
  @override
  final typeId = 0;

  @override
  Coupon read(BinaryReader reader) {
    return Coupon(
      id: reader.read(),
      code: reader.read(),
      storeId: reader.read(),
      discountPercent: reader.read(),
      description: reader.read(),
      expiresAt: Timestamp.fromDate(DateTime.fromMillisecondsSinceEpoch(reader.read())),
      usageCount: reader.read(),
      category: reader.read(),
      isVerified: reader.read(),
      affiliateLink: reader.read(),
      isActive: reader.read(),
      createdAt: Timestamp.fromDate(DateTime.fromMillisecondsSinceEpoch(reader.read())),
      updatedAt: Timestamp.fromDate(DateTime.fromMillisecondsSinceEpoch(reader.read())),
      storeName: reader.read(),
      storeLogo: reader.read(),
      storeCashback: reader.read(),
    );
  }

  @override
  void write(BinaryWriter writer, Coupon obj) {
    writer.write(obj.id);
    writer.write(obj.code);
    writer.write(obj.storeId);
    writer.write(obj.discountPercent);
    writer.write(obj.description);
    writer.write(obj.expiresAt.millisecondsSinceEpoch);
    writer.write(obj.usageCount);
    writer.write(obj.category);
    writer.write(obj.isVerified);
    writer.write(obj.affiliateLink);
    writer.write(obj.isActive);
    writer.write(obj.createdAt.millisecondsSinceEpoch);
    writer.write(obj.updatedAt.millisecondsSinceEpoch);
    writer.write(obj.storeName);
    writer.write(obj.storeLogo);
    writer.write(obj.storeCashback);
  }
}
