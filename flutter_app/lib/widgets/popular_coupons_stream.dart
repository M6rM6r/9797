import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import '../models/coupon_model.dart';
import '../services/firestore_service.dart';
import 'coupon_card.dart';

class PopularCouponsStream extends StatelessWidget {
  final int limit;
  final String? category;
  final String? searchTerm;

  const PopularCouponsStream({
    super.key,
    this.limit = 10,
    this.category,
    this.searchTerm,
  });

  @override
  Widget build(BuildContext context) {
    Stream<QuerySnapshot> stream;
    
    if (searchTerm != null && searchTerm!.isNotEmpty) {
      // Search stream
      stream = FirebaseFirestore.instance
          .collection('coupons')
          .where('isActive', isEqualTo: true)
          .where('expiresAt', isGreaterThan: Timestamp.now())
          .orderBy('description')
          .startAt([searchTerm])
          .endAt([searchTerm! + '\uf8ff'])
          .limit(limit)
          .snapshots();
    } else if (category != null && category != 'الكل') {
      // Category stream
      stream = FirebaseFirestore.instance
          .collection('coupons')
          .where('isActive', isEqualTo: true)
          .where('expiresAt', isGreaterThan: Timestamp.now())
          .where('category', isEqualTo: category)
          .orderBy('usageCount', descending: true)
          .limit(limit)
          .snapshots();
    } else {
      // Popular stream
      stream = FirebaseFirestore.instance
          .collection('coupons')
          .where('isActive', isEqualTo: true)
          .where('expiresAt', isGreaterThan: Timestamp.now())
          .orderBy('usageCount', descending: true)
          .limit(limit)
          .snapshots();
    }

    return StreamBuilder<QuerySnapshot>(
      stream: stream,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(
            child: CircularProgressIndicator(),
          );
        }

        if (snapshot.hasError) {
          return Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  Icons.error_outline,
                  size: 64,
                  color: Theme.of(context).colorScheme.error,
                ),
                const SizedBox(height: 16),
                Text(
                  'حدث خطأ في تحميل الكوبونات',
                  style: Theme.of(context).textTheme.titleMedium,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 8),
                Text(
                  snapshot.error.toString(),
                  style: Theme.of(context).textTheme.bodySmall,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 16),
                ElevatedButton(
                  onPressed: () {
                    // Refresh the stream by rebuilding
                    (context as Element).markNeedsBuild();
                  },
                  child: const Text('إعادة المحاولة'),
                ),
              ],
            ),
          );
        }

        if (!snapshot.hasData || snapshot.data!.docs.isEmpty) {
          return Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  Icons.local_offer_outlined,
                  size: 64,
                  color: Colors.grey[400],
                ),
                const SizedBox(height: 16),
                Text(
                  'لا توجد كوبونات متاحة',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: Colors.grey[600],
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 8),
                Text(
                  searchTerm != null 
                      ? 'جرب البحث بكلمات أخرى'
                      : category != null && category != 'الكل'
                          ? 'لا توجد كوبونات في هذه الفئة حالياً'
                          : 'جرب تغيير الفئة أو البحث',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: Colors.grey[500],
                  ),
                  textAlign: TextAlign.center,
                ),
              ],
            ),
          );
        }

        final coupons = snapshot.data!.docs.map((doc) {
          final coupon = Coupon.fromFirestore(doc);
          // Load store info if not available
          if (coupon.storeName == null || coupon.storeLogo == null) {
            _loadStoreInfo(coupon);
          }
          return coupon;
        }).toList();

        return RefreshIndicator(
          onRefresh: () async {
            // Trigger a refresh
            await FirebaseFirestore.instance
                .collection('coupons')
                .limit(1)
                .get();
          },
          child: ListView.builder(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(16),
            itemCount: coupons.length,
            itemBuilder: (context, index) {
              final coupon = coupons[index];
              return Padding(
                padding: const EdgeInsets.only(bottom: 16),
                child: CouponCard(
                  coupon: coupon,
                  onTap: () {
                    Navigator.pushNamed(
                      context,
                      '/coupon',
                      arguments: {
                        'couponId': snapshot.data!.docs[index].id,
                        'coupon': coupon,
                      },
                    );
                  },
                ),
              );
            },
          ),
        );
      },
    );
  }

  Future<void> _loadStoreInfo(Coupon coupon) async {
    try {
      final storeData = await FirestoreService.getStoreById(coupon.storeId);
      if (storeData != null) {
        // Update coupon with store info
        // This would typically be handled by state management
        // For now, we'll just log it
        print('Loaded store info for ${coupon.storeId}: ${storeData['name']}');
      }
    } catch (e) {
      print('Error loading store info: $e');
    }
  }
}

class PopularCouponsGrid extends StatelessWidget {
  final int limit;
  final String? category;
  final String? searchTerm;

  const PopularCouponsGrid({
    super.key,
    this.limit = 20,
    this.category,
    this.searchTerm,
  });

  @override
  Widget build(BuildContext context) {
    Stream<QuerySnapshot> stream;
    
    if (searchTerm != null && searchTerm!.isNotEmpty) {
      stream = FirebaseFirestore.instance
          .collection('coupons')
          .where('isActive', isEqualTo: true)
          .where('expiresAt', isGreaterThan: Timestamp.now())
          .orderBy('description')
          .startAt([searchTerm])
          .endAt([searchTerm! + '\uf8ff'])
          .limit(limit)
          .snapshots();
    } else if (category != null && category != 'الكل') {
      stream = FirebaseFirestore.instance
          .collection('coupons')
          .where('isActive', isEqualTo: true)
          .where('expiresAt', isGreaterThan: Timestamp.now())
          .where('category', isEqualTo: category)
          .orderBy('usageCount', descending: true)
          .limit(limit)
          .snapshots();
    } else {
      stream = FirebaseFirestore.instance
          .collection('coupons')
          .where('isActive', isEqualTo: true)
          .where('expiresAt', isGreaterThan: Timestamp.now())
          .orderBy('usageCount', descending: true)
          .limit(limit)
          .snapshots();
    }

    return StreamBuilder<QuerySnapshot>(
      stream: stream,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(
            child: CircularProgressIndicator(),
          );
        }

        if (snapshot.hasError) {
          return Center(
            child: Text('خطأ: ${snapshot.error}'),
          );
        }

        if (!snapshot.hasData || snapshot.data!.docs.isEmpty) {
          return const Center(
            child: Text('لا توجد كوبونات'),
          );
        }

        final coupons = snapshot.data!.docs.map((doc) {
          return Coupon.fromFirestore(doc);
        }).toList();

        return GridView.builder(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2,
            childAspectRatio: 0.7,
            crossAxisSpacing: 16,
            mainAxisSpacing: 16,
          ),
          itemCount: coupons.length,
          itemBuilder: (context, index) {
            final coupon = coupons[index];
            return CouponCard(
              coupon: coupon,
              onTap: () {
                Navigator.pushNamed(
                  context,
                  '/coupon',
                  arguments: {
                    'couponId': snapshot.data!.docs[index].id,
                    'coupon': coupon,
                  },
                );
              },
            );
          },
        );
      },
    );
  }
}
