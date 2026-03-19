import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_core/firebase_core.dart';
import '../models/coupon_model.dart';

class FirestoreService {
  static final FirebaseFirestore _firestore = FirebaseFirestore.instance;
  static final CollectionReference _couponsCollection = _firestore.collection('coupons');
  static final CollectionReference _storesCollection = _firestore.collection('stores');
  static final CollectionReference _categoriesCollection = _firestore.collection('categories');
  static final CollectionReference _blogCollection = _firestore.collection('blog_posts');
  static final CollectionReference _eventsCollection = _firestore.collection('events');

  // Initialize service
  static Future<void> initialize() async {
    await Firebase.initializeApp();
  }

  // Coupons
  static Stream<List<Coupon>> getPopularCouponsStream({int limit = 10}) {
    return _couponsCollection
        .where('isActive', isEqualTo: true)
        .where('expiresAt', isGreaterThan: Timestamp.now())
        .orderBy('usageCount', descending: true)
        .limit(limit)
        .snapshots()
        .map((snapshot) => snapshot.docs
            .map((doc) => Coupon.fromFirestore(doc))
            .toList());
  }

  static Stream<List<Coupon>> getCouponsByCategoryStream(
    String category, {
    int limit = 20,
    DocumentSnapshot? startAfter,
  }) {
    Query query = _couponsCollection
        .where('isActive', isEqualTo: true)
        .where('expiresAt', isGreaterThan: Timestamp.now())
        .where('category', isEqualTo: category)
        .orderBy('usageCount', descending: true)
        .limit(limit);

    if (startAfter != null) {
      query = query.startAfterDocument(startAfter);
    }

    return query.snapshots().map((snapshot) => snapshot.docs
        .map((doc) => Coupon.fromFirestore(doc))
        .toList());
  }

  static Stream<List<Coupon>> searchCouponsStream(
    String searchTerm, {
    int limit = 20,
    DocumentSnapshot? startAfter,
  }) {
    Query query = _couponsCollection
        .where('isActive', isEqualTo: true)
        .where('expiresAt', isGreaterThan: Timestamp.now())
        .orderBy('description')
        .startAt([searchTerm])
        .endAt([searchTerm + '\uf8ff'])
        .limit(limit);

    if (startAfter != null) {
      query = query.startAfterDocument(startAfter);
    }

    return query.snapshots().map((snapshot) => snapshot.docs
        .map((doc) => Coupon.fromFirestore(doc))
        .toList());
  }

  static Future<Coupon?> getCouponById(String couponId) async {
    try {
      DocumentSnapshot doc = await _couponsCollection.doc(couponId).get();
      if (doc.exists) {
        return Coupon.fromFirestore(doc);
      }
      return null;
    } catch (e) {
      print('Error getting coupon: $e');
      return null;
    }
  }

  static Future<Coupon?> getCouponByCode(String code) async {
    try {
      QuerySnapshot snapshot = await _couponsCollection
          .where('code', isEqualTo: code)
          .where('isActive', isEqualTo: true)
          .limit(1)
          .get();

      if (snapshot.docs.isNotEmpty) {
        return Coupon.fromFirestore(snapshot.docs.first);
      }
      return null;
    } catch (e) {
      print('Error getting coupon by code: $e');
      return null;
    }
  }

  static Future<void> incrementCouponUsage(String couponId) async {
    try {
      await _couponsCollection.doc(couponId).update({
        'usageCount': FieldValue.increment(1),
        'updatedAt': Timestamp.now(),
      });
    } catch (e) {
      print('Error incrementing coupon usage: $e');
    }
  }

  static Stream<List<Coupon>> getStoreCouponsStream(
    String storeId, {
    int limit = 20,
    DocumentSnapshot? startAfter,
  }) {
    Query query = _couponsCollection
        .where('isActive', isEqualTo: true)
        .where('expiresAt', isGreaterThan: Timestamp.now())
        .where('storeId', isEqualTo: storeId)
        .orderBy('usageCount', descending: true)
        .limit(limit);

    if (startAfter != null) {
      query = query.startAfterDocument(startAfter);
    }

    return query.snapshots().map((snapshot) => snapshot.docs
        .map((doc) => Coupon.fromFirestore(doc))
        .toList());
  }

