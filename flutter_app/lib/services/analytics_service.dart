import 'package:firebase_analytics/firebase_analytics.dart';

class AnalyticsService {
  static final FirebaseAnalytics _analytics = FirebaseAnalytics.instance;
  static FirebaseAnalytics get analytics => _analytics;

  static Future<void> initialize() async {
    await _analytics.setAnalyticsCollectionEnabled(true);
  }

  // User engagement events
  static Future<void> logAppOpen() async {
    await _analytics.logAppOpen();
  }

  static Future<void> logCouponCopy(String couponId, String storeName, String discount) async {
    await _analytics.logEvent(
      name: 'coupon_copy',
      parameters: {
        'coupon_id': couponId,
        'store_name': storeName,
        'discount_percent': discount,
      },
    );
  }

  static Future<void> logCouponShare(String couponId, String storeName, String method) async {
    await _analytics.logEvent(
      name: 'coupon_share',
      parameters: {
        'coupon_id': couponId,
        'store_name': storeName,
        'share_method': method, // 'whatsapp', 'twitter', 'copy_link'
      },
    );
  }

  static Future<void> logStoreRedirect(String storeName, String couponId) async {
    await _analytics.logEvent(
      name: 'store_redirect',
      parameters: {
        'store_name': storeName,
        'coupon_id': couponId,
      },
    );
  }

  static Future<void> logSearch(String query, int resultCount) async {
    await _analytics.logEvent(
      name: 'search',
      parameters: {
        'search_term': query,
        'number_of_results': resultCount,
      },
    );
  }

  static Future<void> logCategoryView(String category) async {
    await _analytics.logEvent(
      name: 'category_view',
      parameters: {
        'category': category,
      },
    );
  }

  static Future<void> logBlogView(String blogId, String category) async {
    await _analytics.logEvent(
      name: 'blog_view',
      parameters: {
        'blog_id': blogId,
        'blog_category': category,
      },
    );
  }

  // Screen tracking
  static Future<void> logScreenView(String screenName) async {
    await _analytics.logScreenView(
      screenName: screenName,
      screenClass: screenName,
    );
  }

  // E-commerce events
  static Future<void> logViewCoupon(String couponId, String storeName, String category) async {
    await _analytics.logEvent(
      name: 'view_coupon',
      parameters: {
        'coupon_id': couponId,
        'store_name': storeName,
        'category': category,
      },
    );
  }

  static Future<void> logAddToWishlist(String couponId, String storeName) async {
    await _analytics.logEvent(
      name: 'add_to_wishlist',
      parameters: {
        'coupon_id': couponId,
        'store_name': storeName,
      },
    );
  }

  // Custom events for Arabic coupon app
  static Future<void> logStreakAchieved(int streakDays) async {
    await _analytics.logEvent(
      name: 'streak_achieved',
      parameters: {
        'streak_days': streakDays,
      },
    );
  }

  static Future<void> logBadgeUnlocked(String badgeName) async {
    await _analytics.logEvent(
      name: 'badge_unlocked',
      parameters: {
        'badge_name': badgeName,
      },
    );
  }

  static Future<void> logEventParticipation(String eventName) async {
    await _analytics.logEvent(
      name: 'event_participation',
      parameters: {
        'event_name': eventName, // 'ramadan', 'white_friday', etc.
      },
    );
  }

  // Set user properties (anonymous)
  static Future<void> setUserProperty(String name, String value) async {
    await _analytics.setUserProperty(name: name, value: value);
  }

  static Future<void> setPreferredCategories(List<String> categories) async {
    await _analytics.setUserProperty(
      name: 'preferred_categories',
      value: categories.join(','),
    );
  }

  static Future<void> setAppLanguage(String language) async {
    await _analytics.setUserProperty(name: 'app_language', value: language);
  }

  static Future<void> logEvent(String name, {Map<String, dynamic>? parameters}) async {
    await _analytics.logEvent(
      name: name,
      parameters: parameters?.cast<String, Object>(),
    );
  }
}
