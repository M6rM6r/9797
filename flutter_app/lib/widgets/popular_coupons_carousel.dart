import 'package:flutter/material.dart';
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:carousel_slider/carousel_slider.dart';
import '../models/coupon_model.dart';
import '../widgets/coupon_card.dart';
import '../services/analytics_service.dart';

class PopularCouponsCarousel extends StatefulWidget {
  const PopularCouponsCarousel({super.key});

  @override
  State<PopularCouponsCarousel> createState() => _PopularCouponsCarouselState();
}

class _PopularCouponsCarouselState extends State<PopularCouponsCarousel> {
  final FirebaseFirestore _firestore = FirebaseFirestore.instance;
  List<Coupon> _popularCoupons = [];
  bool _isLoading = true;
  final CarouselController _carouselController = CarouselController();

  @override
  void initState() {
    super.initState();
    _loadPopularCoupons();
  }

  Future<void> _loadPopularCoupons() async {
    try {
      QuerySnapshot snapshot = await _firestore
          .collection('coupons')
          .where('isActive', isEqualTo: true)
          .where('expiresAt', isGreaterThan: Timestamp.now())
          .orderBy('usageCount', descending: true)
          .limit(10)
          .get();

      final coupons = snapshot.docs.map((doc) {
        return Coupon.fromFirestore(doc);
      }).toList();

      if (mounted) {
        setState(() {
          _popularCoupons = coupons;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Container(
        height: 180,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          color: Colors.grey[100],
        ),
        child: const Center(
          child: CircularProgressIndicator(),
        ),
      );
    }

    if (_popularCoupons.isEmpty) {
      return const SizedBox.shrink();
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        // Section Header
        Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Row(
            children: [
              Icon(
                Icons.local_fire_department,
                color: Colors.orange[600],
                size: 20,
              ),
              const SizedBox(width: 8),
              Text(
                'الكوبونات الأكثر شعبية',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.bold,
                  color: Colors.orange[600],
                ),
              ),
              const Spacer(),
              TextButton(
                onPressed: () {
                  // TODO: Navigate to all popular coupons
                },
                child: Text(
                  'عرض الكل',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.primary,
                  ),
                ),
              ),
            ],
          ),
        ),

        // Carousel
        CarouselSlider.builder(
          carouselController: _carouselController,
          options: CarouselOptions(
            height: 180,
            viewportFraction: 0.8,
            enlargeCenterPage: true,
            enableInfiniteScroll: _popularCoupons.length > 3,
            autoPlay: true,
            autoPlayInterval: const Duration(seconds: 5),
            autoPlayAnimationDuration: const Duration(milliseconds: 800),
            autoPlayCurve: Curves.easeInOut,
            scrollDirection: Axis.horizontal,
            reverse: false, // Keep false for RTL, the carousel will handle it
          ),
          itemCount: _popularCoupons.length,
          itemBuilder: (context, index, realIndex) {
            final coupon = _popularCoupons[index];
            return GestureDetector(
              onTap: () {
                Navigator.pushNamed(
                  context,
                  '/coupon',
                  arguments: {
                    'couponId': coupon.id,
                    'coupon': coupon,
                  },
                );
                AnalyticsService.logEvent('popular_coupon_tapped', parameters: {
                  'coupon_id': coupon.id,
                  'position': index,
                });
              },
              child: Container(
                width: double.infinity,
                margin: const EdgeInsets.symmetric(horizontal:4),
                child: CouponCard(
                  coupon: coupon,
                ),
              ),
            );
          },
        ),

        // Carousel Indicators
        if (_popularCoupons.length > 1)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: _popularCoupons.asMap().entries.map((entry) {
                return GestureDetector(
                  onTap: () => _carouselController.animateToPage(entry.key),
                  child: Container(
                    width: 8,
                    height: 8,
                    margin: const EdgeInsets.symmetric(horizontal: 4),
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Theme.of(context).colorScheme.primary.withOpacity(
                        _getCurrentIndex() == entry.key ? 0.8 : 0.3,
                      ),
                    ),
                  ),
                );
              }).toList(),
            ),
          ),
      ],
    );
  }

  int _getCurrentIndex() {
    // This is a simplified approach - in a real app, you'd track the current index
    return 0;
  }
}