  // Stores
  static Future<Map<String, dynamic>?> getStoreById(String storeId) async {
    try {
      DocumentSnapshot doc = await _storesCollection.doc(storeId).get();
      if (doc.exists) {
        return doc.data() as Map<String, dynamic>;
      }
      return null;
    } catch (e) {
      print('Error getting store: $e');
      return null;
    }
  }

  static Stream<List<Map<String, dynamic>>> getStoresStream() {
    return _storesCollection
        .where('isActive', isEqualTo: true)
        .orderBy('name')
        .snapshots()
        .map((snapshot) => snapshot.docs
            .map((doc) => {
              'id': doc.id,
              ...doc.data() as Map<String, dynamic>,
            })
            .toList());
  }

  // Categories
  static Stream<List<Map<String, dynamic>>> getCategoriesStream() {
    return _categoriesCollection
        .where('isActive', isEqualTo: true)
        .orderBy('sortOrder')
        .snapshots()
        .map((snapshot) => snapshot.docs
            .map((doc) => {
              'id': doc.id,
              ...doc.data() as Map<String, dynamic>,
            })
            .toList());
  }

  // Blog Posts
  static Stream<List<Map<String, dynamic>>> getBlogPostsStream({
    int limit = 10,
    String? category,
    DocumentSnapshot? startAfter,
  }) {
    Query query = _blogCollection
        .where('isActive', isEqualTo: true)
        .orderBy('publishDate', descending: true)
        .limit(limit);

    if (category != null) {
      query = query.where('category', isEqualTo: category);
    }

    if (startAfter != null) {
      query = query.startAfterDocument(startAfter);
    }

    return query.snapshots().map((snapshot) => snapshot.docs
        .map((doc) => {
              'id': doc.id,
              ...doc.data() as Map<String, dynamic>,
            })
        .toList());
  }

  static Future<void> incrementBlogView(String blogId) async {
    try {
      await _blogCollection.doc(blogId).update({
        'viewCount': FieldValue.increment(1),
      });
    } catch (e) {
      print('Error incrementing blog view: $e');
    }
  }

  // Events
  static Stream<List<Map<String, dynamic>>> getActiveEventsStream() {
    Timestamp now = Timestamp.now();
    return _eventsCollection
        .where('isActive', isEqualTo: true)
        .where('startDate', isLessThanOrEqualTo: now)
        .where('endDate', isGreaterThanOrEqualTo: now)
        .orderBy('startDate')
        .snapshots()
        .map((snapshot) => snapshot.docs
            .map((doc) => {
              'id': doc.id,
              ...doc.data() as Map<String, dynamic>,
            })
            .toList());
  }

  // Analytics (write-only from app)
  static Future<void> trackAnalyticsEvent(Map<String, dynamic> eventData) async {
    try {
      await _firestore.collection('analytics').add({
        ...eventData,
        'timestamp': Timestamp.now(),
        'platform': 'flutter',
      });
    } catch (e) {
      print('Error tracking analytics: $e');
    }
  }

  // Cache helper methods
  static Future<void> cacheCoupons(List<Coupon> coupons) async {
    // This would integrate with Hive for local caching
    // Implementation depends on your caching strategy
  }

  static Future<List<Coupon>> getCachedCoupons() async {
    // This would retrieve cached coupons from Hive
    // Implementation depends on your caching strategy
    return [];
  }

  // Utility methods
  static Future<List<String>> getSearchSuggestions(String query) async {
    if (query.length < 2) return [];
    
    try {
      QuerySnapshot snapshot = await _couponsCollection
          .where('isActive', isEqualTo: true)
          .where('description', isGreaterThanOrEqualTo: query)
          .where('description', isLessThanOrEqualTo: query + '\uf8ff')
          .limit(5)
          .get();

      return snapshot.docs
          .map((doc) => Coupon.fromFirestore(doc).description)
          .toSet()
          .toList();
    } catch (e) {
      print('Error getting search suggestions: $e');
      return [];
    }
  }

  static Future<void> logUserActivity(String activity, Map<String, dynamic> data) async {
    try {
      await _firestore.collection('user_activity').add({
        'activity': activity,
        'data': data,
        'timestamp': Timestamp.now(),
        'anonymousId': await _getOrCreateAnonymousId(),
      });
    } catch (e) {
      print('Error logging user activity: $e');
    }
  }

  static Future<String> _getOrCreateAnonymousId() async {
    // This would use shared_preferences to store/retrieve anonymous user ID
    // For now, return a simple timestamp-based ID
    return 'user_${DateTime.now().millisecondsSinceEpoch}';
  }
}
